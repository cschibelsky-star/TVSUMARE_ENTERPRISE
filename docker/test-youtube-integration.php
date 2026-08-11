<?php
$root='/var/www/html';
$files=[
  $root.'/includes/youtube_helper.php',
  $root.'/includes/youtube_oauth.php',
  $root.'/includes/video_branding.php',
  $root.'/admin/youtube-oauth-callback.php',
  $root.'/admin/tvplay.php',
];
foreach($files as $f){ if(!is_file($f)){fwrite(STDERR,"YOUTUBE_SMOKE missing={$f}\n");exit(1);} }
require_once $root.'/includes/outbound_guard.php';
require_once $root.'/includes/youtube_oauth.php';
$requiredHosts=['www.youtube.com','accounts.google.com','oauth2.googleapis.com','www.googleapis.com'];
$allowed=tvs_outbound_allowed_hosts();
foreach($requiredHosts as $host){ if(!in_array($host,$allowed,true)){fwrite(STDERR,"YOUTUBE_SMOKE blocked_host={$host}\n");exit(2);} }
if(tvs_youtube_redirect_uri()===''){fwrite(STDERR,"YOUTUBE_SMOKE redirect_uri_empty\n");exit(3);}
$tvplay=(string)file_get_contents($root.'/admin/tvplay.php');
foreach(['publish_youtube','tvs_youtube_oauth_connected','Publicar no YouTube'] as $needle){ if(strpos($tvplay,$needle)===false){fwrite(STDERR,"YOUTUBE_SMOKE tvplay_missing={$needle}\n");exit(4);} }
if(!function_exists('tvs_youtube_upload_branded')){fwrite(STDERR,"YOUTUBE_SMOKE upload_function_missing\n");exit(5);}
echo "YOUTUBE_INTEGRATION_SMOKE=PASS\n";
