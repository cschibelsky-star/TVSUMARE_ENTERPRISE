<?php
require_once __DIR__.'/auth.php';
require_login();
$activeAdmin='dashboard';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function j($file){ $p=dirname(__DIR__).'/data/'.$file; $a=json_decode(@file_get_contents($p),true); return is_array($a)?$a:[]; }
function dashboard_validity_alerts($news){ $out=[]; foreach($news as $n){ $ts=0; foreach(['published_at','created_at','date'] as $k){if(!empty($n[$k])&&($x=strtotime((string)$n[$k]))){$ts=$x;break;}} $age=$ts?max(0,(int)floor((time()-$ts)/86400)):null; $txt=function_exists('mb_strtolower')?mb_strtolower(strip_tags((string)(($n['category']??'').' '.($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??''))),'UTF-8'):strtolower(strip_tags((string)($n['title']??''))); $sensitive=preg_match('~\b(vagas?|empregos?|processo seletivo|concurso|inscri[cç][oõ]es|edital|evento|agenda|programa[cç][aã]o|interdi[cç][aã]o|tr[aâ]nsito|vacina[cç][aã]o|campanha|prazo|atendimento|curso|matr[ií]cula|feira|show|festival)\b~iu',$txt)===1; $limit=$sensitive?7:30; $checked=strtotime((string)($n['validity_checked_at']??'')); if($checked&&(time()-$checked)<($limit*86400))continue; if($age===null||$age>=$limit||($n['validity_status']??'')==='revisao_solicitada')$out[]=$n; } return $out; }
$news=j('noticias.json'); $drafts=j('materias_aprovacao.json'); $discard=j('pautas_descartadas.json'); $videos=j('videos.json'); $via=j('videos_ia.json'); $fontes=j('fontes.json'); $validityAlerts=dashboard_validity_alerts($news);
$radarNotification=j('radar_notification.json');
$radarAlertActive=!empty($radarNotification['date']) && ($radarNotification['date']===date('Y-m-d')) && ((int)($radarNotification['new_today']??0)>0);
$whatsappStatus=(string)($radarNotification['whatsapp_status']??'aguardando_configuracao');
$today=date('Y-m-d'); $publishedToday=0;
foreach($news as $n){ $d=substr((string)($n['published_at']??$n['created_at']??$n['date']??''),0,10); if($d===$today) $publishedToday++; }
$geminiOk = !empty($GLOBALS['gemini_api_key'] ?? getenv('GEMINI_API_KEY'));
$rep=j('reporter_ia_config.json'); $heygenOk = !empty($rep['heygen_api_key'] ?? getenv('HEYGEN_API_KEY'));
$activeSources=0; foreach($fontes as $f){ if(!isset($f['active']) || $f['active']) $activeSources++; }
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | TV Sumaré Enterprise</title><link rel="stylesheet" href="admin.css?v=2.0.3"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main">
<div class="top"><div><span class="eyebrow">TVSUMARE_ENTERPRISE_2.0</span><h1>Dashboard Executivo</h1><p class="muted">Redação, IA, vídeos, fontes e operação comercial em uma visão única. Tecnologia by Vitrine AI Pro.</p></div><div class="actions"><a class="btn orange" href="radar-regional.php">Aprovações</a><a class="btn secondary" href="../index.php" target="_blank">Ver Portal</a></div></div>
<?php if($radarAlertActive): ?><div class="notice"><strong>Radar atualizado hoje: <?=h($radarNotification['new_today']??0)?> nova(s) matéria(s).</strong> <?=h($radarNotification['publishable']??0)?> publicável(is), <?=h($radarNotification['review']??0)?> para revisão editorial e <?=h($radarNotification['image_review']??0)?> para revisão de imagem. <a href="radar-regional.php">Abrir matérias para aprovação</a></div><?php endif; ?>
<?php if($validityAlerts): ?><div class="notice error"><strong><?=count($validityAlerts)?> matéria(s) precisam de checagem de validade.</strong> <a href="content-validity.php">Abrir Validade Editorial</a></div><?php endif; ?>
<div class="admin-kpi-grid">
  <div class="admin-kpi"><span>Publicadas hoje</span><strong><?=$publishedToday?></strong></div>
  <div class="admin-kpi"><span>Em aprovação</span><strong><?=count($drafts)?></strong></div>
  <div class="admin-kpi"><span>Checar validade</span><strong><?=count($validityAlerts)?></strong></div>
  <div class="admin-kpi"><span>Descartadas</span><strong><?=count($discard)?></strong></div>
  <div class="admin-kpi"><span>Fontes ativas</span><strong><?=$activeSources?></strong></div>
  <div class="admin-kpi"><span>Vídeos publicados</span><strong><?=count($videos)?></strong></div>
  <div class="admin-kpi"><span>Fila TV Play IA</span><strong><?=count($via)?></strong></div>
  <div class="admin-kpi"><span>IA editorial</span><strong><?=$geminiOk?'OK':'OFF'?></strong></div>
  <div class="admin-kpi"><span>HeyGen</span><strong><?=$heygenOk?'OK':'OFF'?></strong></div>
  <div class="admin-kpi"><span>WhatsApp editorial</span><strong><?=h($whatsappStatus==='enviado'?'ENVIADO':($whatsappStatus==='falha'?'FALHA':'AGUARDANDO'))?></strong></div>
</div>
<section class="grid2"><div class="box"><h2>Operação editorial</h2><p class="muted">Use esta rotina para manter o portal limpo, sem notícias antigas, sem duplicidade e com descarte regional aplicado.</p><div class="actions"><a class="btn orange" href="radar-regional.php">Aprovações</a><a class="btn" href="content-validity.php">Validade Editorial</a><a class="btn" href="fontes-status.php">Saúde das Fontes</a><a class="btn secondary" href="log-editorial.php">Log Editorial</a></div></div><div class="box"><h2>Fluxo Enterprise</h2><p><b>Região oficial:</b> Sumaré, Hortolândia, Paulínia, Nova Odessa, Americana e Campinas.</p><p class="muted">Conteúdo fora da região, duplicado ou antigo deve ser descartado antes de chegar à Home. Conteúdo publicado que envelhece entra em checagem, sem remoção automática.</p></div></section>
<section class="grid2"><div class="box"><h2>IA e vídeos</h2><p class="muted">A IA editorial está integrada aos fluxos de redação; use os módulos operacionais abaixo para produzir e revisar conteúdo.</p><div class="actions"><a class="btn" href="editor-ia.php">Editor IA</a><a class="btn" href="reporter-ia.php">Repórter IA</a><a class="btn" href="boletim-ia.php">Boletim IA</a><a class="btn" href="tvplay.php">TV Play IA</a></div></div><div class="box"><h2>Comercial</h2><p class="muted">Guia Comercial, banners, monetização e CTA “Anuncie Aqui” permanecem como pilares de venda da TV Digital Enterprise.</p><div class="actions"><a class="btn secondary" href="guia-comercial.php">Guia Comercial</a><a class="btn secondary" href="monetizacao.php">Monetização</a></div></div></section>
</main></div></body></html>
