<?php
require_once __DIR__.'/auth.php';
require_login();
$activeAdmin='dashboard';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function j($file){ $p=dirname(__DIR__).'/data/'.$file; $a=json_decode(@file_get_contents($p),true); return is_array($a)?$a:[]; }
$news=j('noticias.json'); $drafts=j('materias_aprovacao.json'); $discard=j('pautas_descartadas.json'); $videos=j('videos.json'); $via=j('videos_ia.json'); $fontes=j('fontes.json');
$today=date('Y-m-d'); $publishedToday=0;
foreach($news as $n){ $d=substr((string)($n['published_at']??$n['created_at']??$n['date']??''),0,10); if($d===$today) $publishedToday++; }
$geminiOk = !empty($GLOBALS['gemini_api_key'] ?? getenv('GEMINI_API_KEY'));
$rep=j('reporter_ia_config.json'); $heygenOk = !empty($rep['heygen_api_key'] ?? getenv('HEYGEN_API_KEY'));
$activeSources=0; foreach($fontes as $f){ if(!isset($f['active']) || $f['active']) $activeSources++; }
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | TV Sumaré Enterprise</title><link rel="stylesheet" href="admin.css?v=2.0.2"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main">
<div class="top"><div><span class="eyebrow">TVSUMARE_ENTERPRISE_2.0</span><h1>Dashboard Executivo</h1><p class="muted">Redação, IA, vídeos, fontes e operação comercial em uma visão única. Tecnologia by Vitrine AI Pro.</p></div><div class="actions"><a class="btn orange" href="radar-regional.php">Aprovações</a><a class="btn secondary" href="../index.php" target="_blank">Ver Portal</a></div></div>
<div class="admin-kpi-grid">
  <div class="admin-kpi"><span>Publicadas hoje</span><strong><?=$publishedToday?></strong></div>
  <div class="admin-kpi"><span>Em aprovação</span><strong><?=count($drafts)?></strong></div>
  <div class="admin-kpi"><span>Descartadas</span><strong><?=count($discard)?></strong></div>
  <div class="admin-kpi"><span>Fontes ativas</span><strong><?=$activeSources?></strong></div>
  <div class="admin-kpi"><span>Vídeos publicados</span><strong><?=count($videos)?></strong></div>
  <div class="admin-kpi"><span>Fila TV Play IA</span><strong><?=count($via)?></strong></div>
  <div class="admin-kpi"><span>Gemini</span><strong><?=$geminiOk?'OK':'OFF'?></strong></div>
  <div class="admin-kpi"><span>HeyGen</span><strong><?=$heygenOk?'OK':'OFF'?></strong></div>
</div>
<section class="grid2"><div class="box"><h2>Operação editorial</h2><p class="muted">Use esta rotina para manter o portal limpo, sem notícias antigas, sem duplicidade e com descarte regional aplicado.</p><div class="actions"><a class="btn orange" href="reparar-noticias-master.php">Reparar notícias</a><a class="btn" href="fontes-status.php">Saúde das Fontes</a><a class="btn secondary" href="log-editorial.php">Log Editorial</a></div></div><div class="box"><h2>Fluxo Enterprise</h2><p><b>Região oficial:</b> Sumaré, Hortolândia, Paulínia, Nova Odessa, Americana e Campinas.</p><p class="muted">Conteúdo fora da região, duplicado ou antigo deve ser descartado antes de chegar à Home.</p></div></section>
<section class="grid2"><div class="box"><h2>IA e vídeos</h2><div class="actions"><a class="btn" href="gemini.php">Gemini</a><a class="btn" href="editor-ia.php">Editor IA</a><a class="btn" href="reporter-ia.php">Repórter IA</a><a class="btn" href="tvplay.php">TV Play IA</a></div></div><div class="box"><h2>Comercial</h2><p class="muted">Guia Comercial, banners, monetização e CTA “Anuncie Aqui” permanecem como pilares de venda da TV Digital Enterprise.</p><div class="actions"><a class="btn secondary" href="guia-comercial.php">Guia Comercial</a><a class="btn secondary" href="monetizacao.php">Monetização</a></div></div></section>
</main></div></body></html>
