<?php
require_once __DIR__.'/../includes/outbound_guard.php';
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/config.php';
$activeAdmin='rss';

// Central RSS robusta: nunca deve gerar tela branca. Qualquer erro aparece no painel e é salvo em log.
ini_set('display_errors','0');
error_reporting(E_ALL);

$root = dirname(__DIR__);
$dataDir = $root.'/data';
$fontesFile = $dataDir.'/fontes.json';
$statusFile = $dataDir.'/rss_status.json';
$logFile = $dataDir.'/rss_debug.log';
if(!is_dir($dataDir)) @mkdir($dataDir,0755,true);

function rss_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rss_read_json($path){
  if(!file_exists($path)) return [];
  $raw = @file_get_contents($path);
  if($raw===false || trim($raw)==='') return [];
  $json = json_decode($raw,true);
  return is_array($json) ? $json : [];
}
function rss_save_json($path,$data){
  @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}
function rss_log($msg){
  global $logFile;
  @file_put_contents($logFile, '['.date('Y-m-d H:i:s').'] '.$msg."\n", FILE_APPEND);
}
function rss_fetch_url($url){
  if(!$url || !function_exists('curl_init') || !function_exists('tvs_outbound_curl_options')) return '';
  $options=tvs_outbound_curl_options($url,10);
  if($options===null){
    rss_log('URL bloqueada pela politica de saida.');
    return '';
  }
  $ch=curl_init((string)$url);
  curl_setopt_array($ch,$options+[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_USERAGENT=>'TVSumareRSS/2.0',
    CURLOPT_HTTPHEADER=>['Accept: application/rss+xml, application/xml, text/xml']
  ]);
  $body=curl_exec($ch);
  $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
  if($body===false) rss_log('Falha segura ao consultar fonte permitida.');
  curl_close($ch);
  if(!is_string($body) || $http<200 || $http>=300) return '';
  return substr($body,0,2097152);
}
function rss_clean_text($s){
  $s = html_entity_decode((string)$s, ENT_QUOTES|ENT_HTML5, 'UTF-8');
  $s = strip_tags($s);
  $s = preg_replace('/\s+/u',' ', $s);
  return trim($s);
}
function rss_parse_items($xml){
  $items=[];
  if(trim((string)$xml)==='') return $items;
  libxml_use_internal_errors(true);
  if(function_exists('simplexml_load_string')){
    $sx = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    if($sx){
      $nodes = [];
      if(isset($sx->channel->item)) $nodes = $sx->channel->item;
      elseif(isset($sx->entry)) $nodes = $sx->entry;
      foreach($nodes as $it){
        $title = rss_clean_text((string)($it->title ?? ''));
        $link = '';
        if(isset($it->link)){
          $linkNode = $it->link;
          $attrs = $linkNode->attributes();
          $link = isset($attrs['href']) ? (string)$attrs['href'] : (string)$linkNode;
        }
        $date = (string)($it->pubDate ?? $it->updated ?? $it->published ?? '');
        $desc = rss_clean_text((string)($it->description ?? $it->summary ?? $it->content ?? ''));
        if($title!=='') $items[]=['title'=>$title,'link'=>$link,'date'=>$date,'desc'=>$desc];
        if(count($items)>=30) break;
      }
    }
  }
  // Fallback por regex se SimpleXML falhar ou estiver indisponível
  if(!$items){
    preg_match_all('~<item\b[^>]*>(.*?)</item>~is',$xml,$matches);
    foreach($matches[1] as $block){
      preg_match('~<title[^>]*>(.*?)</title>~is',$block,$mt);
      preg_match('~<link[^>]*>(.*?)</link>~is',$block,$ml);
      preg_match('~<pubDate[^>]*>(.*?)</pubDate>~is',$block,$md);
      preg_match('~<description[^>]*>(.*?)</description>~is',$block,$ms);
      $title = rss_clean_text($mt[1] ?? '');
      if($title!=='') $items[]=['title'=>$title,'link'=>rss_clean_text($ml[1]??''),'date'=>rss_clean_text($md[1]??''),'desc'=>rss_clean_text($ms[1]??'')];
      if(count($items)>=30) break;
    }
  }
  return $items;
}
function feedbin_configured(){
  return trim((string)(getenv('FEEDBIN_USERNAME')?:''))!=='' && trim((string)(getenv('FEEDBIN_PASSWORD')?:''))!=='';
}
function feedbin_request($path){
  if(!feedbin_configured()) return ['ok'=>false,'status'=>0,'error'=>'Credenciais Feedbin ainda não configuradas.','data'=>null];
  if(!preg_match('~^/(subscriptions|entries)(?:[/?].*)?$~',$path)) return ['ok'=>false,'status'=>0,'error'=>'Rota Feedbin não permitida.','data'=>null];

  $url='https://api.feedbin.com/v2'.$path;
  $options=tvs_outbound_curl_options($url,20);
  if($options===null) return ['ok'=>false,'status'=>0,'error'=>'Feedbin bloqueado pela política de saída.','data'=>null];

  $ch=curl_init($url);
  curl_setopt_array($ch,$options+[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,
    CURLOPT_USERPWD=>trim((string)getenv('FEEDBIN_USERNAME')).':'.trim((string)getenv('FEEDBIN_PASSWORD')),
    CURLOPT_HTTPHEADER=>['Accept: application/json'],
    CURLOPT_USERAGENT=>'TVSumareFeedbinPilot/1.0'
  ]);
  $body=curl_exec($ch);
  $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);

  if(!is_string($body) || $body==='') return ['ok'=>false,'status'=>$http,'error'=>'Feedbin sem resposta'.($err?': '.$err:''),'data'=>null];
  if(strlen($body)>4194304) return ['ok'=>false,'status'=>$http,'error'=>'Resposta Feedbin excedeu o limite seguro.','data'=>null];

  $data=json_decode($body,true);
  if($http<200 || $http>=300 || !is_array($data)){
    return ['ok'=>false,'status'=>$http,'error'=>'Feedbin HTTP '.$http.' ou JSON inválido.','data'=>null];
  }
  return ['ok'=>true,'status'=>$http,'error'=>'','data'=>$data];
}
function feedbin_pilot_metrics($entries){
  $metrics=[
    'entries'=>0,'original_url'=>0,'content'=>0,'published'=>0,
    'image'=>0,'extracted_content_url'=>0,'google_url'=>0
  ];
  $sample=[];
  foreach((array)$entries as $e){
    if(!is_array($e)) continue;
    $metrics['entries']++;
    $url=trim((string)($e['url']??''));
    if($url!==''){
      if(stripos($url,'news.google.com')!==false) $metrics['google_url']++;
      else $metrics['original_url']++;
    }
    if(trim(strip_tags((string)($e['content']??'')))!=='') $metrics['content']++;
    if(trim((string)($e['published']??''))!=='') $metrics['published']++;
    if(trim((string)($e['extracted_content_url']??''))!=='') $metrics['extracted_content_url']++;
    $images=$e['images']??null;
    if(is_array($images) && (!empty($images['original_url']) || !empty($images['size_1']['cdn_url']))) $metrics['image']++;
    if(count($sample)<8){
      $sample[]=[
        'title'=>(string)($e['title']??'Sem título'),
        'url'=>$url,
        'published'=>(string)($e['published']??''),
        'has_content'=>trim(strip_tags((string)($e['content']??'')))!=='' ? 1 : 0,
        'has_image'=>(is_array($images) && (!empty($images['original_url']) || !empty($images['size_1']['cdn_url']))) ? 1 : 0
      ];
    }
  }
  return ['metrics'=>$metrics,'sample'=>$sample];
}

function rss_default_sources(){
  return [
    ['name'=>'Agência Brasil — Últimas Notícias','type'=>'Agência','city'=>'Brasil','category'=>'Brasil','url'=>'https://agenciabrasil.ebc.com.br/rss/ultimasnoticias/feed.xml','rss'=>'https://agenciabrasil.ebc.com.br/rss/ultimasnoticias/feed.xml','active'=>true],
    ['name'=>'Google News — Sumaré e Região','type'=>'Radar','city'=>'Região','category'=>'Regional','url'=>'https://news.google.com/rss/search?q=Sumar%C3%A9%20OR%20Hortol%C3%A2ndia%20OR%20Paul%C3%ADnia%20OR%20%22Nova%20Odessa%22%20OR%20Americana%20OR%20Campinas&hl=pt-BR&gl=BR&ceid=BR:pt-419','rss'=>'https://news.google.com/rss/search?q=Sumar%C3%A9%20OR%20Hortol%C3%A2ndia%20OR%20Paul%C3%ADnia%20OR%20%22Nova%20Odessa%22%20OR%20Americana%20OR%20Campinas&hl=pt-BR&gl=BR&ceid=BR:pt-419','active'=>true],
    ['name'=>'Governo SP','type'=>'Oficial','city'=>'São Paulo','category'=>'Governo','url'=>'https://www.saopaulo.sp.gov.br/feed/','rss'=>'https://www.saopaulo.sp.gov.br/feed/','active'=>true],
    ['name'=>'Portal de Sumaré','type'=>'Portal Regional','city'=>'Sumaré','category'=>'Regional','url'=>'https://portaldesumare.com.br/','rss'=>'','active'=>true]
  ];
}

$fontes = rss_read_json($fontesFile);
if(!is_array($fontes)) $fontes=[];
$msg=''; $errors=[]; $tested=false; $results=[];

try{
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? '';
    if($action==='seed'){
      $existing=[]; foreach($fontes as $f){ $existing[strtolower(trim($f['name']??''))]=1; }
      foreach(rss_default_sources() as $src){
        $key=strtolower(trim($src['name']));
        if(empty($existing[$key])) $fontes[]=$src;
      }
      rss_save_json($fontesFile,$fontes);
      $msg='Fontes essenciais ativadas/atualizadas.';
    }
    if($action==='toggle'){
      $i=(int)($_POST['idx']??-1);
      if(isset($fontes[$i])){
        $fontes[$i]['active']=empty($fontes[$i]['active']);
        rss_save_json($fontesFile,$fontes);
        $msg='Status atualizado.';
      }
    }
    if($action==='test'){
      $tested=true;
      foreach($fontes as $i=>$f){
        if(empty($f['active'])) continue;
        $feed = trim((string)($f['rss'] ?? '')) ?: trim((string)($f['url'] ?? ''));
        if($feed===''){
          $results[]=['name'=>$f['name']??'Fonte','ok'=>false,'count'=>0,'error'=>'Sem URL/RSS configurado','items'=>[]];
          continue;
        }
        $xml = rss_fetch_url($feed);
        $items = rss_parse_items($xml);
        $ok = count($items)>0;
        $results[]=[
          'name'=>$f['name']??'Fonte',
          'ok'=>$ok,
          'count'=>count($items),
          'error'=>$ok?'':'Não retornou itens RSS. Pode ser página HTML comum ou feed indisponível.',
          'items'=>array_slice($items,0,5),
          'checked_at'=>date('c')
        ];
      }
      $existingStatus=rss_read_json($statusFile);
      if(!is_array($existingStatus)) $existingStatus=[];
      $existingStatus['updated_at']=date('c');
      $existingStatus['results']=$results;
      rss_save_json($statusFile,$existingStatus);
      $msg='Teste RSS concluído.';
    }
    if($action==='feedbin_test'){
      $statusNow=rss_read_json($statusFile);
      if(!is_array($statusNow)) $statusNow=[];
      if(!feedbin_configured()){
        $pilot=[
          'configured'=>0,
          'checked_at'=>date('c'),
          'ok'=>0,
          'error'=>'Credenciais Feedbin ainda não configuradas no runtime.',
          'metrics'=>[],
          'sample'=>[]
        ];
      } else {
        $resp=feedbin_request('/entries.json?per_page=30&mode=extended');
        if(!empty($resp['ok'])){
          $pilotData=feedbin_pilot_metrics($resp['data']??[]);
          $pilot=[
            'configured'=>1,
            'checked_at'=>date('c'),
            'ok'=>1,
            'error'=>'',
            'metrics'=>$pilotData['metrics'],
            'sample'=>$pilotData['sample']
          ];
        } else {
          $pilot=[
            'configured'=>1,
            'checked_at'=>date('c'),
            'ok'=>0,
            'error'=>(string)($resp['error']??'Falha desconhecida no Feedbin.'),
            'metrics'=>[],
            'sample'=>[]
          ];
        }
      }
      $statusNow['feedbin_pilot']=$pilot;
      rss_save_json($statusFile,$statusNow);
      $msg=!empty($pilot['ok'])
        ? 'Piloto Feedbin concluído sem alterar o Radar principal.'
        : 'Piloto Feedbin preparado; verifique o status abaixo.';
    }
  }
}catch(Throwable $e){
  $errors[]=$e->getMessage();
  rss_log('ERRO RSS Central: '.$e->getMessage());
}

$status = rss_read_json($statusFile);
if(!$results && !empty($status['results']) && is_array($status['results'])) $results=$status['results'];
$feedbinPilot=is_array($status['feedbin_pilot']??null)?$status['feedbin_pilot']:[
  'configured'=>feedbin_configured()?1:0,
  'ok'=>0,
  'checked_at'=>'',
  'error'=>feedbin_configured()?'Piloto ainda não executado.':'Credenciais Feedbin ainda não configuradas no runtime.',
  'metrics'=>[],
  'sample'=>[]
];
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Central RSS</title><link rel="stylesheet" href="admin.css"><style>.status-ok{color:#17803a;font-weight:700}.status-bad{color:#b42318;font-weight:700}.rss-preview{margin:.35rem 0 0 1rem;color:#475467}.rss-preview li{margin:.2rem 0}.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}.metric{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}.metric b{font-size:26px;display:block}.debug{background:#fff3cd;border:1px solid #ffe69c;padding:12px;border-radius:12px;color:#664d03}</style></head><body class="admin"><div class="layout"><?php include '_menu.php'; ?><main class="main"><h1>📡 Central RSS</h1><p class="muted">Gerencie e teste as fontes RSS que abastecem o Radar, Última Hora e a fila editorial. RSS não publica automaticamente: tudo segue para aprovação.</p><?php if($msg):?><div class="notice"><?=rss_h($msg)?></div><?php endif;?><?php if($errors):?><div class="debug"><b>Erro interno:</b><br><?=rss_h(implode(' | ',$errors))?></div><?php endif;?>

<div class="grid2"><div class="metric"><span>Fontes cadastradas</span><b><?=count($fontes)?></b></div><div class="metric"><span>Fontes ativas</span><b><?=count(array_filter($fontes,function($f){return !empty($f['active']);}))?></b></div><div class="metric"><span>Último teste</span><b style="font-size:16px"><?=rss_h(!empty($status['updated_at'])?date('d/m H:i',strtotime($status['updated_at'])):'Ainda não testado')?></b></div></div>

<div class="actions" style="margin-top:16px"><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="seed"><button class="btn primary">Ativar fontes essenciais</button></form><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="test"><button class="btn">Testar RSS agora</button></form><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="feedbin_test"><button class="btn">Testar piloto Feedbin</button></form><a class="btn" href="radar-regional.php">Abrir Radar</a></div>

<section class="card"><h2>Resultado do teste RSS</h2><?php if(!$results):?><p class="muted">Clique em <b>Testar RSS agora</b> para verificar se as fontes estão retornando itens.</p><?php else:?><table><thead><tr><th>Fonte</th><th>Status</th><th>Itens</th><th>Prévia</th></tr></thead><tbody><?php foreach($results as $r):?><tr><td><strong><?=rss_h($r['name']??'Fonte')?></strong></td><td><?=!empty($r['ok'])?'<span class="status-ok">OK</span>':'<span class="status-bad">Falha</span>'?><br><small><?=rss_h($r['error']??'')?></small></td><td><?= (int)($r['count']??0) ?></td><td><?php if(!empty($r['items'])):?><ul class="rss-preview"><?php foreach(array_slice($r['items'],0,4) as $it):?><li><?=rss_h($it['title']??'')?></li><?php endforeach;?></ul><?php else:?><span class="muted">Sem itens para exibir.</span><?php endif;?></td></tr><?php endforeach;?></tbody></table><?php endif;?></section>

<section class="card"><h2>Piloto Feedbin</h2>
<?php $fm=is_array($feedbinPilot['metrics']??null)?$feedbinPilot['metrics']:[]; ?>
<p class="muted">Piloto isolado: consulta até 30 entradas da conta Feedbin em modo extended e mede URL original, conteúdo, data, imagem e disponibilidade do extrator. Não altera o Radar nem publica matérias.</p>
<div class="grid2">
  <div class="metric"><span>Configuração</span><b style="font-size:18px"><?=!empty($feedbinPilot['configured'])?'Pronta':'Pendente'?></b></div>
  <div class="metric"><span>Entradas</span><b><?=(int)($fm['entries']??0)?></b></div>
  <div class="metric"><span>URL original</span><b><?=(int)($fm['original_url']??0)?></b></div>
  <div class="metric"><span>Conteúdo</span><b><?=(int)($fm['content']??0)?></b></div>
  <div class="metric"><span>Imagem</span><b><?=(int)($fm['image']??0)?></b></div>
  <div class="metric"><span>Extrator disponível</span><b><?=(int)($fm['extracted_content_url']??0)?></b></div>
</div>
<?php if(!empty($feedbinPilot['error'])): ?><div class="debug" style="margin-top:12px"><?=rss_h($feedbinPilot['error'])?></div><?php endif; ?>
<?php if(!empty($feedbinPilot['sample'])): ?><table style="margin-top:12px"><thead><tr><th>Entrada</th><th>URL</th><th>Data</th><th>Conteúdo</th><th>Imagem</th></tr></thead><tbody><?php foreach(array_slice($feedbinPilot['sample'],0,8) as $s): ?><tr><td><?=rss_h($s['title']??'Sem título')?></td><td><small><?=rss_h($s['url']??'')?></small></td><td><small><?=rss_h($s['published']??'')?></small></td><td><?=!empty($s['has_content'])?'✅':'—'?></td><td><?=!empty($s['has_image'])?'✅':'—'?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
</section>

<section class="card"><h2>Fontes monitoradas</h2><?php if(!$fontes):?><p class="muted">Nenhuma fonte cadastrada. Clique em <b>Ativar fontes essenciais</b>.</p><?php else:?><table><thead><tr><th>Fonte</th><th>Tipo</th><th>Cidade</th><th>Categoria</th><th>RSS/URL</th><th>Status</th><th>Ação</th></tr></thead><tbody><?php foreach($fontes as $i=>$f):?><tr><td><strong><?=rss_h($f['name']??'Fonte')?></strong></td><td><?=rss_h($f['type']??'RSS')?></td><td><?=rss_h($f['city']??'Região')?></td><td><?=rss_h($f['category']??'')?></td><td><small><?=rss_h(($f['rss']??'') ?: ($f['url']??''))?></small></td><td><?=!empty($f['active'])?'✅ Ativa':'⏸ Inativa'?></td><td><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="idx" value="<?=$i?>"><button class="btn small">Alternar</button></form></td></tr><?php endforeach;?></tbody></table><?php endif;?></section>

<section class="card"><h2>Observação técnica</h2><p class="muted">Se uma fonte aparecer como falha, ela não quebra mais a página. O erro fica isolado e registrado em <code>data/rss_debug.log</code>. Algumas prefeituras não possuem RSS público; nesses casos o Radar usa a página do site como fonte de pauta, mas o teste RSS pode mostrar falha.</p></section>
</main></div></body></html>
