<?php
$path='/var/www/html/admin/reporter-ia.php';
$code=file_get_contents($path);
if($code===false){
    fwrite(STDERR,"Reporter IA não encontrado\n");
    exit(1);
}

$required=[
    'provider block'=>'function rpia_provider_blocked($cfg)',
    'send lock'=>"send_lock_at",
    'dedupe'=>'function rpia_is_latest_approved_job($job,$jobs)',
    'approval gate'=>"roteiro_aprovado",
];
foreach($required as $label=>$marker){
    if(strpos($code,$marker)===false){
        fwrite(STDERR,"Reporter {$label}: marcador moderno ausente; abortando\n");
        exit(2);
    }
    echo "Reporter {$label}: fluxo moderno preservado.\n";
}

if(strpos($code,'function rpia_job_state($j)')===false){
    $anchor="\n\n$msg=''; $err='';";
    $pos=strpos($code,$anchor);
    if($pos===false){
        fwrite(STDERR,"Reporter helper anchor não encontrado\n");
        exit(3);
    }
    $helpers=<<<'PHP'

function rpia_job_state($j){
  $s=strtolower((string)($j['status']??'roteiro_revisao'));
  if(in_array($s,['cancelado','cancelled','canceled'],true)) return 'cancelled';
  if(in_array($s,['heygen_falhou','failed','falhou','erro'],true)) return 'failed';
  if(in_array($s,['publicado','published'],true)) return 'published';
  if(in_array($s,['video_pronto','completed','complete','ready','pronto'],true) || !empty($j['video_url']) || !empty($j['captioned_video_url'])) return 'completed';
  if(in_array($s,['heygen_agente_processando','processing','enviado','submitted'],true) || !empty($j['heygen_session_id']) || !empty($j['heygen_video_id']) || !empty($j['send_lock_at'])) return 'processing';
  return 'ready';
}
function rpia_state_label($state){
  return ['ready'=>'Roteiro pronto','processing'=>'Processando','completed'=>'Vídeo pronto','failed'=>'Falhou','cancelled'=>'Cancelado','published'=>'Publicado'][$state]??'Aguardando';
}
PHP;
    $code=substr($code,0,$pos).$helpers.substr($code,$pos);
    echo "Reporter state helpers: aplicados.\n";
}else{
    echo "Reporter state helpers: já aplicados.\n";
}

if(strpos($code,"if($action==='archive_job')")===false){
    $anchor="\n}\n$news=rpia_read('noticias.json');";
    $pos=strpos($code,$anchor);
    if($pos===false){
        fwrite(STDERR,"Reporter actions anchor não encontrado\n");
        exit(4);
    }
    $actions=<<<'PHP'

  if($action==='archive_job'){
    $idx=null; [$job,$jobs]=rpia_find_videojob($_POST['job_id']??'',$idx);
    if(!$job) $err='Item não encontrado.';
    elseif(!in_array(rpia_job_state($job),['failed','cancelled','published'],true)) $err='Somente itens concluídos, cancelados ou com falha podem ser arquivados.';
    else { $jobs[$idx]['archived_at']=date('c'); $jobs[$idx]['archived']='1'; rpia_write('videos_ia.json',$jobs); $msg='Item movido para o histórico.'; }
  }
  if($action==='release_provider_block'){
    $cfg['heygen_send_blocked']='0';
    $cfg['heygen_send_blocked_reason']='';
    $cfg['heygen_send_blocked_released_at']=date('c');
    rpia_config_save($cfg);
    $msg='Bloqueio de envio removido após regularização da franquia/API.';
  }
PHP;
    $code=substr($code,0,$pos).$actions.substr($code,$pos);
    echo "Reporter history actions: aplicadas.\n";
}else{
    echo "Reporter history actions: já aplicadas.\n";
}

if(strpos($code,'$activeJobs=array_values(array_filter($jobs')===false){
    $old="$jobs=rpia_read('videos_ia.json'); $callbackUrl=";
    $new="$jobs=rpia_read('videos_ia.json'); $activeJobs=array_values(array_filter($jobs,function($j){ return empty($j['archived']) && !in_array(rpia_job_state($j),['cancelled','failed','published'],true); })); $historyJobs=array_values(array_filter($jobs,function($j){ return !empty($j['archived']) || in_array(rpia_job_state($j),['cancelled','failed','published'],true); })); $callbackUrl=";
    $count=substr_count($code,$old);
    if($count!==1){
        fwrite(STDERR,"Reporter queue split: trecho esperado count={$count}; abortando\n");
        exit(5);
    }
    $code=str_replace($old,$new,$code);
    echo "Reporter queue split: aplicado.\n";
}else{
    echo "Reporter queue split: já aplicado.\n";
}

if(strpos($code,'Fila operacional de vídeos IA')===false){
    $start='<section class="box"><h2>Fila de vídeos IA</h2><?php foreach($jobs as $j):';
    $pos=strpos($code,$start);
    if($pos===false){
        fwrite(STDERR,"Reporter queue UI start não encontrado\n");
        exit(6);
    }
    $end='</section></div></main></div></body></html>';
    $endPos=strpos($code,$end,$pos);
    if($endPos===false){
        fwrite(STDERR,"Reporter queue UI end não encontrado\n");
        exit(7);
    }
    $newUi=<<<'HTML'
<section class="box"><div class="top"><div><h2>Fila operacional de vídeos IA</h2><p class="mini muted">Roteiro aprovado → envio → processamento → vídeo pronto → publicação. Falhas e publicados ficam no histórico.</p></div></div>
<?php if(rpia_provider_blocked($cfg)): ?><div class="notice error"><strong>Repórter IA indisponível temporariamente — franquia/API sem saldo.</strong><br>Nenhum novo vídeo será enviado até a regularização.<form method="post" style="margin-top:10px" onsubmit="return confirm('Liberar o envio somente depois de confirmar a regularização da franquia/API?')"><?=tvs_csrf_field()?><input type="hidden" name="action" value="release_provider_block"><button class="btn secondary">Liberar após regularização</button></form></div><?php endif; ?>
<?php if(!$activeJobs): ?><p class="muted">Nenhum item exige ação operacional agora.</p><?php endif; ?>
<?php foreach($activeJobs as $j): $state=rpia_job_state($j); ?><article class="job"><span class="pill <?=$state==='completed'?'ok':'warn'?>"><?=rpia_h(rpia_state_label($state))?></span><span class="pill"><?=rpia_h($j['category']??'Giro da Região')?></span><h3><?=rpia_h($j['title']??'Vídeo TV Sumaré')?></h3><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="update_script"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><textarea name="script"><?=rpia_h($j['script']??'')?></textarea><?php if($state==='ready'): ?><button class="btn secondary">Salvar e aprovar roteiro</button><?php endif; ?></form><div class="actions"><?php if($state==='ready' && !rpia_provider_blocked($cfg)): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="send_heygen"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn orange">Enviar para geração</button></form><?php elseif($state==='processing'): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="check_heygen"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn secondary">Atualizar status</button></form><?php elseif($state==='completed'): ?><?php if(!empty($j['video_url'])||!empty($j['captioned_video_url'])): ?><a class="btn secondary" target="_blank" rel="noopener" href="<?=rpia_h($j['captioned_video_url']??$j['video_url'])?>">Abrir vídeo</a><?php endif; ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="publish_video"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn">Publicar no Play</button></form><?php endif; ?></div></article><?php endforeach; ?>
<details style="margin-top:16px"><summary><strong>Histórico / falhas / publicados (<?=count($historyJobs)?>)</strong></summary><?php foreach($historyJobs as $j): $state=rpia_job_state($j); ?><article class="job"><span class="pill warn"><?=rpia_h(rpia_state_label($state))?></span><h3><?=rpia_h($j['title']??'Vídeo TV Sumaré')?></h3><?php if(empty($j['archived'])): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="archive_job"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn secondary">Arquivar</button></form><?php endif; ?></article><?php endforeach; ?></details></section>
HTML;
    $code=substr($code,0,$pos).$newUi.$end.substr($code,$endPos+strlen($end));
    echo "Reporter queue UI: aplicada.\n";
}else{
    echo "Reporter queue UI: já aplicada.\n";
}

if(file_put_contents($path,$code,LOCK_EX)===false){
    fwrite(STDERR,"Falha ao gravar Reporter IA\n");
    exit(8);
}
echo "REPORTER_QUEUE_HARDENING_APPLIED=SIM\n";
