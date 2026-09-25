<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/monitor_lib.php';
include dirname(__DIR__).'/config.php';

$nf = dirname(__DIR__) . '/data/noticias.json';
$news = file_exists($nf)?json_decode(file_get_contents($nf),true):[]; if(!is_array($news)) $news=[];
$id=$_GET['id']??''; $n=null; foreach($news as $item){ if(($item['id']??'')===$id) $n=$item; }
if(!$n){ http_response_code(404); echo 'Notícia não encontrada'; exit; }

$url=rtrim($site_url??'https://tvsumare.com.br','/').'/noticia.php?id='.urlencode($id);
$title=$n['title']??''; $city=$n['city']??'Sumaré e região'; $cat=$n['category']??'Notícias';
$caption="🚨 ".$title."\n\n".($n['subtitle']??'')."\n\n📍 ".$city."\n🗞️ Categoria: ".$cat."\n\nLeia a matéria completa no portal TV Sumaré:\n".$url."\n\n#TVSumaré #Sumaré #Paulínia #NovaOdessa #Hortolândia #Campinas #Americana #NotíciasDaRegião";

$metaPageId=trim((string)(getenv('META_PAGE_ID')?:''));
$metaPageUrl=trim((string)(getenv('META_PAGE_URL')?:''));
$metaPageToken=trim((string)(getenv('META_PAGE_ACCESS_TOKEN')?:''));
$facebookReady=$metaPageId!=='' && $metaPageToken!=='';
$publishResult=null;

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')==='publish_facebook'){
    tvs_verify_csrf();
    if(!$facebookReady){
        $publishResult=['ok'=>false,'message'=>'Facebook configurado parcialmente: falta um Page Access Token válido no runtime.'];
    }elseif(!function_exists('curl_init')){
        $publishResult=['ok'=>false,'message'=>'Publicação indisponível: extensão cURL não encontrada no container.'];
    }else{
        $endpoint='https://graph.facebook.com/v26.0/'.rawurlencode($metaPageId).'/feed';
        $ch=curl_init($endpoint);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>30,
            CURLOPT_POSTFIELDS=>[
                'message'=>$caption,
                'link'=>$url,
                'access_token'=>$metaPageToken,
            ],
        ]);
        $body=(string)curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $curlError=(string)curl_error($ch);
        curl_close($ch);
        $json=json_decode($body,true);
        if($status>=200 && $status<300 && is_array($json) && !empty($json['id'])){
            $publishResult=['ok'=>true,'message'=>'Publicação enviada para a Página do Facebook. ID: '.(string)$json['id']];
        }else{
            $apiMessage=is_array($json)?(string)($json['error']['message']??''):'';
            $publishResult=['ok'=>false,'message'=>'A Meta recusou a publicação'.($apiMessage!==''?': '.$apiMessage:($curlError!==''?': '.$curlError:'.'))];
        }
    }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Distribuir nas redes | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=6"><style>.post-preview{display:grid;grid-template-columns:360px 1fr;gap:18px}.insta-card{aspect-ratio:1/1;border-radius:24px;overflow:hidden;background:linear-gradient(135deg,#061a4d,#ff7a00);color:#fff;display:flex;flex-direction:column;justify-content:flex-end;padding:24px;box-shadow:0 20px 50px rgba(0,0,0,.2)}.insta-card img{width:120px;height:75px;object-fit:contain;background:#fff;border-radius:14px;padding:6px;margin-bottom:auto}.insta-card h2{font-size:25px;line-height:1.08;margin:18px 0 10px}.insta-card span{font-weight:900;letter-spacing:.08em;color:#ffd29e}.copybox{min-height:260px}.social-status{padding:12px 14px;border-radius:12px;background:#f8fafc;margin:12px 0}.social-status.ok{background:#ecfdf3}.social-status.warn{background:#fff7ed}@media(max-width:850px){.post-preview{grid-template-columns:1fr}}</style></head><body><div class="admin"><aside class="side"><div class="logo"><img src="../assets/logo-tv-sumare.jpeg" alt="TV Sumaré"><div><b>TV SUMARÉ</b><br><small>Painel Administrativo</small></div></div><nav class="menu"><a href="index.php">Dashboard</a><a href="noticias.php">Notícias publicadas</a><a href="drafts.php">Rascunhos</a><a href="monitor.php">Monitor + Gemini</a><a href="logout.php">Sair</a></nav></aside><main class="main"><div class="top"><div><span class="eyebrow">Distribuição</span><h1>Facebook e Instagram</h1></div><a class="btn secondary" href="noticias.php">Voltar</a></div>
<?php if($publishResult): ?><div class="notice <?=$publishResult['ok']?'':'error'?>"><?=htmlspecialchars($publishResult['message'])?></div><?php endif; ?>
<div class="post-preview"><div class="insta-card"><img src="../assets/logo-tv-sumare.jpeg" alt="TV Sumaré"><span><?=htmlspecialchars($cat)?></span><h2><?=htmlspecialchars($title)?></h2><p><?=htmlspecialchars($city)?> • Leia no portal</p></div><div class="box"><h2>Texto de distribuição</h2><textarea class="copybox" id="caption"><?=htmlspecialchars($caption)?></textarea>
<div class="social-status <?=$facebookReady?'ok':'warn'?>"><strong>Facebook:</strong> <?=$facebookReady?'pronto para publicar via API':'Página cadastrada; aguardando Page Access Token'?><?php if($metaPageUrl!==''): ?> · <a target="_blank" href="<?=htmlspecialchars($metaPageUrl)?>">abrir Página</a><?php endif; ?></div>
<div class="actions"><button class="btn orange" onclick="navigator.clipboard.writeText(document.getElementById('caption').value);alert('Legenda copiada!')">Copiar legenda</button><a class="btn" target="_blank" href="https://www.instagram.com/">Abrir Instagram</a><a class="btn secondary" target="_blank" href="<?=htmlspecialchars($url)?>">Abrir matéria</a></div>
<form method="post" style="margin-top:14px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="publish_facebook"><button class="btn orange" type="submit" <?=$facebookReady?'':'disabled'?>>Publicar na Página do Facebook</button></form>
<p><small>A publicação automática usa credencial somente pelo runtime; o token não é salvo no código nem exibido no painel.</small></p></div></div></main></div></body></html>
