<?php
require_once __DIR__.'/auth.php'; require_login(); require_once __DIR__.'/monitor_lib.php';
$activeAdmin='fontes_status';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$fontes=tvs_read_json_file(dirname(__DIR__).'/data/fontes.json');
$rows=[];$totalFound=0;$totalOk=0;$inactive=0;
foreach($fontes as $src){
  if(isset($src['active']) && !$src['active']){ $inactive++; $rows[]=['source'=>$src,'status'=>'Inativa','count'=>0,'ok'=>false,'error'=>'Fonte desativada','items'=>[]]; continue; }
  $items=[];$error='';$ok=false;
  try{$items=tvs_capture_source_items($src);$ok=count($items)>0;$error=$ok?'':'Nenhum item encontrado.';}catch(Throwable $e){$error=$e->getMessage();}
  $totalFound+=count($items); if($ok)$totalOk++;
  $rows[]=['source'=>$src,'status'=>$ok?'Online':'Atenção','count'=>count($items),'ok'=>$ok,'error'=>$error,'items'=>array_slice($items,0,2)];
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saúde das Fontes</title><link rel="stylesheet" href="admin.css?v=2.0.2"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Operação Editorial</span><h1>Saúde das Fontes</h1><p class="muted">Visão compacta das fontes monitoradas, sem páginas gigantes e sem espaços vazios.</p></div><div class="actions"><a class="btn orange" href="rss-central.php">Central RSS</a><a class="btn secondary" href="radar-regional.php">Aprovações</a></div></div>
<div class="admin-kpi-grid"><div class="admin-kpi"><span>Cadastradas</span><strong><?=count($fontes)?></strong></div><div class="admin-kpi"><span>Online</span><strong><?=$totalOk?></strong></div><div class="admin-kpi"><span>Itens lidos</span><strong><?=$totalFound?></strong></div><div class="admin-kpi"><span>Inativas</span><strong><?=$inactive?></strong></div></div>
<section class="source-health-grid">
<?php foreach($rows as $r): $f=$r['source']; $badge=$r['ok']?'ok':(($r['status']==='Inativa')?'warn':'bad'); ?>
<article class="source-card"><h3><?=h($f['name']??'Fonte')?></h3><small><?=h($f['city']??'Região')?> • <?=h($f['type']??'Fonte')?></small><br><span class="badge <?=$badge?>"><?=h($r['status'])?> • <?=intval($r['count'])?> itens</span><?php if(!$r['ok']): ?><p class="muted"><?=h($r['error'])?></p><?php endif; ?><?php if($r['items']): ?><ul><?php foreach($r['items'] as $it): ?><li><?=h($it['title']??'')?></li><?php endforeach; ?></ul><?php endif; ?></article>
<?php endforeach; ?>
</section></main></div></body></html>
