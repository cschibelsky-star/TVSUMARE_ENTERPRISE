<?php
require_once __DIR__.'/auth.php'; require_login(); require_once __DIR__.'/monitor_lib.php';
require_once dirname(__DIR__).'/includes/radar_source_registry.php';
$activeAdmin='fontes_status';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$registered=tvs_read_json_file(dirname(__DIR__).'/data/fontes.json');
if(!is_array($registered)) $registered=[];
$health=tvs_read_json_file(dirname(__DIR__).'/data/radar_source_health.json');
if(!is_array($health)) $health=[];
$cities=['Sumaré','Hortolândia','Paulínia','Nova Odessa','Americana','Campinas'];
$rows=[];$totalFound=0;$totalOk=0;$inactive=0;
foreach($cities as $city){
  $fontes=tvs_radar_merge_source_registry($registered,tvs_radar_curated_sources_for_city($city),$city);
  $snapshots=(array)($health[$city]['sources']??[]);
  foreach($fontes as $src){
    $name=(string)($src['name']??'Fonte');
    $metric=null;
    foreach($snapshots as $snapshot){
      if((string)($snapshot['name']??'')===$name){$metric=$snapshot;break;}
    }
    $active=!isset($src['active']) || !empty($src['active']);
    $count=$metric!==null?(int)($metric['read']??0):0;
    $ok=$active && $metric!==null && $count>0;
    if(!$active) $inactive++;
    if($ok) $totalOk++;
    $totalFound+=$count;
    $rows[]=[
      'source'=>$src,'status'=>!$active?'Inativa':($metric===null?'Sem medição':($ok?'Com itens':'Sem itens')),
      'count'=>$count,'ok'=>$ok,
      'error'=>$metric===null?'Aguardando próximo ciclo de coleta.':($ok?'':'Fonte não retornou itens no último ciclo.'),
      'metric'=>$metric
    ];
  }
}
$fontesCount=count($rows);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saúde das Fontes</title><link rel="stylesheet" href="admin.css?v=2.0.2"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Operação Editorial</span><h1>Saúde das Fontes</h1><p class="muted">Último rendimento por fonte e cidade, atualizado pelo coletor automático. Esta página não dispara consultas externas.</p></div><div class="actions"><a class="btn orange" href="rss-central.php">Central RSS</a><a class="btn secondary" href="radar-regional.php">Aprovações</a></div></div>
<div class="admin-kpi-grid"><div class="admin-kpi"><span>Monitoradas</span><strong><?=$fontesCount?></strong></div><div class="admin-kpi"><span>Com itens</span><strong><?=$totalOk?></strong></div><div class="admin-kpi"><span>Itens captados no último ciclo</span><strong><?=$totalFound?></strong></div><div class="admin-kpi"><span>Inativas</span><strong><?=$inactive?></strong></div></div>
<section class="source-health-grid">
<?php foreach($rows as $r): $f=$r['source']; $badge=$r['ok']?'ok':(($r['status']==='Inativa')?'warn':'bad'); ?>
<article class="source-card"><h3><?=h($f['name']??'Fonte')?></h3><small><?=h($f['city']??'Região')?> • <?=h($f['type']??'Fonte')?></small><br><span class="badge <?=$badge?>"><?=h($r['status'])?> • <?=intval($r['count'])?> itens</span><?php if(!$r['ok']): ?><p class="muted"><?=h($r['error'])?></p><?php endif; ?><?php if($r['metric']!==null): ?><p class="muted">Cidade no texto: <?=intval($r['metric']['city_mentions']??0)?> · Com data: <?=intval($r['metric']['dated']??0)?> · Links Google: <?=intval($r['metric']['google_wrappers']??0)?></p><small>Verificada em <?=h($r['metric']['checked_at']??'')?></small><?php endif; ?></article>
<?php endforeach; ?>
</section></main></div></body></html>
