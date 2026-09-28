<?php
require_once __DIR__.'/auth.php';
require_login();
$activeAdmin='metricas';
include dirname(__DIR__).'/config.php';

$root=dirname(__DIR__);
$metricsFile=$root.'/data/access_metrics.json';
$newsFile=$root.'/data/noticias.json';
$metrics=[];
if(file_exists($metricsFile)){ $tmp=json_decode(file_get_contents($metricsFile),true); if(is_array($tmp)) $metrics=$tmp; }
$news=[];
if(file_exists($newsFile)){ $tmp=json_decode(file_get_contents($newsFile),true); if(is_array($tmp)) $news=$tmp; }

$pageviews=(int)($metrics['pageviews_total']??0);
$visits=(int)($metrics['visits_total']??0);
$today=date('Y-m-d');
$todayViews=(int)($metrics['days'][$today]['pageviews']??0);
$todayVisits=(int)($metrics['days'][$today]['visits']??0);
$last7Views=0; $last7Visits=0; $last30Views=0; $last30Visits=0; $daily=[];
for($i=29;$i>=0;$i--){
  $d=date('Y-m-d',strtotime("-$i days"));
  $v=(int)($metrics['days'][$d]['pageviews']??0);
  $s=(int)($metrics['days'][$d]['visits']??0);
  $daily[]=['date'=>$d,'pageviews'=>$v,'visits'=>$s];
  $last30Views+=$v; $last30Visits+=$s;
  if($i<=6){ $last7Views+=$v; $last7Visits+=$s; }
}
$pages=$metrics['pages']??[];
uasort($pages,fn($a,$b)=>(int)($b['pageviews']??0)<=>(int)($a['pageviews']??0));
$pages=array_slice($pages,0,12,true);
usort($news,fn($a,$b)=>(int)($b['views']??0)<=>(int)($a['views']??0));
$topNews=array_slice($news,0,10);
function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function page_label($p){ $map=['index.php'=>'Início','noticias.php'=>'Notícias','videos.php'=>'Vídeos','aovivo.php'=>'Ao Vivo','guia.php'=>'Guia Comercial','colunas.php'=>'Colunas','anuncie.php'=>'Anuncie','noticia.php'=>'Matérias']; return $map[$p]??$p; }
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Métricas de Acesso | Admin TV Sumaré</title><link rel="stylesheet" href="admin.css"><style>.metrics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}.metric{background:#fff;border:1px solid #e6edf8;border-radius:18px;padding:18px;box-shadow:0 10px 25px rgba(15,47,104,.06)}.metric small{color:#64748b;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.metric strong{display:block;font-size:30px;color:#0f2f68;margin-top:6px}.box table{width:100%;border-collapse:collapse}.box th,.box td{padding:11px;border-bottom:1px solid #e6edf8;text-align:left}.box th{font-size:12px;text-transform:uppercase;color:#64748b}.bar{height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;min-width:90px}.bar span{display:block;height:100%;background:#0f2f68;border-radius:999px}.muted2{color:#64748b;font-size:13px}.charts{display:grid;grid-template-columns:repeat(30,1fr);gap:4px;align-items:end;height:140px;padding-top:12px}.charts span{background:#0f2f68;border-radius:5px 5px 0 0;min-height:2px}.chart-labels{display:flex;justify-content:space-between;color:#64748b;font-size:11px;margin-top:7px}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Audiência</span><h1>Métricas de Acesso</h1><p class="muted" style="text-align:left">Medição própria da TV Sumaré: visualizações e visitas por sessão, sem armazenar IP, nome ou e-mail.</p></div><a class="btn secondary" href="../index.php" target="_blank" rel="noopener">Ver site</a></div>
<section class="metrics-grid"><article class="metric"><small>Visualizações hoje</small><strong><?=$todayViews?></strong></article><article class="metric"><small>Visitas hoje</small><strong><?=$todayVisits?></strong></article><article class="metric"><small>Visualizações 7 dias</small><strong><?=$last7Views?></strong></article><article class="metric"><small>Visitas 7 dias</small><strong><?=$last7Visits?></strong></article><article class="metric"><small>Visualizações 30 dias</small><strong><?=$last30Views?></strong></article><article class="metric"><small>Total de visualizações</small><strong><?=$pageviews?></strong></article></section>
<section class="box" style="margin-top:18px"><h2>Visualizações nos últimos 30 dias</h2><?php $max=max(1,...array_column($daily,'pageviews')); ?><div class="charts"><?php foreach($daily as $d): $height=max(2,(int)round(($d['pageviews']/$max)*130)); ?><span title="<?=h(date('d/m',strtotime($d['date'])).' — '.$d['pageviews'].' visualizações / '.$d['visits'].' visitas')?>" style="height:<?=$height?>px"></span><?php endforeach; ?></div><div class="chart-labels"><span><?=h(date('d/m',strtotime($daily[0]['date']??$today)))?></span><span><?=h(date('d/m'))?></span></div></section>
<section class="box" style="margin-top:18px"><h2>Páginas mais acessadas</h2><table><thead><tr><th>Página</th><th>Visualizações</th><th>Participação</th></tr></thead><tbody><?php foreach($pages as $p=>$row): $pv=(int)($row['pageviews']??0); $pct=$pageviews>0?round(($pv/$pageviews)*100,1):0; ?><tr><td><?=h(page_label($p))?></td><td><strong><?=$pv?></strong></td><td><div class="bar"><span style="width:<?=min(100,$pct)?>%"></span></div><span class="muted2"><?=$pct?>%</span></td></tr><?php endforeach; ?></tbody></table></section>
<section class="box" style="margin-top:18px"><h2>Matérias mais lidas</h2><p class="muted" style="text-align:left">As matérias já tinham contador próprio; este ranking aproveita o histórico existente.</p><table><thead><tr><th>Matéria</th><th>Cidade</th><th>Visualizações</th></tr></thead><tbody><?php foreach($topNews as $n): ?><tr><td><a href="../noticia.php?id=<?=urlencode((string)($n['id']??''))?>" target="_blank" rel="noopener"><?=h($n['title']??'Sem título')?></a></td><td><?=h($n['city']??'Região')?></td><td><strong><?=(int)($n['views']??0)?></strong></td></tr><?php endforeach; ?></tbody></table></section>
<section class="box" style="margin-top:18px"><h2>Como a contagem funciona</h2><p class="muted" style="text-align:left">Cada carregamento de página pública conta uma visualização. Uma visita nova é contabilizada quando não existe sessão de visita ativa ou após 30 minutos de inatividade. Robôs e pré-visualizadores conhecidos são ignorados. Nenhum endereço IP é armazenado.</p></section></main></div></body></html>