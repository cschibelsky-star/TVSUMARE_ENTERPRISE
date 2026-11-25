<?php
/**
 * CLI-only, read-only diagnostic. No secret or raw provider payload is printed.
 * Usage: php scripts/heygen_digital_twin_diagnostic.php (authorized runtime only)
 * Never run through the web server or commit credentials.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/includes/heygen_digital_twin_probe.php';
require_once dirname(__DIR__).'/includes/heygen_helper.php';
require_once dirname(__DIR__).'/includes/outbound_guard.php';
// Use the same configuration resolution as the Repórter IA; never print the key.
$cfg=tvs_heygen_load_config([]);
$key=$cfg['heygen_api_key']??'';
if (!is_string($key) || trim($key)==='') {
 fwrite(STDERR,"HeyGen credential unavailable; no request made\n"); exit(2);
}
if (!function_exists('curl_init')) {
 fwrite(STDERR,"PHP cURL unavailable\n"); exit(2);
}
$get=static function($path)use($key) {
 if (!in_array($path,['/v3/api_keys/self','/v3/avatars/looks'],true)) return ['ok'=>false,'http'=>0];
 $url='https://api.heygen.com'.$path;
 $outboundOptions=tvs_outbound_curl_options($url,15);
 if ($outboundOptions===null) return ['ok'=>false,'http'=>0];
 $ch=curl_init($url);
 // Do not follow redirects with the API credential attached.
 curl_setopt_array($ch,$outboundOptions+[
   CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
   CURLOPT_HTTPHEADER=>['X-Api-Key: '.$key,'Accept: application/json']
 ]);
 curl_setopt($ch,CURLOPT_FOLLOWLOCATION,false);
 $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
 curl_close($ch);
 $decoded=is_string($raw)?json_decode($raw,true):null;
 return ['ok'=>$status>=200&&$status<300&&is_array($decoded),'http'=>$status,
         'data'=>is_array($decoded)?$decoded:[]];
};
$self=$get('/v3/api_keys/self');
$info=$self['data']['data']??[];
$scopes=$info['scopes']??[];
echo json_encode([
 'key_introspection'=>['ok'=>$self['ok'],'http'=>$self['http'],
   'scope_mode'=>is_string($info['scope_mode']??null)?$info['scope_mode']:null,
   'can_read_avatars'=>is_array($scopes)?in_array('avatars:read',$scopes,true):null],
 'avatar_check'=>tvs_heygen_digital_twin_probe($get,'c68420874a454f3bbf7200a48add0288')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
