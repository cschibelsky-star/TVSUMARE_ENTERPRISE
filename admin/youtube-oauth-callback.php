<?php
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/includes/youtube_oauth.php';

$code=trim((string)($_GET['code']??''));
$state=trim((string)($_GET['state']??''));
$error=trim((string)($_GET['error']??''));

if($error!==''){
  header('Location: tvplay.php?yt_err='.rawurlencode('Autorização Google cancelada ou negada: '.$error));
  exit;
}
if($code==='' || $state===''){
  header('Location: tvplay.php?yt_err='.rawurlencode('Callback OAuth incompleto.'));
  exit;
}
$r=tvs_youtube_oauth_exchange($code,$state);
if(empty($r['ok'])){
  header('Location: tvplay.php?yt_err='.rawurlencode($r['error']??'Falha ao concluir OAuth do YouTube.'));
  exit;
}
header('Location: tvplay.php?yt_msg='.rawurlencode('Canal YouTube autorizado com sucesso.'));
exit;
