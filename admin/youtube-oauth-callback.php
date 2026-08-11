<?php
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/includes/youtube_oauth.php';

$code=trim((string)($_GET['code']??''));
$state=trim((string)($_GET['state']??''));
$error=trim((string)($_GET['error']??''));

if($error!==''){
  header('Location: tvplay.php?err='.rawurlencode('Autorização Google cancelada ou negada: