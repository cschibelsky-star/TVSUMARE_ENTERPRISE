<?php
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/includes/heygen_helper.php';
require_once (is_file(__DIR__.'/includes/outbound_guard.php') ? __DIR__.'/includes/outbound_guard.php' : dirname(__DIR__).'/includes/outbound_guard.php');
$activeAdmin='status';

function hgd_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function hgd_auth_test($key){
  if(trim((string)$key)==='') return ['ok'=>false,'http'=>0,'error'=>'Chave HeyGen não configurada.'];
  if(!function_exists('curl_init')) return ['ok'=>false,'http'=>0,'error'=>'cURL não está habilitado no servidor.'];
  $url='https://api.heygen.com/v3/users/me';
  $opts=tvs_outbound_curl_options($url,20);
  if($opts===null) return ['ok'=>false,'http'=>0,'error'=>'URL HeyGen bloqueada pela política de saída.'];
  $ch=curl_init($url);
  curl_setopt_array($ch,$opts+[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPGET=>true,
    CURLOPT_HTTPHEADER=>['X-Api-Key: '.$key,'Accept: application/json']
  ]);
  $res=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($res===false || $res==='') return ['ok'=>false,'http'=>$http,'error'=>'HeyGen sem resposta: '.$err];
  $json=json_decode($res,true);
  if($http>=400) return ['ok'=>false,'http'=>$http,'error'=>'HeyGen HTTP '.$http,'data'=>$json?:['raw'=>substr($res,0,900)]];
  return ['ok'=>true,'http'=>$http,'data'=>is_array($json)?$json:[]];
}

$cfg=tvs_heygen_repair_config([]);
$diag=tvs_heygen_diagnostics($cfg);
$authResult=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $authResult=hgd_auth_test($cfg['heygen_api_key']??'');
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico HeyGen | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=16"><style>.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px;margin:12px 0}.ok{color:#166534;font-weight:900}.bad{color:#b91c1c;font-weight:900}.small{color:#64748b;font-size:13px}.code{font-family:monospace;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px;white-space:pre-wrap;word-break:break-word}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">HeyGen</span><h1>Diagnóstico e autenticação</h1><p class="muted" style="text-align:left">Valida a configuração local e testa a API Key diretamente em <code>GET /v3/users/me</code>, sem gerar vídeo.</p></div><a class="btn secondary" href="status.php">Voltar ao Status</a></div><section class="box"><h2>Configuração detectada</h2><p><strong>Chave:</strong> <?=hgd_h($diag['key_masked'])?></p><p><strong>Avatar:</strong> <span class="<?=$diag['avatar_configured']?'ok':'bad'?>"><?=$diag['avatar_configured']?'configurado':'não configurado'?></span></p><p><strong>Voz:</strong> <span class="<?=$diag['voice_configured']?'ok':'bad'?>"><?=$diag['voice_configured']?'configurada':'não configurada'?></span></p><p class="small"><strong>Raiz detectada:</strong> <?=hgd_h($diag['root'])?></p><?php foreach($diag['paths'] as $p): ?><div class="card"><strong><?=hgd_h($p['path'])?></strong><br>Existe: <?=$p['exists']?'sim':'não'?> • Gravável: <?=$p['writable']?'sim':'não'?> • Chave no JSON: <?=$p['has_key']?'sim':'não'?></div><?php endforeach; ?><form method="post" style="margin-top:14px"><?=tvs_csrf_field()?><button class="btn orange" type="submit">Testar autenticação oficial HeyGen</button></form></section><?php if($authResult!==null): ?><section class="box" style="margin-top:14px"><?php if($authResult['ok']): ?><h2 class="ok">HeyGen conectada ✅</h2><p>HTTP <?=hgd_h($authResult['http'])?>. A chave foi aceita por <code>/v3/users/me</code>.</p><div class="code"><?=hgd_h(json_encode($authResult['data'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></div><?php else: ?><h2 class="bad">HeyGen não autorizada ❌</h2><p>HTTP <?=hgd_h($authResult['http'])?> — <?=hgd_h($authResult['error']??'Falha de autenticação.')?></p><?php if(!empty($authResult['data'])): ?><div class="code"><?=hgd_h(json_encode($authResult['data'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></div><?php endif; ?><p>Se retornar 401, copie ou regenere a API Key na HeyGen e salve novamente no Repórter IA antes de repetir o teste.</p><?php endif; ?></section><?php endif; ?><p style="margin-top:16px"><a class="btn secondary" href="reporter-ia.php">Abrir Repórter IA</a> <a class="btn secondary" href="boletim-ia.php">Abrir Boletim IA</a></p></main></div></body></html>
