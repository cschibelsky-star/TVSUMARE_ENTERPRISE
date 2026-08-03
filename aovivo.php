<?php
$active='aovivo';
function tvs_live_json($file){ if(!file_exists($file)) return []; $d=json_decode(file_get_contents($file),true); return is_array($d)?$d:[]; }
function tvs_live_h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function tvs_live_embed_safe($html){
  $html=trim((string)$html);
  if($html==='' || stripos($html,'<iframe')===false) return '';
  if(preg_match('~src=["\']https://(www\.)?(youtube\.com|youtu\.be|www\.youtube-nocookie\.com)/[^"\']+["\']~i',$html)) return $html;
  return '';
}
$settings=tvs_live_json('data/site_settings.json');
$embed=tvs_live_embed_safe($settings['live_embed']??'');
$online=strtolower((string)($settings['live_status']??'offline'))==='online';
$programacao=$settings['programacao']??[];
if(!is_array($programacao) || !$programacao){ $programacao=[['hora'=>'08:00','titulo'=>'Primeiras Notícias'],['hora'=>'12:00','titulo'=>'Jornal do Meio-Dia'],['hora'=>'18:30','titulo'=>'Resumo da Cidade']]; }
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ao Vivo | TV Sumaré</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"></head><body><?php include 'header.php'; ?><main class="container page-shell"><div class="page-title"><span>Transmissão</span><h1><?=tvs_live_h($settings['live_title']??'TV Sumaré Ao Vivo')?></h1><p><?=tvs_live_h($settings['live_description']??'Acompanhe boletins, entrevistas e transmissões especiais da TV Sumaré.')?></p></div><div class="live-layout"><div class="live-player"><?php if($embed): ?><div class="live-embed-full"><?=$embed?></div><?php else: ?><div class="pulse-dot"></div><h2><?= $online?'AO VIVO AGORA':'PRÓXIMA TRANSMISSÃO' ?></h2><p><?=tvs_live_h($settings['live_next']??'Programação ao vivo em breve')?></p><a class="btn btn-primary" href="<?=tvs_live_h($settings['youtube_url']??'https://www.youtube.com/') ?>" target="_blank" rel="noopener">▶ Abrir YouTube</a><?php endif; ?></div><aside class="schedule-card"><h3>Grade de Programação</h3><?php foreach($programacao as $row): ?><div class="schedule-row"><strong><?=tvs_live_h($row['hora']??'--:--')?></strong><span><?=tvs_live_h($row['titulo']??'Programa TV Sumaré')?></span></div><?php endforeach; ?><a class="btn btn-outline" href="videos.php" style="margin-top:16px">Ver vídeos</a></aside></div></main><?php include 'rodape.php'; ?></body></html>
