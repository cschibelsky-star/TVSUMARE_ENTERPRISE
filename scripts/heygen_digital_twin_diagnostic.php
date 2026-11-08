<?php
/**
 * CLI-only, read-only diagnostic. No secret or raw provider payload is printed.
 * Usage: HEYGEN_API_KEY=<from secure runtime> php scripts/heygen_digital_twin_diagnostic.php
 * Never run through the web server or commit credentials.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/includes/heygen_digital_twin_probe.php';
$key=getenv('HEYGEN_API_KEY');
if (!is_string($key) || trim($key)==='') {
 fwrite(STDERR,"HEYGEN_API_KEY missing; no request made\n"); exit(2);
}
if (!function_exists('curl_init')) {
 fwrite(STDERR,"PHP cURL unavailable\n"); exit(2);
}
$get=static function($path)use($key) {
 if (!in_array($path,['/v3/api_keys/self','/v3/avatars/looks'],true)) return ['ok'=>false,'http'=>0];
 $ch=curl_init('https://api.heygen.com'.$path);
 curl_setopt_array($ch,[
   CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
   CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['X-Api-Key: '.$key,'Accept: application/json'],
   CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS
 ]);
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
