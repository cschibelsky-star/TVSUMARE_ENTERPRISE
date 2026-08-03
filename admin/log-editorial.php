<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';
$activeAdmin='log_editorial';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$log=tvs_read_json_file(dirname(__DIR__).'/data/radar_log.json');
$discard=tvs_read_json_file(dirname(__DIR__).'/data/pautas_descartadas.json');
if(!is_array($log)) $log=[]; if(!is_array($discard)) $discard=[];
$combined=$log;
foreach($discard as $d){
  $combined[]=['title'=>$d['title']??'','source'=>$d['source']??'Fonte','city'=>$d['city']??'Região','status'=>'DESCARTADA','reason'=>$d['reason']??'Descartada','url'=>$d['url']??'','created_at'=>$d['created_at']??''];
}
usort($combined,function($a,$b){return strcmp((string)($b['created_at']??''),(string)($a['created_at']??''));});
$combined=array_slice($combined,0,250);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Log Editorial</title><link rel="stylesheet" href="admin.css?v=91"><style>.tag{display:inline-block;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:800;background:#eef2ff;color:#1d4ed8}.tag.desc{background:#fee2e2;color:#991b1b}.tag.guia{background:#ecfccb;color:#365314}.tag.rev{background:#fff7ed;color:#9a3412}.tag.pub{background:#dcfce7;color:#166534}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><h1>📊 Log Editorial do Radar</h1><p class="muted">Histórico das decisões do Radar: o que entrou na redação, o que foi para revisão, o que virou Guia Comercial e o que foi descartado.</p><div class="actions"><a class="btn" href="radar-regional.php">Centro de Redação</a><a class="btn" href="fontes-status.php">Saúde das Fontes</a></div><section class="card"><table><thead><tr><th>Data</th><th>Status</th><th>Cidade</th><th>Fonte</th><th>Título</th><th>Motivo</th></tr></thead><tbody><?php if(!$combined): ?><tr><td colspan="6" class="muted">Ainda não há registros. Rode o Radar para gerar o primeiro log.</td></tr><?php endif; ?><?php foreach($combined as $r): $st=strtoupper((string)($r['status']??'')); $cls=strpos($st,'DESC')!==false?'desc':(strpos($st,'GUIA')!==false?'guia':(strpos($st,'REV')!==false?'rev':'pub')); ?><tr><td><?=h(!empty($r['created_at'])?date('d/m H:i',strtotime($r['created_at'])):'')?></td><td><span class="tag <?=$cls?>"><?=h($st?:'REGISTRO')?></span></td><td><?=h($r['city']??'Região')?></td><td><?=h($r['source']??'Fonte')?></td><td><?php if(!empty($r['url'])): ?><a href="<?=h($r['url'])?>" target="_blank" rel="noopener"><?=h($r['title']??'Sem título')?></a><?php else: ?><?=h($r['title']??'Sem título')?><?php endif; ?></td><td><?=h($r['reason']??'')?></td></tr><?php endforeach; ?></tbody></table></section></main></div></body></html>