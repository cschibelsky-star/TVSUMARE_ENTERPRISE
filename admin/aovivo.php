<?php
require_once __DIR__.'/auth.php';
require_login();
$activeAdmin='aovivo';
$dataFile=dirname(__DIR__).'/data/site_settings.json';
function tvs_live_admin_json($file){ if(!file_exists($file)) return []; $d=json_decode(file_get_contents($file),true); return is_array($d)?$d:[]; }
$settings=tvs_live_admin_json($dataFile);
$msg='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  $settings['live_title']=trim($_POST['live_title']??'TV Sumaré Ao Vivo');
  $settings['live_description']=trim($_POST['live_description']??'Acompanhe boletins, entrevistas e transmissões especiais da TV Sumaré.');
  $settings['live_status']=($_POST['live_status']??'offline')==='online'?'online':'offline';
  $settings['live_next']=trim($_POST['live_next']??'Programação ao vivo em breve');
  $settings['youtube_url']=trim($_POST['youtube_url']??'');
  $settings['live_embed']=trim($_POST['live_embed']??'');
  if(!is_dir(dirname($dataFile))) mkdir(dirname($dataFile),0755,true);
  file_put_contents($dataFile,json_encode($settings,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  $msg='Configuração do Ao Vivo salva.';
}
function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ao Vivo | Admin TV Sumaré</title><link rel="stylesheet" href="admin.css"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">TV Digital</span><h1>Ao Vivo</h1><p class="muted" style="text-align:left">Configure o player público de transmissão ao vivo.</p></div><a class="btn secondary" href="../aovivo.php" target="_blank">Ver página pública</a></div><?php if($msg): ?><div class="ok" style="margin:12px 0;padding:12px;border-radius:12px;background:#dcfce7;color:#166534;font-weight:800"><?=h($msg)?></div><?php endif; ?><form class="box" method="post"><?=tvs_csrf_field()?><label>Título</label><input name="live_title" value="<?=h($settings['live_title']??'TV Sumaré Ao Vivo')?>"><label>Descrição</label><textarea name="live_description" rows="3"><?=h($settings['live_description']??'Acompanhe boletins, entrevistas e transmissões especiais da TV Sumaré.')?></textarea><label>Status</label><select name="live_status"><option value="offline" <?=($settings['live_status']??'offline')!=='online'?'selected':''?>>Offline / sem transmissão</option><option value="online" <?=($settings['live_status']??'')==='online'?'selected':''?>>Online / ao vivo agora</option></select><label>Mensagem próxima transmissão</label><input name="live_next" value="<?=h($settings['live_next']??'Programação ao vivo em breve')?>"><label>URL YouTube ou canal</label><input name="youtube_url" value="<?=h($settings['youtube_url']??'')?>"><label>Embed iframe YouTube</label><textarea name="live_embed" rows="5" placeholder="Cole aqui o iframe do YouTube, se houver."><?=h($settings['live_embed']??'')?></textarea><button class="btn orange" type="submit">Salvar Ao Vivo</button></form></main></div></body></html>
