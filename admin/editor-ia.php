<?php
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once __DIR__.'/monitor_lib.php';

$activeAdmin='editor_ia';
$notice=''; $error=''; $result=null; $sourcesFound=[];
$fontesFile=dirname(__DIR__).'/data/fontes.json';
$fontes=tvs_read_json_file($fontesFile);

$cities=['Sumaré','Hortolândia','Paulínia','Nova Odessa','Americana','Campinas','Região'];
$categories=['Cidade','Política','Saúde','Segurança','Educação','Esportes','Cultura','Empregos','Economia','Defesa Civil','Guia Comercial'];

function tvs_reporter_fetch_feed($url,$limit=6){
  $xml=tvs_fetch_url($url); if(!$xml) return [];
  $items=[];
  if(preg_match_all('~<item\b[^>]*>(.*?)</item>~is',$xml,$m)){
    foreach($m[1] as $block){
      preg_match('~<title[^>]*>(.*?)</title>~is',$block,$tm);
      preg_match('~<link[^>]*>(.*?)</link>~is',$block,$lm);
      preg_match('~<description[^>]*>(.*?)</description>~is',$block,$dm);
      $title=tvs_clean_text($tm[1]??''); $link=tvs_clean_text($lm[1]??''); $desc=tvs_clean_text($dm[1]??'');
      if(!$title || !$link || tvs_is_boilerplate($title)) continue;
      $items[]=['title'=>$title,'url'=>$link,'description'=>$desc,'source'=>'RSS/Busca pública'];
      if(count($items)>=$limit) break;
    }
  }
  return $items;
}
function tvs_reporter_google_news($city,$theme,$category){
  $q=trim($theme.' '.$city.' '.$category.' Sumaré Hortolândia Paulínia Americana Campinas');
  $url='https://news.google.com/rss/search?q='.urlencode($q).'&hl=pt-BR&gl=BR&ceid=BR:pt-419';
  return tvs_reporter_fetch_feed($url,8);
}
function tvs_reporter_source_candidates($fontes,$city,$theme,$category){
  $out=[];
  foreach($fontes as $f){
    if(isset($f['active']) && !$f['active']) continue;
    $fcity=$f['city']??'';
    if($city && $city!=='Região' && $fcity && tvs_lower($fcity)!==tvs_lower($city) && !preg_match('/São Paulo|Região/i',$fcity)) continue;
    if(!empty($f['rss'])){
      foreach(tvs_reporter_fetch_feed($f['rss'],5) as $it){ $it['source']=$f['name']??'Fonte cadastrada'; $out[]=$it; }
    }
    if(!empty($f['url'])){
      $html=tvs_fetch_url($f['url']);
      foreach(tvs_extract_links($f['url'],$html) as $link){
        $hay=tvs_lower(($link['title']??'').' '.$theme.' '.$category);
        $score=$link['score']??0;
        foreach(preg_split('/\s+/u',tvs_lower($theme.' '.$category)) as $term){ if(tvs_strlen($term)>3 && strpos($hay,$term)!==false) $score+=2; }
        $out[]=['title'=>$link['title'],'url'=>$link['url'],'description'=>'','source'=>$f['name']??'Fonte cadastrada','score'=>$score];
      }
    }
  }
  usort($out,function($a,$b){ return ($b['score']??0)<=>($a['score']??0); });
  $unique=[];$seen=[];
  foreach($out as $it){ if(empty($it['url'])||isset($seen[$it['url']])) continue; $seen[$it['url']]=1; $unique[]=$it; if(count($unique)>=8) break; }
  return $unique;
}
function tvs_reporter_collect_articles($candidates,$city,$theme,$category){
  $articles=[];
  foreach($candidates as $cand){
    $a=tvs_extract_article($cand['url'],$cand['title']??'');
    $text=trim(($a['description']??'')."\n\n".($a['body']??''));
    if(tvs_strlen($text)<140){
      $text=$cand['description']??'';
    }
    if(tvs_strlen($text)<90) continue;
    $articles[]=[
      'title'=>$a['title'] ?: ($cand['title']??''),
      'url'=>$a['url'] ?: ($cand['url']??''),
      'source'=>$cand['source']??'Fonte consultada',
      'image'=>$a['image']??'',
      'text'=>tvs_substr(tvs_remove_editorial_artifacts($text),0,3500)
    ];
    if(count($articles)>=4) break;
  }
  return $articles;
}
function tvs_reporter_material($city,$theme,$category,$articles){
  $parts=[];
  $parts[]="Cidade prioritária: {$city}";
  $parts[]="Tema solicitado pelo editor: {$theme}";
  $parts[]="Categoria: {$category}";
  $i=1;
  foreach($articles as $a){
    $parts[]="\nFONTE {$i}: ".($a['source']??'Fonte consultada')."\nTítulo original: ".($a['title']??'')."\nURL: ".($a['url']??'')."\nConteúdo apurado:\n".($a['text']??'');
    $i++;
  }
  return implode("\n",$parts);
}
function tvs_reporter_fallback($city,$theme,$category,$articles){
  $first=$articles[0]??[];
  $title=trim($theme) ? ucfirst($theme).' movimenta pauta regional em '.$city : (($first['title']??'Notícia regional') ?: 'Notícia regional');
  $subtitle='Informações foram apuradas em fontes públicas e organizadas em formato jornalístico para revisão editorial.';
  $paras=[];
  foreach($articles as $a){
    $txt=tvs_remove_editorial_artifacts($a['text']??'');
    $chunks=preg_split('/\n{2,}|(?<=[.!?])\s+/u',$txt);
    foreach($chunks as $c){ $c=trim($c); if(tvs_strlen($c)>80 && !tvs_is_boilerplate($c)) $paras[]=$c; if(count($paras)>=6) break 2; }
  }
  if(!$paras) $paras[]='O tema informado pelo editor foi localizado em fontes públicas da região, mas o sistema não conseguiu extrair detalhes suficientes para uma reportagem completa. Revise a fonte antes de publicar.';
  $body=implode("\n\n",array_slice($paras,0,7));
  return ['title'=>$title,'subtitle'=>$subtitle,'summary'=>tvs_substr($paras[0]??$subtitle,0,180),'body'=>$body,'category'=>$category?:'Cidade','tags'=>[$city,$category,'TV Sumaré'],'seo_title'=>$title,'meta_description'=>tvs_substr($paras[0]??$subtitle,0,155),'slug'=>tvs_slug($title),'instagram_caption'=>$title."\n\nConfira a matéria no portal da TV Sumaré.",'whatsapp_text'=>$title."\n\nLeia no portal da TV Sumaré."];
}
function tvs_reporter_publish($data){
  $nf=dirname(__DIR__).'/data/noticias.json'; $news=tvs_read_json_file($nf);
  $id=uniqid('news_');
  $title=trim($data['title']??''); $body=trim($data['body']??'');
  if($title==='' || $body==='') return false;
  $item=['id'=>$id,'title'=>$title,'subtitle'=>trim($data['subtitle']??''),'summary'=>trim($data['summary']??''),'body'=>$body,'category'=>trim($data['category']??'Cidade'),'city'=>trim($data['city']??'Região'),'source'=>trim($data['source']??'Fontes consultadas'),'source_url'=>trim($data['source_url']??''),'image'=>trim($data['image']??''),'tags'=>array_values(array_filter(array_map('trim',explode(',',is_array($data['tags']??null)?implode(',',$data['tags']):($data['tags']??''))))),'seo_title'=>trim($data['seo_title']??$title),'meta_description'=>trim($data['meta_description']??($data['summary']??'')),'slug'=>trim($data['slug']??tvs_slug($title)),'instagram_caption'=>trim($data['instagram_caption']??''),'whatsapp_text'=>trim($data['whatsapp_text']??''),'published_at'=>date('c'),'created_at'=>date('c')];
  $news[]=$item; tvs_save_json_file($nf,array_values($news)); return $id;
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  $action=$_POST['action']??'search_generate';
  if($action==='publish'){
    $id=tvs_reporter_publish($_POST);
    if($id){ header('Location: noticias.php?published=1'); exit; }
    $error='Não foi possível publicar. Confira se título e texto completo estão preenchidos.';
  } else {
    $city=trim($_POST['city']??'Sumaré'); $theme=trim($_POST['theme']??''); $category=trim($_POST['category']??'Cidade');
    if($theme==='') $error='Informe um tema para o Repórter IA pesquisar e produzir a reportagem.';
    else {
      $candidates=array_merge(tvs_reporter_source_candidates($fontes,$city,$theme,$category), tvs_reporter_google_news($city,$theme,$category));
      $seen=[]; $merged=[];
      foreach($candidates as $c){ if(empty($c['url'])||isset($seen[$c['url']])) continue; $seen[$c['url']]=1; $merged[]=$c; if(count($merged)>=10) break; }
      $sourcesFound=$merged;
      $articles=tvs_reporter_collect_articles($merged,$city,$theme,$category);
      if(!$articles){ $error='Nenhuma fonte com conteúdo suficiente foi encontrada. Tente um tema mais específico ou cadastre uma fonte oficial com página de notícias/RSS.'; }
      else {
        $material=tvs_reporter_material($city,$theme,$category,$articles);
        $result=gemini_reporter_article($gemini_api_key??'', $material, ['city'=>$city,'theme'=>$theme,'category'=>$category]);
        if(!$result) $result=tvs_reporter_fallback($city,$theme,$category,$articles);
        $result=tvs_sanitize_ai_article($result,['city'=>$city,'name'=>'Fontes consultadas'],['title'=>$theme,'description'=>$material,'body'=>$material,'url'=>$articles[0]['url']??'']);
        $result['city']=$city; $result['category']=$result['category'] ?: $category; $result['image']=$articles[0]['image']??'';
        $result['source']='Fontes consultadas'; $result['source_url']=$articles[0]['url']??'';
        $result['sources_json']=json_encode(array_map(function($a){return ['title'=>$a['title']??'', 'source'=>$a['source']??'', 'url'=>$a['url']??''];},$articles),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $notice='Reportagem profissional gerada. Revise, edite se necessário e publique.';
      }
    }
  }
}
function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function val($name,$default=''){ global $result; return h($_POST[$name] ?? ($result[$name] ?? $default)); }
$body=$_POST['body']??($result['body']??'');
$tags=$_POST['tags']??(isset($result['tags'])?(is_array($result['tags'])?implode(', ',$result['tags']):$result['tags']):'');
$sourcesJson=$_POST['sources_json']??($result['sources_json']??'');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Repórter IA | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=60"><style>.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.textarea-large{min-height:360px}.hint{color:#64748b;font-size:13px}.source-list{display:grid;gap:8px;margin-top:12px}.source-item{border:1px solid #e2e8f0;background:#f8fafc;border-radius:12px;padding:10px;font-size:13px}.actions{display:flex;gap:10px;flex-wrap:wrap}@media(max-width:900px){.form-grid{grid-template-columns:1fr}}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Redação automatizada com revisão humana</span><h1>Repórter IA TV Sumaré</h1><p class="hint">Informe cidade, tema e categoria. A IA pesquisa fontes públicas/cadastradas, produz uma reportagem profissional e você decide se publica.</p></div><a class="btn secondary" href="fontes.php">Fontes Oficiais</a></div><?php if($notice): ?><div class="notice"><?=h($notice)?></div><?php endif; ?><?php if($error): ?><div class="notice error"><?=h($error)?></div><?php endif; ?>
<div class="box"><form method="post" class="form"><?=tvs_csrf_field()?><h2>1. Pesquisar e produzir reportagem</h2><div class="form-grid"><div><label>Cidade</label><select name="city"><?php foreach($cities as $c): ?><option <?=($_POST['city']??'Sumaré')===$c?'selected':''?>><?=h($c)?></option><?php endforeach; ?></select></div><div><label>Categoria</label><select name="category"><?php foreach($categories as $c): ?><option <?=($_POST['category']??'Cidade')===$c?'selected':''?>><?=h($c)?></option><?php endforeach; ?></select></div><div><label>Tema</label><input name="theme" placeholder="Ex.: HORTOCOPA, saúde, obras, vagas de emprego" value="<?=h($_POST['theme']??'')?>"></div></div><p class="hint">Padrão fixo: profissional jornalístico. Sem profundidade, sem rascunhos complexos e sem texto base manual.</p><button class="btn orange" name="action" value="search_generate">Pesquisar e Produzir Reportagem</button>
<?php if($sourcesFound): ?><div class="field-card"><h3>Fontes encontradas</h3><div class="source-list"><?php foreach(array_slice($sourcesFound,0,6) as $s): ?><div class="source-item"><b><?=h($s['title']??'Fonte')?></b><br><span><?=h($s['source']??'Fonte consultada')?></span><br><a href="<?=h($s['url']??'#')?>" target="_blank">Ver fonte</a></div><?php endforeach; ?></div></div><?php endif; ?>
<hr style="border:0;border-top:1px solid #e3e8f2;margin:24px 0"><h2>2. Revisar e publicar</h2><input type="hidden" name="sources_json" value="<?=h($sourcesJson)?>"><input type="hidden" name="source" value="<?=val('source','Fontes consultadas')?>"><input type="hidden" name="source_url" value="<?=val('source_url')?>"><label>Título</label><input name="title" value="<?=val('title')?>"><label>Subtítulo</label><input name="subtitle" value="<?=val('subtitle')?>"><label>Resumo curto</label><input name="summary" value="<?=val('summary')?>"><label>Imagem destacada / URL</label><input name="image" value="<?=val('image')?>"><label>Texto completo</label><textarea class="textarea-large" name="body"><?=h($body)?></textarea><div class="form-grid"><div><label>SEO Title</label><input name="seo_title" value="<?=val('seo_title')?>"></div><div><label>Slug</label><input name="slug" value="<?=val('slug')?>"></div><div><label>Tags</label><input name="tags" value="<?=h($tags)?>"></div></div><label>Meta description</label><input name="meta_description" value="<?=val('meta_description')?>"><label>Legenda Instagram</label><textarea name="instagram_caption"><?=val('instagram_caption')?></textarea><label>Texto WhatsApp</label><textarea name="whatsapp_text"><?=val('whatsapp_text')?></textarea><div class="actions"><button class="btn" name="action" value="publish" onclick="return confirm('Publicar esta reportagem no portal?')">Publicar</button><button class="btn secondary" type="button" onclick="document.querySelector('[name=body]').focus()">Editar texto</button><a class="btn danger" href="editor-ia.php">Excluir/limpar</a></div></form></div></main></div></body></html>
