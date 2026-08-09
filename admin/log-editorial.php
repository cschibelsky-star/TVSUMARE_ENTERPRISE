<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';
$activeAdmin='log_editorial';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$base=dirname(__DIR__).'/data';
$logFile=$base.'/radar_log.json';
$discardFile=$base.'/pautas_descartadas.json';
$queueFile=$base.'/materias_aprovacao.json';
$notice=''; $error='';

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='recover'){
  $idx=(int)($_POST['discard_index']??-1);
  $discard=tvs_read_json_file($discardFile); if(!is_array($discard)) $discard=[];
  if(!isset($discard[$idx])){
    $error='Pauta descartada não encontrada. Atualize a página e tente novamente.';
  } else {
    $d=$discard[$idx];
    $queue=tvs_read_json_file($queueFile); if(!is_array($queue)) $queue=[];
    $title=trim((string)($d['title']??'')); $url=trim((string)($d['url']??''));
    $exists=false;
    foreach($queue as $q){
      if(($url!=='' && ($q['url']??'')===$url) || ($title!=='' && mb_strtolower(trim((string)($q['title']??'')),'UTF-8')===mb_strtolower($title,'UTF-8'))){ $exists=true; break; }
    }
    if($exists){
      $error='Esta pauta já está na fila de aprovação.';
    } else {
      $queue[]=[
        'id'=>uniqid('recover_'),
        'title'=>$title ?: 'Pauta recuperada',
        'source'=>$d['source']??'Fonte',
        'city'=>$d['city']??'Região',
        'category'=>$d['category']??'Cidade',
        'url'=>$url,
        'description'=>$d['description']??($d['text']??''),
        'text'=>$d['text']??($d['description']??''),
        'image'=>$d['image']??($d['image_url']??''),
        'status'=>'REVISAO_EDITORIAL',
        'editorial_mode'=>'RECUPERADA',
        'recovery_reason'=>$d['reason']??'Recuperada do Log Editorial',
        'created_at'=>date('c')
      ];
      if(tvs_save_json_file($queueFile,$queue)){
        array_splice($discard,$idx,1);
        tvs_save_json_file($discardFile,$discard);
        $log=tvs_read_json_file($logFile); if(!is_array($log)) $log=[];
        $log[]=['id'=>uniqid('log_'),'title'=>$title,'source'=>$d['source']??'Fonte','city'=>$d['city']??'Região','status'=>'RECUPERADA','reason'=>'Recuperada manualmente do descarte e enviada para revisão editorial.','url'=>$url,'created_at'=>date('c')];
        $log=array_slice($log,-500); tvs_save_json_file($logFile,$log);
        $notice='Pauta recuperada. Ela voltou para Aprovações como revisão editorial.';
      } else $error='Não foi possível gravar a pauta na fila de aprovação.';
    }
  }
}

$log=tvs_read_json_file($logFile); $discard=tvs_read_json_file($discardFile);
if(!is_array($log)) $log=[]; if(!is_array($discard)) $discard=[];
$combined=$log;
foreach($discard as $i=>$d){
  $combined[]=['title'=>$d['title']??'','source'=>$d['source']??'Fonte','city'=>$d['city']??'Região','status'=>'DESCARTADA','reason'=>$d['reason']??'Descartada','url'=>$d['url']??'','created_at'=>$d['created_at']??'','discard_index'=>$i,'recoverable'=>true,'image'=>$d['image']??($d['image_url']??'')];
}
usort($combined,function($a,$b){return strcmp((string)($b['created_at']??''),(string)($a['created_at']??''));});
$combined=array_slice($combined,0,250);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Log Editorial</title><link rel="stylesheet" href="admin.css?v=92"><style>.tag{display:inline-block;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:800;background:#eef2ff;color:#1d4ed8}.tag.desc{background:#fee2e2;color:#991b1b}.tag.guia{background:#ecfccb;color:#365314}.tag.rev{background:#fff7ed;color:#9a3412}.tag.pub{background:#dcfce7;color:#166534}.recover{margin-top:8px}.recover button{border:0;border-radius:8px;padding:7px 10px;font-weight:800;cursor:pointer;background:#fff7ed;color:#9a3412}.thumb{width:72px;height:48px;object-fit:cover;border-radius:7px;display:block;margin-top:6px}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><h1>📊 Log Editorial do Radar</h1><p class="muted">Histórico das decisões do Radar. Descarte não é mais irreversível: pautas úteis podem voltar para revisão editorial.</p><?php if($notice): ?><div class="alert success"><?=h($notice)?></div><?php endif; ?><?php if($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?><div class="actions"><a class="btn" href="radar-regional.php">Centro de Redação</a><a class="btn" href="fontes-status.php">Saúde das Fontes</a></div><section class="card"><table><thead><tr><th>Data</th><th>Status</th><th>Cidade</th><th>Fonte</th><th>Título</th><th>Motivo / ação</th></tr></thead><tbody><?php if(!$combined): ?><tr><td colspan="6" class="muted">Ainda não há registros. Rode o Radar para gerar o primeiro log.</td></tr><?php endif; ?><?php foreach($combined as $r): $st=strtoupper((string)($r['status']??'')); $cls=strpos($st,'DESC')!==false?'desc':((strpos($st,'REV')!==false||strpos($st,'RECUP')!==false)?'rev':(strpos($st,'GUIA')!==false?'guia':'pub')); ?><tr><td><?=h(!empty($r['created_at'])?date('d/m H:i',strtotime($r['created_at'])):'')?></td><td><span class="tag <?=$cls?>"><?=h($st?:'REGISTRO')?></span></td><td><?=h($r['city']??'Região')?></td><td><?=h($r['source']??'Fonte')?></td><td><?php if(!empty($r['url'])): ?><a href="<?=h($r['url'])?>" target="_blank" rel="noopener"><?=h($r['title']??'Sem título')?></a><?php else: ?><?=h($r['title']??'Sem título')?><?php endif; ?><?php if(!empty($r['image'])): ?><img class="thumb" src="<?=h($r['image'])?>" alt="Imagem da pauta"><?php endif; ?></td><td><?=h($r['reason']??'')?><?php if(!empty($r['recoverable'])): ?><form class="recover" method="post" onsubmit="return confirm('Recuperar esta pauta e enviar para revisão editorial?')"><input type="hidden" name="action" value="recover"><input type="hidden" name="discard_index" value="<?=h($r['discard_index'])?>"><button type="submit">↩ Recuperar para revisão</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section></main></div></body></html>