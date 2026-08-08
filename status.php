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
// Carregamento blindado da HeyGen: lê JSON, config.php, variáveis de ambiente e fallback interno do projeto.
$rpiaCfg=tvs_heygen_repair_config($rpiaCfg);
$heygenKey=trim((string)($rpiaCfg['heygen_api_key']??''));
$heygenAvatar=trim((string)($rpiaCfg['heygen_avatar_id']??''));
$heygenVoice=trim((string)($rpiaCfg['heygen_voice_id']??''));
$heygenConfigured=($heygenKey!=='' && $heygenAvatar!=='' && $heygenVoice!=='');
$heygenDiag=tvs_heygen_diagnostics($rpiaCfg);
$geminiTest=null;
$heygenTest=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')==='test_gemini'){
  if(empty($gemini_api_key)){
    $geminiTest=['ok'=>false,'message'=>'Gemini sem chave configurada.'];
  } else {
    $r=tvs_gemini_generate_text($gemini_api_key,'Responda exatamente com a frase: TV SUMARÉ GEMINI OK',['temperature'=>0,'maxOutputTokens'=>30],18);
    $geminiTest=!empty($r['ok'])
      ? ['ok'=>true,'message'=>'Gemini respondeu corretamente usando o modelo '.$r['model'].'.']
      : ['ok'=>false,'message'=>'Falha no teste Gemini. Verifique o arquivo data/ia_erros.log. Detalhe: '.($r['error']??'sem detalhe')];
  }
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')==='test_heygen'){
  if($heygenKey===''){
    $heygenTest=['ok'=>false,'message'=>'HeyGen sem chave configurada no arquivo ativo do servidor. Use o Diagnóstico HeyGen abaixo para confirmar o caminho real e se o JSON foi gravado.'];
  } elseif(!function_exists('curl_init')){
    $heygenTest=['ok'=>false,'message'=>'cURL não está habilitado no servidor.'];
  } else {
    $url='https://api.heygen.com/v3/video-agents/styles?limit=1';
    $outboundOptions=tvs_outbound_curl_options($url,20);
    if($outboundOptions===null){
      $heygenTest=['ok'=>false,'message'=>'HeyGen bloqueada pela politica de saida.'];
    } else {
    $ch=curl_init($url);
    curl_setopt_array($ch,$outboundOptions+[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['x-api-key: '.$heygenKey,'Accept: application/json']]);
    $res=curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $heygenTest=(is_string($res) && strlen($res)<=2097152 && $res!=='' && $http<400)
      ? ['ok'=>true,'message'=>'HeyGen respondeu corretamente. API Video Agent disponível.']
      : ['ok'=>false,'message'=>'Falha no teste HeyGen. HTTP '.$http.' '.$err.' '.substr((string)$res,0,180)];
    }
  }
}
$checks=[
 ['Gemini configurado', !empty($gemini_api_key??''), 'Modelo: '.htmlspecialchars($gemini_model??'não definido',ENT_QUOTES,'UTF-8').' • chave oculta'],
 ['HeyGen Video Agent configurado', $heygenConfigured, 'API Key + Avatar ID + Voice ID configurados; chave oculta'],
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
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Status do Sistema | Admin TV Sumaré</title><link rel="stylesheet" href="admin.css"><style>.status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.status-card{background:#fff;border:1px solid #e6edf8;border-radius:18px;padding:18px;box-shadow:0 10px 25px rgba(15,47,104,.06)}.ok{color:#166534;font-weight:900}.bad{color:#b91c1c;font-weight:900}.small{color:#64748b;font-size:13px}.test-ok{background:#dcfce7;border:1px solid #86efac;color:#14532d;padding:12px;border-radius:14px;font-weight:800}.test-bad{background:#fee2e2;border:1px solid #fca5a5;color:#7f1d1d;padding:12px;border-radius:14px;font-weight:800}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Enterprise 1.0</span><h1>Status do Sistema</h1><p class="muted" style="text-align:left">Diagnóstico seguro para acompanhar o funcionamento do portal sem expor chaves ou dados sensíveis.</p></div><a class="btn secondary" href="index.php">Voltar ao Dashboard</a></div><section class="status-grid"><?php foreach($checks as $c): ?><article class="status-card"><h3><?=htmlspecialchars($c[0],ENT_QUOTES,'UTF-8')?></h3><div class="<?=$c[1]?'ok':'bad'?>"><?=$c[1]?'ONLINE / OK':'ATENÇÃO'?></div><div class="small"><?=htmlspecialchars($c[2],ENT_QUOTES,'UTF-8')?></div></article><?php endforeach; ?></section><section class="box" style="margin-top:18px"><h2>Teste Gemini</h2><p class="muted" style="text-align:left">Use este botão depois de subir para a HostGator. Ele valida a chave e o modelo sem exibir a chave no painel.</p><?php if($geminiTest): ?><div class="<?=$geminiTest['ok']?'test-ok':'test-bad'?>"><?=htmlspecialchars($geminiTest['message'],ENT_QUOTES,'UTF-8')?></div><?php endif; ?><form method="post" style="margin-top:12px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="test_gemini"><button class="btn orange" type="submit">Testar conexão Gemini</button></form></section><section class="box" style="margin-top:18px"><h2>Teste HeyGen Video Agent</h2><p class="muted" style="text-align:left">Valida se a chave da HeyGen responde no endpoint v3 de estilos do Video Agent.</p><?php if($heygenTest): ?><div class="<?=$heygenTest['ok']?'test-ok':'test-bad'?>"><?=htmlspecialchars($heygenTest['message'],ENT_QUOTES,'UTF-8')?></div><?php endif; ?><form method="post" style="margin-top:12px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="test_heygen"><button class="btn orange" type="submit">Testar conexão HeyGen</button></form></section><section class="box" style="margin-top:18px"><h2>Diagnóstico HeyGen</h2><p class="muted" style="text-align:left">Mostra o caminho real usado pela HostGator, sem exibir a chave completa.</p><div class="small"><strong>Raiz detectada:</strong> <?=htmlspecialchars($heygenDiag['root'],ENT_QUOTES,'UTF-8')?><br><strong>Chave:</strong> <?=htmlspecialchars($heygenDiag['key_masked'],ENT_QUOTES,'UTF-8')?><br><strong>Avatar:</strong> <?=$heygenDiag['avatar_configured']?'configurado':'não configurado'?> • <strong>Voz:</strong> <?=$heygenDiag['voice_configured']?'configurada':'não configurada'?></div><?php foreach($heygenDiag['paths'] as $p): ?><div class="small" style="margin-top:8px;padding:8px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc"><?=htmlspecialchars($p['path'],ENT_QUOTES,'UTF-8')?> — existe: <?=$p['exists']?'sim':'não'?> • gravável: <?=$p['writable']?'sim':'não'?> • chave no JSON: <?=$p['has_key']?'sim':'não'?></div><?php endforeach; ?></section><section class="box" style="margin-top:18px"><h2>Recomendações de teste beta</h2><p class="muted" style="text-align:left">Teste diariamente: Radar, aprovação de matérias, vídeos, Ao Vivo, Guia Comercial, Área Comercial, redes sociais e geração de RSS/sitemap.</p></section></main></div></body></html>
