<?php
require_once __DIR__.'/auth.php';
require_login();
$activeAdmin='status';
include dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once dirname(__DIR__).'/includes/heygen_helper.php';
function tvs_status_file($path){ return file_exists($path); }
function tvs_status_json_count($path){ if(!file_exists($path)) return 0; $d=json_decode(file_get_contents($path),true); return is_array($d)?count($d):0; }
function tvs_status_writable($path){ if(file_exists($path)) return is_writable($path); $dir=dirname($path); return is_dir($dir) && is_writable($dir); }
$root=dirname(__DIR__);
$rpiaCfgPath=$root.'/data/reporter_ia_config.json';
$rpiaCfg=[];
if(file_exists($rpiaCfgPath)){
  $tmp=json_decode(file_get_contents($rpiaCfgPath),true);
  if(is_array($tmp)) $rpiaCfg=$tmp;
}
$rpiaCfg=tvs_heygen_repair_config($rpiaCfg);
$heygenKey=trim((string)($rpiaCfg['heygen_api_key']??''));
$heygenAvatar=trim((string)($rpiaCfg['heygen_avatar_id']??''));
$heygenVoice=trim((string)($rpiaCfg['heygen_voice_id']??''));
$heygenConfigured=($heygenKey!=='' && $heygenAvatar!=='' && $heygenVoice!=='');
$heygenDiag=tvs_heygen_diagnostics($rpiaCfg);
$radarNotification=[];
$radarNotificationPath=$root.'/data/radar_notification.json';
if(file_exists($radarNotificationPath)){
  $tmp=json_decode(file_get_contents($radarNotificationPath),true);
  if(is_array($tmp)) $radarNotification=$tmp;
}
$whatsappWebhook=trim((string)(getenv('TVSUMARE_WHATSAPP_WEBHOOK_URL') ?: ''));
$whatsappConfigured=$whatsappWebhook!=='';
$geminiTest=null;
$heygenTest=null;
$whatsappTest=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') tvs_verify_csrf();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')==='test_gemini'){
  if(empty($gemini_api_key)){
    $geminiTest=['ok'=>false,'message'=>'Gemini sem chave configurada.'];
  } else {
    $r=tvs_gemini_generate_text($gemini_api_key,'Responda exatamente com a frase: TV SUMARÉ GEMINI OK',['temperature'=>0,'maxOutputTokens'=>30],18);
    $geminiTest=!empty($r['ok'])
      ? ['ok'=>true,'message'=>'Gemini respondeu corretamente usando o modelo '.$r['model'].'.']
      : ['ok'=>false,'message'=>'Falha no teste Gemini. Consulte o log técnico de IA.'];
  }
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')==='test_heygen'){
  if($heygenKey===''){
    $heygenTest=['ok'=>false,'message'=>'HeyGen sem chave configurada no ambiente ativo. Use o diagnóstico abaixo para confirmar a configuração sem expor credenciais.'];
  } elseif(!function_exists('curl_init')){
    $heygenTest=['ok'=>false,'message'=>'cURL não está habilitado no servidor.'];
  } else {
    $url='https://api.heygen.com/v3/video-agents/styles?limit=1';
    $outboundOptions=tvs_outbound_curl_options($url,20);
    if($outboundOptions===null){
      $heygenTest=['ok'=>false,'message'=>'Conexão com o provedor de vídeo bloqueada pela política de saída.'];
    } else {
      $ch=curl_init($url);
      curl_setopt_array($ch,$outboundOptions+[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['x-api-key: '.$heygenKey,'Accept: application/json']]);
      $res=curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      $heygenTest=(is_string($res) && strlen($res)<=2097152 && $res!=='' && $http<400)
        ? ['ok'=>true,'message'=>'HeyGen respondeu corretamente. API Video Agent disponível.']
        : ['ok'=>false,'message'=>'Não foi possível validar o provedor de vídeo agora. Consulte o diagnóstico técnico.'];
    }
  }
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')==='test_whatsapp'){
  if(!$whatsappConfigured){
    $whatsappTest=['ok'=>false,'message'=>'WhatsApp ainda aguarda configuração do webhook.'];
  } elseif(!function_exists('tvs_outbound_curl_options')){
    $whatsappTest=['ok'=>false,'message'=>'Proteção de saída indisponível.'];
  } else {
    $curlOptions=tvs_outbound_curl_options($whatsappWebhook,15);
    if($curlOptions===null || !function_exists('curl_init')){
      $whatsappTest=['ok'=>false,'message'=>'Webhook bloqueado pela política de saída ou cURL indisponível.'];
    } else {
      $message=trim((string)($radarNotification['whatsapp_text']??''));
      if($message==='') $message='TV Sumaré — teste de notificação editorial do Radar.';
      $payload=json_encode([
        'event'=>'tvsumare.radar.test',
        'date'=>date('Y-m-d'),
        'message'=>$message,
        'summary'=>$radarNotification,
        'test'=>true
      ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $ch=curl_init($whatsappWebhook);
      curl_setopt_array($ch,$curlOptions+[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>$payload
      ]);
      $res=curl_exec($ch);
      $http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
      $err=(string)curl_error($ch);
      curl_close($ch);
      $whatsappTest=($res!==false && $http>=200 && $http<300)
        ? ['ok'=>true,'message'=>'Webhook de WhatsApp respondeu corretamente ao teste editorial.']
        : ['ok'=>false,'message'=>'Falha no webhook de WhatsApp'.($err!==''?': '.$err:' (HTTP '.$http.').')];
    }
  }
}
$checks=[
 ['Gemini configurado', !empty($gemini_api_key??''), 'Modelo: '.htmlspecialchars($gemini_model??'não definido',ENT_QUOTES,'UTF-8').' • chave oculta'],
 ['HeyGen Video Agent configurado', $heygenConfigured, 'API Key + Avatar ID + Voice ID configurados; chave oculta'],
 ['WhatsApp editorial configurado', $whatsappConfigured, $whatsappConfigured?'Webhook configurado e protegido pela política de saída':'Aguardando TVSUMARE_WHATSAPP_WEBHOOK_URL'],
 ['Pasta data gravável', is_writable($root.'/data'), $root.'/data'],
 ['Notícias', tvs_status_file($root.'/data/noticias.json'), tvs_status_json_count($root.'/data/noticias.json').' registros'],
 ['Matérias para aprovação', tvs_status_file($root.'/data/materias_aprovacao.json'), tvs_status_json_count($root.'/data/materias_aprovacao.json').' registros'],
 ['Vídeos', tvs_status_file($root.'/data/videos.json'), tvs_status_json_count($root.'/data/videos.json').' registros'],
 ['Vídeos IA', tvs_status_file($root.'/data/videos_ia.json'), tvs_status_json_count($root.'/data/videos_ia.json').' roteiros/jobs'],
 ['Ao Vivo', tvs_status_file($root.'/data/site_settings.json'), 'Configurações do portal'],
 ['Colunas', tvs_status_file($root.'/data/colunas.json'), tvs_status_json_count($root.'/data/colunas.json').' registros'],
 ['RSS', tvs_status_file($root.'/rss.php'), '/rss.xml ou rss.php'],
 ['News Sitemap', tvs_status_file($root.'/news-sitemap.php'), '/news-sitemap.xml ou news-sitemap.php'],
 ['Robots', tvs_status_file($root.'/robots.txt'), 'robots.txt']
];
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Status do Sistema | Admin TV Sumaré</title><link rel="stylesheet" href="admin.css"><style>.status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.status-card{background:#fff;border:1px solid #e6edf8;border-radius:18px;padding:18px;box-shadow:0 10px 25px rgba(15,47,104,.06)}.ok{color:#166534;font-weight:900}.bad{color:#b91c1c;font-weight:900}.small{color:#64748b;font-size:13px}.test-ok{background:#dcfce7;border:1px solid #86efac;color:#14532d;padding:12px;border-radius:14px;font-weight:800}.test-bad{background:#fee2e2;border:1px solid #fca5a5;color:#7f1d1d;padding:12px;border-radius:14px;font-weight:800}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Enterprise 2.0</span><h1>Status do Sistema</h1><p class="muted" style="text-align:left">Diagnóstico seguro do ambiente de homologação/VPS sem expor chaves ou dados sensíveis.</p></div><a class="btn secondary" href="index.php">Voltar ao Dashboard</a></div><section class="status-grid"><?php foreach($checks as $c): ?><article class="status-card"><h3><?=htmlspecialchars($c[0],ENT_QUOTES,'UTF-8')?></h3><div class="<?=$c[1]?'ok':'bad'?>"><?=$c[1]?'ONLINE / OK':'ATENÇÃO'?></div><div class="small"><?=htmlspecialchars($c[2],ENT_QUOTES,'UTF-8')?></div></article><?php endforeach; ?></section><section class="box" style="margin-top:18px"><h2>Teste Gemini</h2><p class="muted" style="text-align:left">Valida a chave e o modelo no ambiente ativo sem exibir a credencial no painel.</p><?php if($geminiTest): ?><div class="<?=$geminiTest['ok']?'test-ok':'test-bad'?>"><?=htmlspecialchars($geminiTest['message'],ENT_QUOTES,'UTF-8')?></div><?php endif; ?><form method="post" style="margin-top:12px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="test_gemini"><button class="btn orange" type="submit">Testar conexão Gemini</button></form></section><section class="box" style="margin-top:18px"><h2>Teste HeyGen Video Agent</h2><p class="muted" style="text-align:left">Valida a disponibilidade do provedor de vídeo sem revelar credenciais.</p><?php if($heygenTest): ?><div class="<?=$heygenTest['ok']?'test-ok':'test-bad'?>"><?=htmlspecialchars($heygenTest['message'],ENT_QUOTES,'UTF-8')?></div><?php endif; ?><form method="post" style="margin-top:12px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="test_heygen"><button class="btn orange" type="submit">Testar conexão HeyGen</button></form></section><section class="box" style="margin-top:18px"><h2>Teste WhatsApp Editorial</h2><p class="muted" style="text-align:left">Valida o webhook de notificação sem expor a URL ou outras credenciais.</p><?php if($whatsappTest): ?><div class="<?=$whatsappTest['ok']?'test-ok':'test-bad'?>"><?=htmlspecialchars($whatsappTest['message'],ENT_QUOTES,'UTF-8')?></div><?php endif; ?><div class="small"><strong>Estado:</strong> <?=htmlspecialchars((string)($radarNotification['whatsapp_status']??($whatsappConfigured?'configurado':'aguardando_configuracao')),ENT_QUOTES,'UTF-8')?><?php if(!empty($radarNotification['whatsapp_sent_at'])): ?> • <strong>Último envio:</strong> <?=htmlspecialchars((string)$radarNotification['whatsapp_sent_at'],ENT_QUOTES,'UTF-8')?><?php endif; ?></div><form method="post" style="margin-top:12px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="test_whatsapp"><button class="btn orange" type="submit">Testar notificação WhatsApp</button></form></section><section class="box" style="margin-top:18px"><h2>Diagnóstico HeyGen</h2><p class="muted" style="text-align:left">Mostra a configuração efetivamente detectada pelo ambiente ativo, sem exibir a chave completa.</p><div class="small"><strong>Raiz detectada:</strong> <?=htmlspecialchars($heygenDiag['root'],ENT_QUOTES,'UTF-8')?><br><strong>Chave:</strong> <?=htmlspecialchars($heygenDiag['key_masked'],ENT_QUOTES,'UTF-8')?><br><strong>Avatar:</strong> <?=$heygenDiag['avatar_configured']?'configurado':'não configurado'?> • <strong>Voz:</strong> <?=$heygenDiag['voice_configured']?'configurada':'não configurada'?></div><?php foreach($heygenDiag['paths'] as $p): ?><div class="small" style="margin-top:8px;padding:8px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc"><?=htmlspecialchars($p['path'],ENT_QUOTES,'UTF-8')?> — existe: <?=$p['exists']?'sim':'não'?> • gravável: <?=$p['writable']?'sim':'não'?> • chave no JSON: <?=$p['has_key']?'sim':'não'?></div><?php endforeach; ?></section><section class="box" style="margin-top:18px"><h2>Rotina de homologação</h2><p class="muted" style="text-align:left">Valide periodicamente Radar, aprovação editorial, vídeos, Ao Vivo, Guia Comercial, Área Comercial, distribuição social, RSS e sitemap.</p></section></main></div></body></html>
