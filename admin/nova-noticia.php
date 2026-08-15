<?php
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once __DIR__.'/monitor_lib.php';

$activeAdmin='nova_noticia';
$notice='';
$error='';
$draft=$_SESSION['tvs_quick_news_draft'] ?? null;

function tvs_quick_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function tvs_quick_post($key,$fallback=''){ return trim((string)($_POST[$key]??$fallback)); }
function tvs_quick_token(){ return bin2hex(random_bytes(24)); }
function tvs_quick_image_from_source($manualImage,$sourceUrl,$source){
  $manualImage=trim((string)$manualImage);
  $sourceUrl=trim((string)$sourceUrl);
  $source=trim((string)$source);

  if($manualImage!==''){
    return [
      'image'=>$manualImage,
      'image_source_type'=>'manual',
      'image_credit'=>function_exists('tvs_image_credit_from_source')?tvs_image_credit_from_source($source,$manualImage):'',
      'image_review_required'=>0,
    ];
  }

  if($sourceUrl!=='' && function_exists('tvs_fetch_url') && function_exists('tvs_extract_meta_image_from_html')){
    $html=tvs_fetch_url($sourceUrl);
    if($html!==''){
      $captured=trim((string)tvs_extract_meta_image_from_html($sourceUrl,$html));
      if($captured!==''){
        return [
          'image'=>$captured,
          'image_source_type'=>'source_og',
          'image_credit'=>function_exists('tvs_image_credit_from_source')?tvs_image_credit_from_source($source,$captured):'',
          'image_review_required'=>0,
        ];
      }
    }
  }

  return [
    'image'=>'assets/cat-cidade.svg',
    'image_source_type'=>'default',
    'image_credit'=>'Imagem ilustrativa: TV Sumaré',
    'image_review_required'=>1,
  ];
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $action=(string)($_POST['action']??'');

  if($action==='enrich'){
    $title=tvs_quick_post('title');
    $subtitle=tvs_quick_post('subtitle');
    $city=tvs_quick_post('city','Sumaré');
    $category=tvs_quick_post('category','Cidades');
    $body=tvs_quick_post('body');
    $image=tvs_quick_post('image');
    $source=tvs_quick_post('source','Redação TV Sumaré');
    $sourceUrl=tvs_quick_post('source_url');

    if($title==='' || $body===''){
      $error='Informe título e texto-base antes de enriquecer.';
    } else {
      $imageMeta=tvs_quick_image_from_source($image,$sourceUrl,$source);
      $image=$imageMeta['image'];

      $material="TÍTULO BASE: {$title}\nSUBTÍTULO BASE: {$subtitle}\nCIDADE: {$city}\nCATEGORIA SUGERIDA: {$category}\nFONTE: {$source}\nURL DA FONTE: {$sourceUrl}\n\nTEXTO BASE:\n{$body}";
      $enriched=gemini_rewrite(
        $gemini_api_key??'',
        $material,
        [
          'style'=>'Jornalístico profissional',
          'approach'=>'Informativa',
          'size'=>'Média',
          'city'=>$city,
          'source'=>$source,
          'source_url'=>$sourceUrl,
          'mode'=>'article'
        ]
      );

      if(!is_array($enriched) || empty($enriched['title']) || empty($enriched['body'])){
        $error='O enriquecimento editorial não retornou uma matéria válida. Nada foi publicado.';
      } else {
        $draft=[
          'token'=>tvs_quick_token(),
          'enriched_at'=>date('c'),
          'title'=>trim((string)($enriched['title']??$title)),
          'subtitle'=>trim((string)($enriched['subtitle']??$subtitle)),
          'summary'=>trim((string)($enriched['summary']??'')),
          'city'=>$city,
          'category'=>trim((string)($enriched['category']??$category)),
          'body'=>trim((string)($enriched['body']??$body)),
          'image'=>$image,
          'image_source_type'=>$imageMeta['image_source_type'],
          'image_credit'=>$imageMeta['image_credit'],
          'image_review_required'=>$imageMeta['image_review_required'],
          'source'=>$source,
          'source_url'=>$sourceUrl,
          'tags'=>is_array($enriched['tags']??null)?implode(', ',$enriched['tags']):'',
          'seo_title'=>trim((string)($enriched['seo_title']??($enriched['title']??$title))),
          'meta_description'=>trim((string)($enriched['meta_description']??($enriched['summary']??''))),
          'slug'=>trim((string)($enriched['slug']??'')),
          'instagram_caption'=>trim((string)($enriched['instagram_caption']??'')),
          'whatsapp_text'=>trim((string)($enriched['whatsapp_text']??'')),
        ];
        $_SESSION['tvs_quick_news_draft']=$draft;
        $notice=$imageMeta['image_source_type']==='source_og'
          ? 'Enriquecimento concluído e imagem capturada da fonte. Revise a matéria abaixo antes de publicar.'
          : 'Enriquecimento concluído. Revise a matéria e a imagem abaixo antes de publicar.';
      }
    }
  }

  if($action==='publish'){
    $sessionDraft=$_SESSION['tvs_quick_news_draft'] ?? null;
    $token=(string)($_POST['draft_token']??'');
    if(!is_array($sessionDraft) || $token==='' || !hash_equals((string)($sessionDraft['token']??''),$token)){
      $error='Rascunho enriquecido inválido ou expirado. Faça o enriquecimento novamente antes de publicar.';
    } else {
      $title=tvs_quick_post('title');
      $body=tvs_quick_post('body');
      if($title==='' || $body===''){
        $error='Título e texto são obrigatórios para publicação.';
      } else {
        $file=dirname(__DIR__).'/data/noticias.json';
        $arr=tvs_read_json_file($file);
        if(!is_array($arr)) $arr=[];
        $now=date('c');
        $tags=array_values(array_filter(array_map('trim',explode(',',tvs_quick_post('tags')))));
        $finalImage=tvs_quick_post('image');
        $imageChanged=$finalImage!==(string)($sessionDraft['image']??'');
        $finalImageSourceType=$imageChanged?'manual_review':(string)($sessionDraft['image_source_type']??'manual');
        $finalImageCredit=$imageChanged
          ? (function_exists('tvs_image_credit_from_source')?tvs_image_credit_from_source(tvs_quick_post('source','Redação TV Sumaré'),$finalImage):'')
          : (string)($sessionDraft['image_credit']??'');
        $finalImageReviewRequired=$imageChanged?0:(int)($sessionDraft['image_review_required']??0);

        $arr[]=[
          'id'=>uniqid('news_'),
          'title'=>$title,
          'subtitle'=>tvs_quick_post('subtitle'),
          'summary'=>tvs_quick_post('summary'),
          'city'=>tvs_quick_post('city','Sumaré'),
          'category'=>tvs_quick_post('category','Cidades'),
          'body'=>$body,
          'image'=>$finalImage,
          'image_source_type'=>$finalImageSourceType,
          'image_credit'=>$finalImageCredit,
          'image_review_required'=>$finalImageReviewRequired,
          'source'=>tvs_quick_post('source','Redação TV Sumaré'),
          'source_url'=>tvs_quick_post('source_url'),
          'tags'=>$tags,
          'seo_title'=>tvs_quick_post('seo_title',$title),
          'meta_description'=>tvs_quick_post('meta_description'),
          'slug'=>tvs_quick_post('slug'),
          'instagram_caption'=>tvs_quick_post('instagram_caption'),
          'whatsapp_text'=>tvs_quick_post('whatsapp_text'),
          'editorial_mode'=>'NOTICIA_RAPIDA_ENRIQUECIDA',
          'enriched_at'=>$sessionDraft['enriched_at']??$now,
          'views'=>0,
          'shares'=>0,
          'published_at'=>$now,
          'created_at'=>$now
        ];

        if(tvs_save_json_file($file,$arr)){
          unset($_SESSION['tvs_quick_news_draft']);
          $draft=null;
          $notice='Notícia enriquecida e publicada com sucesso.';
        } else {
          $error='Não foi possível gravar noticias.json. Nada deve ser considerado publicado.';
        }
      }
    }
  }

  if($action==='cancel_draft'){
    unset($_SESSION['tvs_quick_news_draft']);
    $draft=null;
    $notice='Rascunho enriquecido descartado.';
  }
}

if(!$draft && isset($_SESSION['tvs_quick_news_draft'])) $draft=$_SESSION['tvs_quick_news_draft'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notícia Rápida | TV Sumaré</title>
<link rel="stylesheet" href="admin.css?v=2.0.5">
<style>
.quick-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.quick-grid .full{grid-column:1/-1}.quick-note{padding:12px;border:1px solid #dbeafe;border-radius:12px;background:#eff6ff}.quick-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.image-meta{font-size:12px;color:#64748b;margin-top:6px}.image-warning{font-size:12px;color:#9a3412;margin-top:6px}@media(max-width:900px){.quick-grid{grid-template-columns:1fr}.quick-grid .full{grid-column:auto}}
</style>
</head>
<body>
<div class="admin">
<?php include __DIR__.'/_menu.php'; ?>
<main class="main">
  <div class="top">
    <div>
      <span class="eyebrow">Redação</span>
      <h1>Notícia Rápida</h1>
      <p class="muted">Fluxo obrigatório: informe o material-base, enriqueça com Gemini, revise e só então publique.</p>
    </div>
    <div class="actions"><a class="btn secondary" href="noticias.php">Ver publicadas</a><a class="btn secondary" href="radar-regional.php">Aprovações</a></div>
  </div>

  <?php if($notice): ?><div class="notice"><?=tvs_quick_h($notice)?></div><?php endif; ?>
  <?php if($error): ?><div class="notice error"><?=tvs_quick_h($error)?></div><?php endif; ?>

  <?php if(!$draft): ?>
  <form class="box" method="post">
    <?=tvs_csrf_field()?>
    <input type="hidden" name="action" value="enrich">
    <div class="quick-note"><strong>Publicação direta desativada.</strong> O Gemini deve estruturar e enriquecer a matéria antes da revisão humana.</div>
    <div class="quick-grid" style="margin-top:14px">
      <div class="full"><label>Título / fato principal</label><input name="title" required value="<?=tvs_quick_h($_POST['title']??'')?>"></div>
      <div class="full"><label>Subtítulo opcional</label><input name="subtitle" value="<?=tvs_quick_h($_POST['subtitle']??'')?>"></div>
      <div><label>Cidade</label><select name="city"><?php foreach(['Sumaré','Hortolândia','Paulínia','Nova Odessa','Americana','Campinas'] as $city): ?><option <?=($city===($_POST['city']??'Sumaré'))?'selected':''?>><?=tvs_quick_h($city)?></option><?php endforeach; ?></select></div>
      <div><label>Categoria sugerida</label><input name="category" value="<?=tvs_quick_h($_POST['category']??'Cidades')?>"></div>
      <div><label>Fonte</label><input name="source" value="<?=tvs_quick_h($_POST['source']??'Redação TV Sumaré')?>"></div>
      <div><label>URL da fonte</label><input name="source_url" value="<?=tvs_quick_h($_POST['source_url']??'')?>"></div>
      <div class="full"><label>Imagem opcional</label><input name="image" placeholder="Deixe vazio para tentar capturar automaticamente da URL da fonte" value="<?=tvs_quick_h($_POST['image']??'')?>"><div class="image-meta">Prioridade neste fluxo: imagem informada manualmente → imagem capturada da fonte → imagem padrão TV Sumaré.</div></div>
      <div class="full"><label>Material-base / informações confirmadas</label><textarea name="body" required style="min-height:260px"><?=tvs_quick_h($_POST['body']??'')?></textarea></div>
    </div>
    <div class="quick-actions"><button class="btn orange" type="submit">Enriquecer com Gemini</button></div>
  </form>
  <?php else: ?>
  <form class="box" method="post">
    <?=tvs_csrf_field()?>
    <input type="hidden" name="action" value="publish">
    <input type="hidden" name="draft_token" value="<?=tvs_quick_h($draft['token']??'')?>">
    <div class="quick-note"><strong>Revisão humana obrigatória.</strong> Confira fatos, nomes, datas, fonte, imagem e texto antes da publicação.</div>
    <div class="quick-grid" style="margin-top:14px">
      <div class="full"><label>Título</label><input name="title" required value="<?=tvs_quick_h($draft['title']??'')?>"></div>
      <div class="full"><label>Subtítulo</label><input name="subtitle" value="<?=tvs_quick_h($draft['subtitle']??'')?>"></div>
      <div class="full"><label>Resumo</label><input name="summary" value="<?=tvs_quick_h($draft['summary']??'')?>"></div>
      <div><label>Cidade</label><input name="city" value="<?=tvs_quick_h($draft['city']??'Sumaré')?>"></div>
      <div><label>Categoria</label><input name="category" value="<?=tvs_quick_h($draft['category']??'Cidades')?>"></div>
      <div><label>Fonte</label><input name="source" value="<?=tvs_quick_h($draft['source']??'')?>"></div>
      <div><label>URL da fonte</label><input name="source_url" value="<?=tvs_quick_h($draft['source_url']??'')?>"></div>
      <div class="full"><label>Imagem</label><input name="image" value="<?=tvs_quick_h($draft['image']??'')?>"><div class="image-meta">Origem: <?=tvs_quick_h($draft['image_source_type']??'manual')?><?php if(!empty($draft['image_credit'])): ?> • <?=tvs_quick_h($draft['image_credit'])?><?php endif; ?></div><?php if(!empty($draft['image_review_required'])): ?><div class="image-warning">Imagem padrão utilizada. Confirme se deseja mantê-la antes de publicar.</div><?php endif; ?></div>
      <div class="full"><label>Texto enriquecido</label><textarea name="body" required style="min-height:360px"><?=tvs_quick_h($draft['body']??'')?></textarea></div>
      <div class="full"><label>Tags</label><input name="tags" value="<?=tvs_quick_h($draft['tags']??'')?>"></div>
      <div><label>SEO title</label><input name="seo_title" value="<?=tvs_quick_h($draft['seo_title']??'')?>"></div>
      <div><label>Slug</label><input name="slug" value="<?=tvs_quick_h($draft['slug']??'')?>"></div>
      <div class="full"><label>Meta description</label><input name="meta_description" value="<?=tvs_quick_h($draft['meta_description']??'')?>"></div>
      <div class="full"><label>Legenda Instagram</label><textarea name="instagram_caption" style="min-height:120px"><?=tvs_quick_h($draft['instagram_caption']??'')?></textarea></div>
      <div class="full"><label>Texto WhatsApp</label><textarea name="whatsapp_text" style="min-height:100px"><?=tvs_quick_h($draft['whatsapp_text']??'')?></textarea></div>
    </div>
    <div class="quick-actions"><button class="btn orange" type="submit">Publicar matéria revisada</button></div>
  </form>
  <form method="post" style="margin-top:10px">
    <?=tvs_csrf_field()?>
    <input type="hidden" name="action" value="cancel_draft">
    <button class="btn secondary" type="submit">Descartar rascunho enriquecido</button>
  </form>
  <?php endif; ?>
</main>
</div>
</body>
</html>