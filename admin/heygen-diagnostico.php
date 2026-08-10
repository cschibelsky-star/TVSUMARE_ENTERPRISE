<?php
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/includes/heygen_helper.php';
require_once (is_file(__DIR__.'/includes/outbound_guard.php') ? __DIR__.'/includes/outbound_guard.php' : dirname(__DIR__).'/includes/outbound_guard.php');
$activeAdmin='status';

function hgd_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function hgd_api_get($key,$endpoint){
  if(trim((string)$key)==='') return ['ok'=>false,'http'=>0,'error'=>'Chave HeyGen não configurada.'];
  if(!function_exists('curl_init')) return ['ok'=>false,'http'=>0,'error'=>'cURL não está habilitado no servidor.'];
  $url='https://api.heygen.com'.$endpoint;
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
  if(strlen($res)>2097152) return ['ok'=>false,'http'=>$http,'error'=>'Resposta HeyGen excedeu o limite seguro.'];
  $json=json_decode($res,true);
  if($http>=400) return ['ok'=>false,'http'=>$http,'error'=>'HeyGen HTTP '.$http,'data'=>is_array($json)?$json:[]];
  return ['ok'=>true,'http'=>$http,'data'=>is_array($json)?$json:[]];
}
function hgd_data($r){ return is_array($r['data']['data']??null)?$r['data']['data']:(is_array($r['data']??null)?$r['data']:[]); }
function hgd_find_look($payload,$avatarId){
  if($avatarId==='') return null;
  $root=hgd_data(['data'=>$payload]);
  $items=[];
  if(array_is_list($root)) $items=$root;
  elseif(isset($root['items'])&&is_array($root['items'])) $items=$root['items'];
  elseif(isset($root['avatars'])&&is_array($root['avatars'])) $items=$root['avatars'];
  elseif(isset($root['looks'])&&is_array($root['looks'])) $items=$root['looks'];
  foreach($items as $item){
    if(!is_array($item)) continue;
    foreach(['id','avatar_id','look_id'] as $k){ if((string)($item[$k]??'')===$avatarId) return $item; }
  }
  return null;
}
function hgd_engines($look){
  if(!is_array($look)) return [];
  $v=$look['supported_api_engines']??($look['supported_engines']??[]);
  if(is_string($v)) $v=[$v];
  if(!is_array($v)) return [];
  return array_values(array_unique(array_filter(array_map(fn($x)=>strtolower(trim((string)$x)),$v))));
}
function hgd_engine_recommendation($engines){
  $joined=implode(' ',array_map('strtolower',$engines));
  if(str_contains($joined,'avatar_v')||str_contains($joined,'avatarv')) return ['Avatar V','Priorizar fotorealismo. Usar motion_prompt somente quando o look/reference permitir; não enviar expressiveness incompatível.'];
  if(str_contains($joined,'avatar_iii')||str_contains($joined,'avatariii')) return ['Avatar III','Engine suportada pelo look. Validar naturalidade em vídeo antes de promovê-la a padrão.'];
  if(str_contains($joined,'avatar_iv')||str_contains($joined,'avatariv')) return ['Avatar IV','Compatível com controles avançados documentados; motion_prompt/expressiveness devem respeitar o schema atual.'];
  return ['Video Agent v3','Capabilities do look não foram identificadas com segurança. Manter Video Agent até diagnóstico completo.'];
}
function hgd_num($v){ return is_numeric($v)?number_format((float)$v,2,',','.'):'—'; }

$cfg=tvs_heygen_repair_config([]);
$diag=tvs_heygen_diagnostics($cfg);
$result=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $key=trim((string)($cfg['heygen_api_key']??''));
  $avatarId=trim((string)($cfg['heygen_avatar_id']??''));
  $voiceId=trim((string)($cfg['heygen_voice_id']??''));
  if($voiceId==='') $voiceId='cbdcad7a79e44262b4f4dad0a1b1fba9';
  $me=hgd_api_get($key,'/v3/users/me');
  $looks=hgd_api_get($key,'/v3/avatars/looks');
  $voice=$voiceId!==''?hgd_api_get($key,'/v3/voices/'.rawurlencode($voiceId)):null;
  $meData=hgd_data($me);
  $look=$looks['ok']?hgd_find_look($looks['data'],$avatarId):null;
  $engines=hgd_engines($look);
  [$engineLabel,$engineNote]=hgd_engine_recommendation($engines);
  $result=[
    'ok'=>$me['ok'], 'http'=>$me['http'], 'error'=>$me['error']??'',
    'included_credits'=>$meData['included_credits']??null,
    'remaining_credits'=>$meData['remaining_credits']??null,
    'avatar_id'=>$avatarId,
    'avatar_found'=>is_array($look),
    'avatar_name'=>$look['name']??($look['avatar_name']??($look['display_name']??'')),
    'engines'=>$engines,
    'engine_label'=>$engineLabel,
    'engine_note'=>$engineNote,
    'voice_id'=>$voiceId,
    'voice_ok'=>is_array($voice)&&!empty($voice['ok']),
    'voice_http'=>is_array($voice)?($voice['http']??0):0,
    'looks_ok'=>$looks['ok'],
    'looks_http'=>$looks['http']??0,
  ];
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico HeyGen | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=16"><style>.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px;margin:12px 0}.ok{color:#166534;font-weight:900}.bad{color:#b91c1c;font-weight:900}.warn{color:#92400e;font-weight:900}.small{color:#64748b;font-size:13px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.metric{background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:14px}.metric strong{display:block;font-size:22px;margin-top:4px}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">HeyGen</span><h1>Diagnóstico de capacidade</h1><p class="muted" style="text-align:left">Valida conta, créditos, avatar/look, engines suportadas e voz sem gerar vídeo nem consumir crédito de geração.</p></div><a class="btn secondary" href="status.php">Voltar ao Status</a></div>
<section class="box"><h2>Configuração detectada</h2><div class="grid"><div class="metric">API Key<strong class="<?=$diag['key_configured']??false?'ok':'bad'?>"><?=!empty($diag['key_masked'])?hgd_h($diag['key_masked']):'não configurada'?></strong></div><div class="metric">Avatar<strong class="<?=$diag['avatar_configured']?'ok':'bad'?>"><?=$diag['avatar_configured']?'configurado':'não configurado'?></strong></div><div class="metric">Voz<strong class="<?=$diag['voice_configured']?'ok':'warn'?>"><?=$diag['voice_configured']?'configurada':'fallback Enterprise'?></strong></div></div><p class="small">Nenhuma chave completa, token ou resposta bruta da conta é exibida nesta página.</p><form method="post" style="margin-top:14px"><?=tvs_csrf_field()?><button class="btn orange" type="submit">Executar diagnóstico de capacidade</button></form></section>
<?php if($result!==null): ?><section class="box" style="margin-top:14px"><?php if($result['ok']): ?><h2 class="ok">HeyGen conectada ✅</h2><div class="grid"><div class="metric">Créditos incluídos<strong><?=hgd_num($result['included_credits'])?></strong></div><div class="metric">Créditos restantes<strong><?=hgd_num($result['remaining_credits'])?></strong></div><div class="metric">Voice ID<strong style="font-size:13px;word-break:break-all"><?=hgd_h($result['voice_id'])?></strong><span class="<?=$result['voice_ok']?'ok':'warn'?>"><?=$result['voice_ok']?'validada pela API':'não validada'?></span></div></div><div class="card"><h3>Avatar / Look</h3><p><strong>ID:</strong> <?=hgd_h($result['avatar_id']?:'não configurado')?></p><p><strong>Localizado na biblioteca:</strong> <span class="<?=$result['avatar_found']?'ok':'warn'?>"><?=$result['avatar_found']?'sim':'não confirmado'?></span></p><?php if($result['avatar_name']!==''): ?><p><strong>Nome:</strong> <?=hgd_h($result['avatar_name'])?></p><?php endif; ?><p><strong>Engines suportadas:</strong> <?=hgd_h($result['engines']?implode(', ',$result['engines']):'não informadas pela resposta')?></p></div><div class="card"><h3>Recomendação automática</h3><p><strong><?=hgd_h($result['engine_label'])?></strong></p><p><?=hgd_h($result['engine_note'])?></p><p class="small">Essa recomendação não gera vídeo. O primeiro render real continua dependendo de homologação visual de rosto, lábios, movimentos, voz e ritmo.</p></div><?php if(!$result['looks_ok']): ?><p class="warn">A conta autenticou, mas a consulta de looks retornou HTTP <?=hgd_h($result['looks_http'])?>. Mantemos Video Agent v3 até confirmar capabilities do avatar.</p><?php endif; ?><?php else: ?><h2 class="bad">HeyGen não autorizada ❌</h2><p>HTTP <?=hgd_h($result['http'])?> — <?=hgd_h($result['error']?:'Falha de autenticação.')?></p><?php endif; ?></section><?php endif; ?><p style="margin-top:16px"><a class="btn secondary" href="reporter-ia.php">Abrir Repórter IA</a> <a class="btn secondary" href="boletim-ia.php">Abrir Boletim IA</a></p></main></div></body></html>
