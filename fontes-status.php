<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';
$activeAdmin='fontes_status';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$fontes=tvs_read_json_file(dirname(__DIR__).'/data/fontes.json');
$rows=[];
$totalFound=0; $totalOk=0;
foreach($fontes as $src){
  if(isset($src['active']) && !$src['active']){
    $rows[]=['source'=>$src,'status'=>'Inativa','count'=>0,'ok'=>false,'error'=>'Fonte desativada','items'=>[]];
    continue;
  }
  $items=[]; $error=''; $ok=false;
  try{
    $items=tvs_capture_source_items($src);
    $ok=count($items)>0;
    $error=$ok?'':'Nenhum item encontrado. Verifique RSS/URL ou se o site bloqueia leitura automática.';
  }catch(Throwable $e){ $error=$e->getMessage(); }
  $totalFound+=count($items); if($ok) $totalOk++;
  $rows[]=['source'=>$src,'status'=>$ok?'Online':'Atenção','count'=>count($items),'ok'=>$ok,'error'=>$error,'items'=>array_slice($items,0,5)];
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saúde das Fontes</title><link rel="stylesheet" href="admin.css?v=91"><style>.ok{color:#15803d;font-weight:800}.bad{color:#b42318;font-weight:800}.warn{color:#b45309;font-weight:800}.preview{margin:6px 0 0 18px;color:#475569}.cardsx{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:14px 0}.cardsx div{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px}.cardsx b{font-size:28px;display:block}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><h1>📡 Saúde das Fontes</h1><p class="muted">Diagnóstico das fontes que abastecem RSS, Radar e Última Hora. Use esta tela para descobrir por que uma cidade está ficando sem pautas.</p><div class="cardsx"><div><span>Fontes cadastradas</span><b><?=count($fontes)?></b></div><div><span>Fontes online</span><b><?=$totalOk?></b></div><div><span>Itens encontrados</span><b><?=$totalFound?></b></div></div><div class="actions"><a class="btn" href="rss-central.php">Central RSS</a><a class="btn" href="radar-regional.php">Centro de Redação</a><a class="btn" href="log-editorial.php">Log Editorial</a></div><section class="card"><h2>Fontes monitoradas</h2><table><thead><tr><th>Fonte</th><th>Cidade</th><th>Tipo</th><th>Status</th><th>Itens</th><th>Prévia / erro</th></tr></thead><tbody><?php foreach($rows as $r): $f=$r['source']; ?><tr><td><strong><?=h($f['name']??'Fonte')?></strong><br><small><?=h(($f['rss']??'') ?: ($f['url']??''))?></small></td><td><?=h($f['city']??'Região')?></td><td><?=h($f['type']??'Fonte')?></td><td><?php if($r['ok']): ?><span class="ok">Online</span><?php elseif(($r['status']??'')==='Inativa'): ?><span class="warn">Inativa</span><?php else: ?><span class="bad">Atenção</span><?php endif; ?><br><small><?=h($r['error']??'')?></small></td><td><?=intval($r['count'])?></td><td><?php if($r['items']): ?><ul class="preview"><?php foreach($r['items'] as $it): ?><li><?=h($it['title']??'')?></li><?php endforeach; ?></ul><?php else: ?><span class="muted">Sem prévia.</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section></main></div></body></html>