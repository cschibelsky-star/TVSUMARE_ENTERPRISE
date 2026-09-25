<?php
// Cron diário do Radar Regional TV Sumaré para o ambiente VPS.
// O scheduler pode chamar este arquivo periodicamente; a trava abaixo garante no máximo 1 execução por dia.

define('TVS_RADAR_CRON', true);
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once __DIR__.'/monitor_lib.php';

/*
 * RECOVERY 2026-09-24 — one-shot.
 * Preserva o estado atual dos JSONs e restaura matérias removidas
 * exclusivamente pelo módulo de validade editorial.
 */
$recoveryMarker=dirname(__DIR__).'/data/recovery_20260924_done.json';
if(!is_file($recoveryMarker)){
  $dataDir=dirname(__DIR__).'/data';
  $stamp=date('Ymd_His');
  $backupDir=$dataDir.'/recovery_backup_'.$stamp;
  @mkdir($backupDir,0775,true);
  $backupFiles=['noticias.json','lixeira_noticias.json','materias_aprovacao.json','pautas_descartadas.json','videos.json','videos_ia.json','radar_log.json','radar_status.json','content_validity_log.json'];
  foreach($backupFiles as $bf){
    $src=$dataDir.'/'.$bf;
    if(is_file($src)) @copy($src,$backupDir.'/'.$bf);
  }

  $news=tvs_read_json_file($dataDir.'/noticias.json'); if(!is_array($news)) $news=[];
  $trash=tvs_read_json_file($dataDir.'/lixeira_noticias.json'); if(!is_array($trash)) $trash=[];
  $existing=[];
  foreach($news as $n){
    $id=(string)($n['id']??'');
    if($id!=='') $existing[$id]=1;
  }

  $restored=0; $keptTrash=[];
  foreach($trash as $item){
    $reason=(string)($item['archive_reason']??'');
    $isValidityArchive=
      stripos($reason,'validade editorial')!==false ||
      stripos($reason,'Conteúdo perdeu validade')!==false;
    $id=(string)($item['id']??'');
    if($isValidityArchive && $id!=='' && empty($existing[$id])){
      unset($item['deleted_at'],$item['archive_reason']);
      $item['status']='publicado';
      $item['recovered_at']=date('c');
      $item['recovered_reason']='Restauração controlada após auditoria editorial de 24/09/2026.';
      $item['validity_status']='revisao_solicitada';
      $news[]=$item;
      $existing[$id]=1;
      $restored++;
      continue;
    }
    $keptTrash[]=$item;
  }

  if($restored>0){
    usort($news,function($a,$b){
      $ta=strtotime((string)($a['published_at']??$a['created_at']??''))?:0;
      $tb=strtotime((string)($b['published_at']??$b['created_at']??''))?:0;
      return $tb<=>$ta;
    });
    tvs_save_json_file($dataDir.'/noticias.json',array_values($news));
    tvs_save_json_file($dataDir.'/lixeira_noticias.json',array_values($keptTrash));
  }

  tvs_save_json_file($recoveryMarker,[
    'executed_at'=>date('c'),
    'backup_dir'=>$backupDir,
    'restored'=>$restored,
    'published_total'=>count($news),
    'trash_remaining'=>count($keptTrash)
  ]);
  echo "RECOVERY_20260924 restored={$restored} published_total=".count($news)." trash_remaining=".count($keptTrash)." backup={$backupDir}\n";
}

$cronStarted=microtime(true);
$homeLogDir=dirname(dirname(__DIR__)).'/logs';
$appLogDir=dirname(__DIR__).'/logs';
$cronLogDir=(is_dir($homeLogDir) && is_writable($homeLogDir)) ? $homeLogDir : $appLogDir;
if(!is_dir($cronLogDir)) @mkdir($cronLogDir,0775,true);
$cronLogFile=$cronLogDir.'/tvsumare-cron-radar.log';
@file_put_contents($cronLogFile,date('c')." START mode=cron_hostgator pid=".getmypid()."\n",FILE_APPEND|LOCK_EX);
register_shutdown_function(function() use ($cronLogFile,$cronStarted){
  $err=error_get_last();
  if($err && in_array($err['type']??0,[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
    $duration=(int)round((microtime(true)-$cronStarted)*1000);
    @file_put_contents($cronLogFile,date('c')." FATAL duration_ms={$duration} message=".str_replace(["\r","\n"],' ',(string)($err['message']??''))."\n",FILE_APPEND|LOCK_EX);
  }
});

$_SERVER['REQUEST_METHOD']='CRON';
require_once __DIR__.'/radar-regional.php';

/*
 * QUALITY REPAIR 2026-09-25 — one-shot.
 * Retira do ar matérias antigas que chegaram publicadas apenas com manchete/RSS,
 * limpa sufixos de fonte do título e devolve itens incompletos para revisão.
 */
$qualityMarker=dirname(__DIR__).'/data/published_quality_repair_20260925_done.json';
if(!is_file($qualityMarker)){
  $dataDir=dirname(__DIR__).'/data';
  $stamp=date('Ymd_His');
  $newsPath=$dataDir.'/noticias.json';
  $queuePath=$dataDir.'/materias_aprovacao.json';
  if(is_file($newsPath)) @copy($newsPath,$dataDir.'/noticias.quality-backup-'.$stamp.'.json');
  if(is_file($queuePath)) @copy($queuePath,$dataDir.'/materias_aprovacao.quality-backup-'.$stamp.'.json');

  $news=tvs_read_json_file($newsPath); if(!is_array($news)) $news=[];
  $queue=tvs_read_json_file($queuePath); if(!is_array($queue)) $queue=[];
  $seen=[];
  foreach($queue as $q){
    $u=trim((string)($q['source_url']??$q['url']??''));
    if($u!=='') $seen['u:'.$u]=1;
    $tk=tvs_lower(tvs_clean_text((string)($q['title']??'')));
    if($tk!=='') $seen['t:'.$tk]=1;
  }

  $kept=[]; $moved=0; $titlesCleaned=0; $duplicateSkipped=0;
  foreach($news as $item){
    if(!is_array($item)) continue;
    $source=(string)($item['source']??'');
    $oldTitle=(string)($item['title']??'');
    $cleanTitle=function_exists('tvs_editorial_clean_title')
      ? tvs_editorial_clean_title($oldTitle,$source)
      : trim($oldTitle);
    if($cleanTitle!=='' && $cleanTitle!==$oldTitle){
      $item['title']=$cleanTitle;
      if(empty($item['seo_title']) || trim((string)$item['seo_title'])===$oldTitle) $item['seo_title']=$cleanTitle;
      $titlesCleaned++;
    }

    $body=(string)($item['body']??$item['content']??'');
    $url=trim((string)($item['source_url']??$item['url']??''));
    $thin=function_exists('tvs_editorial_body_is_thin')
      ? tvs_editorial_body_is_thin($item['title']??'',$body,$source)
      : tvs_strlen(tvs_clean_text($body))<300;
    $unresolvedGoogle=$url!=='' && preg_match('~news\.google\.com~i',$url);

    if($thin || $unresolvedGoogle){
      $reason=$thin
        ? 'Matéria publicada com texto jornalístico insuficiente ou repetição da manchete.'
        : 'Matéria publicada com URL do agregador Google News ainda não resolvida.';
      $oldId=(string)($item['id']??'');
      $item['old_news_id']=$oldId;
      $item['id']=uniqid('rework_');
      $item['status']='aguardando';
      $item['editorial_state']='needs_review';
      $item['review_level']='precisa_revisao';
      $item['editorial_status']='Correção obrigatória';
      $item['publication_eligible']=0;
      $item['home_eligible']=0;
      $item['queue_status']='processing';
      $item['queue_pending_reasons']=[$reason];
      $item['quality_repair_reason']=$reason;
      $item['unpublished_at']=date('c');
      $item['previously_published_at']=$item['published_at']??'';

      $uKey=$url!==''?'u:'.$url:'';
      $tKey='t:'.tvs_lower(tvs_clean_text((string)($item['title']??'')));
      if(($uKey!=='' && isset($seen[$uKey])) || isset($seen[$tKey])){
        $duplicateSkipped++;
      } else {
        $queue[]=$item;
        if($uKey!=='') $seen[$uKey]=1;
        $seen[$tKey]=1;
      }
      $moved++;
      continue;
    }

    $kept[]=$item;
  }

  tvs_save_json_file($newsPath,array_values($kept));
  tvs_save_json_file($queuePath,array_values($queue));
  $result=[
    'executed_at'=>date('c'),
    'backup_stamp'=>$stamp,
    'published_before'=>count($news),
    'published_after'=>count($kept),
    'moved_to_review'=>$moved,
    'titles_cleaned'=>$titlesCleaned,
    'queue_duplicates_skipped'=>$duplicateSkipped,
    'queue_after'=>count($queue)
  ];
  tvs_save_json_file($qualityMarker,$result);
  echo 'PUBLISHED_QUALITY_REPAIR '.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}

/*
 * QUALITY DRAFT RECOVERY 2026-09-25 — one-shot.
 * Preserva para revisão humana as matérias retiradas do ar pela auditoria,
 * sem deixá-las sujeitas à limpeza automática da fila do Radar.
 */
$qualityDraftMarker=dirname(__DIR__).'/data/published_quality_draft_recovery_20260925_done.json';
if(!is_file($qualityDraftMarker)){
  $dataDir=dirname(__DIR__).'/data';
  $meta=tvs_read_json_file($qualityMarker); if(!is_array($meta)) $meta=[];
  $stamp=trim((string)($meta['backup_stamp']??''));
  $backup=$stamp!=='' ? $dataDir.'/noticias.quality-backup-'.$stamp.'.json' : '';
  $sourceNews=$backup!=='' ? tvs_read_json_file($backup) : [];
  if(!is_array($sourceNews)) $sourceNews=[];
  $draftPath=$dataDir.'/rascunhos.json';
  $drafts=tvs_read_json_file($draftPath); if(!is_array($drafts)) $drafts=[];
  $seen=[];
  foreach($drafts as $d){
    $u=trim((string)($d['source_url']??$d['url']??''));
    if($u!=='') $seen['u:'.$u]=1;
    $t=tvs_lower(tvs_clean_text((string)($d['title']??'')));
    if($t!=='') $seen['t:'.$t]=1;
  }
  $added=0;
  foreach($sourceNews as $item){
    if(!is_array($item)) continue;
    $source=(string)($item['source']??'');
    $item['title']=function_exists('tvs_editorial_clean_title')
      ? tvs_editorial_clean_title($item['title']??'',$source)
      : trim((string)($item['title']??''));
    $body=(string)($item['body']??$item['content']??'');
    $url=trim((string)($item['source_url']??$item['url']??''));
    $thin=function_exists('tvs_editorial_body_is_thin')
      ? tvs_editorial_body_is_thin($item['title']??'',$body,$source)
      : tvs_strlen(tvs_clean_text($body))<300;
    $unresolvedGoogle=$url!=='' && preg_match('~news\.google\.com~i',$url);
    if(!$thin && !$unresolvedGoogle) continue;
    $uKey=$url!==''?'u:'.$url:'';
    $tKey='t:'.tvs_lower(tvs_clean_text((string)($item['title']??'')));
    if(($uKey!=='' && isset($seen[$uKey])) || isset($seen[$tKey])) continue;
    $item['old_news_id']=$item['id']??'';
    $item['id']=uniqid('draft_quality_');
    $item['status']='rascunho';
    $item['editorial_state']='needs_review';
    $item['review_level']='precisa_revisao';
    $item['editorial_status']='Correção obrigatória';
    $item['publication_eligible']=0;
    $item['home_eligible']=0;
    $item['quality_repair_reason']=$thin
      ? 'Texto jornalístico insuficiente ou repetição da manchete.'
      : 'URL do agregador Google News ainda não resolvida.';
    $item['created_at']=$item['created_at']??date('c');
    $item['updated_at']=date('c');
    $drafts[]=$item;
    if($uKey!=='') $seen[$uKey]=1;
    $seen[$tKey]=1;
    $added++;
  }
  tvs_save_json_file($draftPath,array_values($drafts));
  $draftResult=['executed_at'=>date('c'),'backup'=>$backup,'added_to_drafts'=>$added,'drafts_after'=>count($drafts)];
  tvs_save_json_file($qualityDraftMarker,$draftResult);
  echo 'PUBLISHED_QUALITY_DRAFT_RECOVERY '.json_encode($draftResult,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}

/*
 * EDITORIAL POLICY MIGRATION 2026-09-24 — one-shot.
 * Reclassifica o backlog sem apagar matérias publicadas:
 * - aplica hard gates regionais/temporais;
 * - desacopla imagem da validade editorial;
 * - preserva backup integral da fila antes da migração.
 */
$policyMarker=dirname(__DIR__).'/data/editorial_policy_20260924_done.json';
if(!is_file($policyMarker)){
  $dataDir=dirname(__DIR__).'/data';
  $stamp=date('Ymd_His');
  $queuePath=$dataDir.'/materias_aprovacao.json';
  $queueBackup=$dataDir.'/materias_aprovacao.policy-backup-'.$stamp.'.json';
  if(is_file($queuePath)) @copy($queuePath,$queueBackup);

  $before=tvs_queue_read(); if(!is_array($before)) $before=[];
  $beforeCount=count($before);
  $beforeImage=0;
  foreach($before as $item){ if(!empty($item['image_review_required'])) $beforeImage++; }

  $gateResult=tvs_radar_enforce_queue_rules(true);

  $queue=tvs_queue_read(); if(!is_array($queue)) $queue=[];
  $decoupled=0;
  foreach($queue as &$item){
    $hasImage=trim((string)($item['image']??''))!=='' && tvs_is_valid_image_url($item['image']??'');
    $item['image_status']=$hasImage?'verified':'missing';
    $item['home_eligible']=$hasImage?1:0;
    $item['publication_eligible']=1;
    $item['video_eligible']=1;
    $item['editorial_state']='qualified';
    $item['region_status']='confirmed';
    $item['freshness_status']='current';
    $item['source_status']=tvs_radar_is_google_news_url($item['source_url']??'')?'unresolved_aggregator':'original';

    if(!empty($item['image_review_required'])){
      $item['image_review_required']=0;
      $item['image_review_reason']='Imagem ausente: publicação textual permitida; Home/Hero/redes exigem imagem confirmada.';
      $sensitive=tvs_radar_sensitive_topic(
        $item['title']??'',
        ($item['subtitle']??'').' '.($item['summary']??'').' '.($item['body']??'')
      );
      $score=(int)($item['editorial_score']??0);
      $state=tvs_radar_status_from_score($score,$sensitive);
      $item['review_level']=$state['review_level'];
      $item['editorial_status']=$state['editorial_status'];
      $item['sensitive_review_required']=!empty($state['sensitive'])?1:0;
      $decoupled++;
    }
  }
  unset($item);
  tvs_queue_save($queue);

  $result=[
    'executed_at'=>date('c'),
    'backup'=>$queueBackup,
    'before_total'=>$beforeCount,
    'before_image_review'=>$beforeImage,
    'hard_gate_removed'=>(int)($gateResult['removed']??0),
    'hard_gate_changed'=>(int)($gateResult['changed']??0),
    'after_total'=>count($queue),
    'image_decoupled'=>$decoupled
  ];
  tvs_save_json_file($policyMarker,$result);
  echo 'EDITORIAL_POLICY_MIGRATION '.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}

$cleanup = function_exists('tvs_radar_enforce_queue_rules')
  ? tvs_radar_enforce_queue_rules(true)
  : ['removed'=>0,'changed'=>0,'total'=>0];
@file_put_contents(
  $cronLogFile,
  date('c')." BACKLOG_CLEANUP removed=".(int)($cleanup['removed']??0)." changed=".(int)($cleanup['changed']??0)." total=".(int)($cleanup['total']??0)."\n",
  FILE_APPEND|LOCK_EX
);
echo "BACKLOG_CLEANUP removed=".(int)($cleanup['removed']??0)." changed=".(int)($cleanup['changed']??0)." total=".(int)($cleanup['total']??0)."\n";

/*
 * Retenção editorial pós-publicação.
 * A aprovação é a fronteira de qualidade; depois disso a matéria permanece pública
 * até o prazo de retenção e, ao vencer, é arquivada com rastreabilidade.
 */
$publishedPath=dirname(__DIR__).'/data/noticias.json';
$trashPath=dirname(__DIR__).'/data/lixeira_noticias.json';
$validityLogPath=dirname(__DIR__).'/data/content_validity_log.json';
$published=tvs_read_json_file($publishedPath); if(!is_array($published)) $published=[];
$trash=tvs_read_json_file($trashPath); if(!is_array($trash)) $trash=[];
$validityLog=tvs_read_json_file($validityLogPath); if(!is_array($validityLog)) $validityLog=[];
$kept=[]; $archivedByRetention=0; $now=date('c');
foreach($published as $item){
  $title=(string)($item['title']??'');
  $summary=(string)($item['summary']??'');
  $subtitle=(string)($item['subtitle']??'');
  $category=(string)($item['category']??'');
  $txt=tvs_lower($category.' '.$title.' '.$subtitle.' '.$summary);
  $limit=90;
  if(preg_match('~frente fria|chuva|temporal|alerta|interdi[cç][aã]o|tr[aâ]nsito|plant[aã]o~iu',$txt)) $limit=7;
  elseif(preg_match('~emprego|vagas|processo seletivo|recrutamento|evento|show|festival|agenda|inscri[cç][aã]o|matr[ií]cula|curso|feira|campanha~iu',$txt)) $limit=30;
  $raw=(string)($item['published_at']??$item['created_at']??$item['date']??'');
  $ts=$raw!=='' ? strtotime($raw) : false;
  if($ts===false){ $kept[]=$item; continue; }
  $age=max(0,(int)floor((time()-$ts)/86400));
  if($age<=$limit){ $kept[]=$item; continue; }
  $item['status']='arquivado';
  $item['deleted_at']=$now;
  $item['archive_reason']="Arquivamento automático por retenção editorial ({$limit} dias).";
  $trash[]=$item;
  $validityLog[]=[
    'id'=>uniqid('valid_'),
    'news_id'=>$item['id']??'',
    'title'=>$title!==''?$title:'Sem título',
    'action'=>'ARQUIVADA_RETENCAO',
    'reason'=>$item['archive_reason'],
    'created_at'=>$now
  ];
  $archivedByRetention++;
}
if($archivedByRetention>0){
  tvs_save_json_file($publishedPath,array_values($kept));
  tvs_save_json_file($trashPath,array_values($trash));
  tvs_save_json_file($validityLogPath,array_slice($validityLog,-500));
}
@file_put_contents($cronLogFile,date('c')." RETENTION_ARCHIVE archived={$archivedByRetention} active=".count($kept)."\n",FILE_APPEND|LOCK_EX);
echo "RETENTION_ARCHIVE archived={$archivedByRetention} active=".count($kept)."\n";

$cfg = function_exists('tvs_radar_config') ? tvs_radar_config() : ['per_city'=>6,'auto_daily'=>true,'last_auto_date'=>''];
$today=date('Y-m-d');

if(empty($cfg['auto_daily'])){
  @file_put_contents($cronLogFile,date('c')." SKIP reason=auto_daily_disabled\n",FILE_APPEND|LOCK_EX);
  echo "Radar automatico desativado nas configuracoes.\n";
  exit(0);
}

if((string)($cfg['last_auto_date']??'')===$today){
  $publicNews=function_exists('tvs_read_json_file') ? tvs_read_json_file($newsFile) : [];
  $recentPublic=0;
  if(is_array($publicNews)){
    $cutoff=time()-(21*86400);
    foreach($publicNews as $item){
      if(!is_array($item)) continue;
      $raw=(string)($item['published_at']??$item['created_at']??'');
      $ts=$raw!=='' ? strtotime($raw) : false;
      if($ts!==false && $ts>=$cutoff) $recentPublic++;
    }
  }
  if($recentPublic>=6){
    @file_put_contents($cronLogFile,date('c')." SKIP reason=already_ran_today date={$today} recent_public={$recentPublic}\n",FILE_APPEND|LOCK_EX);
    echo "Radar automatico ja executado hoje ({$today}); {$recentPublic} noticia(s) publica(s) recentes.\n";
    exit(0);
  }
  @file_put_contents($cronLogFile,date('c')." REFILL reason=sparse_public_news date={$today} recent_public={$recentPublic}\n",FILE_APPEND|LOCK_EX);
}

$n = function_exists('tvs_radar_update_queue')
  ? tvs_radar_update_queue(max(1,min(30,(int)($cfg['per_city']??20))))
  : 0;

$cfg['last_auto_date']=$today;
if(function_exists('tvs_radar_save_config')) tvs_radar_save_config($cfg);

if(function_exists('tvs_radar_save_status')){
  tvs_radar_save_status([
    'last_run'=>date('c'),
    'last_mode'=>'cron_daily',
    'last_generated'=>$n,
    'last_message'=>$n>0?"{$n} matéria(s) gerada(s) pela atualização diária.":'Nenhuma matéria nova gerada na atualização diária.'
  ]);
}

$queue=function_exists('tvs_queue_read') ? tvs_queue_read() : [];
if(!is_array($queue)) $queue=[];

$summary=[
  'date'=>$today,
  'generated_at'=>date('c'),
  'generated'=>$n,
  'new_today'=>0,
  'publishable'=>0,
  'review'=>0,
  'image_review'=>0,
  'dashboard_read_at'=>'',
  'whatsapp_sent_at'=>'',
  'radar_url'=>'admin/radar-regional.php'
];

foreach($queue as $item){
  $created=substr((string)($item['created_at']??''),0,10);
  if($created!==$today) continue;
  if(($item['status']??'aguardando')!=='aguardando') continue;

  $summary['new_today']++;

  if(!empty($item['image_review_required'])){
    $summary['image_review']++;
    continue;
  }

  if(function_exists('tvs_radar_can_direct_approve') && tvs_radar_can_direct_approve($item)){
    $summary['publishable']++;
  } else {
    $summary['review']++;
  }
}

$summary['whatsapp_text']='TV Sumaré — Atualização diária concluída'."\n"
  .$summary['new_today'].' nova(s) matéria(s) no Radar'."\n"
  .$summary['publishable'].' publicável(is)'."\n"
  .$summary['review'].' para revisão editorial'."\n"
  .$summary['image_review'].' para revisão de imagem'."\n"
  .'Acesse o painel da TV Sumaré para conferir.';

$summary['whatsapp_status']='aguardando_configuracao';
$summary['whatsapp_error']='';
$whatsappWebhook=trim((string)(getenv('TVSUMARE_WHATSAPP_WEBHOOK_URL') ?: ''));

if($whatsappWebhook!==''){
  $summary['whatsapp_status']='falha';
  if(function_exists('tvs_outbound_curl_options')){
    $curlOptions=tvs_outbound_curl_options($whatsappWebhook,15);
    if($curlOptions!==null && function_exists('curl_init')){
      $payload=json_encode([
        'event'=>'tvsumare.radar.daily',
        'date'=>$today,
        'message'=>$summary['whatsapp_text'],
        'summary'=>$summary
      ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $ch=curl_init($whatsappWebhook);
      curl_setopt_array($ch,$curlOptions+[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>$payload
      ]);
      $response=curl_exec($ch);
      $httpCode=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
      $curlError=(string)curl_error($ch);
      curl_close($ch);
      if($response!==false && $httpCode>=200 && $httpCode<300){
        $summary['whatsapp_status']='enviado';
        $summary['whatsapp_sent_at']=date('c');
      } else {
        $summary['whatsapp_error']=$curlError!=='' ? $curlError : 'HTTP '.$httpCode;
      }
    } else {
      $summary['whatsapp_error']='Webhook bloqueado pela política de saída ou cURL indisponível.';
    }
  } else {
    $summary['whatsapp_error']='Proteção de saída indisponível.';
  }
}

$notificationFile=dirname(__DIR__).'/data/radar_notification.json';
if(function_exists('tvs_save_json_file')) tvs_save_json_file($notificationFile,$summary);

$duration=(int)round((microtime(true)-$cronStarted)*1000);
@file_put_contents($cronLogFile,date('c')." END generated={$n} new_today={$summary['new_today']} publishable={$summary['publishable']} review={$summary['review']} image_review={$summary['image_review']} whatsapp={$summary['whatsapp_status']} duration_ms={$duration}\n",FILE_APPEND|LOCK_EX);

echo "Radar diario executado. Materias geradas: {$n}; WhatsApp: {$summary['whatsapp_status']}\n";
