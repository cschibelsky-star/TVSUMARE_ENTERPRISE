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
  header('Location: tvplay.php?yt_err='.rawurlencode('Retorno OAuth incompleto: código ou estado ausente.'));
  exit;
}

if(!function_exists('tvs_youtube_oauth_exchange')){
  header('Location: tvplay.php?yt_err='.rawurlencode('Integração OAuth do YouTube indisponível.'));
  exit;
}

$result=tvs_youtube_oauth_exchange($code,$state);
if(empty($result['ok'])){
  header('Location: tvplay.php?yt_err='.rawurlencode((string)($result['error']??'Falha ao autorizar o canal do YouTube.')));
  exit;
}

$message=!empty($result['has_refresh_token'])
  ? 'Canal do YouTube autorizado com sucesso.'
  : 'Canal do YouTube autorizado. O Google não retornou refresh token; reconecte com consentimento se a sessão expirar.';

header('Location: tvplay.php?yt_msg='.rawurlencode($message));
exit;
