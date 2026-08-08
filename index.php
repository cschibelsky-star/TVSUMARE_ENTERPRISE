<?php
include 'config.php';
require_once __DIR__.'/includes/tvs_public_helpers.php';
$active='home';
$siteSettings=tvs_json('data/site_settings.json');
$news=tvs_json('data/noticias.json');
$news=function_exists('tvs_prepare_public_news_v2') ? tvs_prepare_public_news_v2((is_array($news)?$news:[]), 21) : tvs_prepare_public_news(is_array($news)?$news:[], 30);
$used=[];
$editorialSections=function_exists('tvs_curated_sections') ? tvs_curated_sections($news) : [];
$heroList=tvs_pick_news($news,$used,function($n){ return !tvs_is_sensitive($n); },1);
$hero=$heroList[0]??($news[0]??null); if($hero){ $used[(string)($hero['id']??md5($hero['title']??''))]=1; }
$secondary=[]; $sideCats=[];
foreach($news as $cand){
  $cat=tvs_news_category($cand);
  $id=(string)($cand['id']??md5($cand['title']??json_encode($cand))); $tk='t:'.substr(tvs_norm_key($cand['title']??''),0,86);
  if(isset($used[$id])||isset($used[$tk])||tvs_is_sensitive($cand)) continue;
  if(isset($sideCats[tvs_lc($cat)]) && count($secondary)<3) continue;
  $used[$id]=1; $used[$tk]=1; $sideCats[tvs_lc($cat)]=1; $secondary[]=$cand;
  if(count($secondary)>=4) break;
}
$ultimas=tvs_public_breaking(tvs_json('data/ultimahora.json'), 7, 12);
if(!$ultimas){ foreach(array_slice($news,0,12) as $n){ $ultimas[]=['title'=>tvs_clean_source_suffix($n['title']??''), 'url'=>tvs_news_url($n), 'source'=>'TV Sumaré', 'time'=>date('H:i',tvs_date_ts($n)), 'created_at'=>$n['published_at']??$n['created_at']??date('c')]; } }
$videos=tvs_load_real_videos(4);
$latest=tvs_pick_news($news,$used,null,9);
$empregos=tvs_pick_news($news,$used,function($n){ return tvs_category_match($n,['emprego','vagas','oportunidade','economia','empresa','negócios','negocios']); },6);
$cities=['Sumaré','Hortolândia','Paulínia','Nova Odessa','Americana','Campinas']; $byCity=[];
foreach($cities as $city){ $items=tvs_pick_news($news,$used,function($n)use($city){ return strcasecmp((string)(tvs_infer_city($n)?:($n['city']??'')),$city)===0; },1); if($items) $byCity[$city]=$items[0]; }
$popular=$news; usort($popular,function($a,$b){ return (int)($b['views']??0)<=>(int)($a['views']??0); });
$popularUsed=[]; $popular=array_values(array_filter($popular,function($n)use(&$popularUsed){ $id=(string)($n['id']??md5($n['title']??'')); if(isset($popularUsed[$id])) return false; $popularUsed[$id]=1; return true; })); $popular=array_slice($popular,0,6);
$empresas=tvs_json('data/empresas.json'); $empresas=is_array($empresas)?array_slice(array_reverse($empresas),0,4):[];
function tvs_card_img_html($n,$class=''){
  $img=tvs_display_image($n);
  return '<img class="'.tvs_h($class).'" src="'.tvs_h($img).'" onerror="this.src=\'assets/tvsumare-noticia-padrao.svg\'" alt="">';
}
function tvs_video_thumb_html($v){
  $thumb=tvs_video_thumb($v);
  if($thumb==='') return '<div class="video-thumb-placeholder">▶</div>';
  return '<img src="'.tvs_h($thumb).'" onerror="this.parentNode.innerHTML=\'<div class=&quot;video-thumb-placeholder&quot;>▶</div>\'" alt="">';
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="TV Sumaré — notícias, vídeos, empregos, guia comercial e cobertura regional."><meta property="og:title" content="TV Sumaré | Portal Regional"><meta property="og:description" content="Notícias de Sumaré, Paulínia, Hortolândia, Nova Odessa, Americana e Campinas."><meta property="og:image" content="<?=tvs_h(rtrim($site_url??'', '/').'/assets/logo-tv-sumare.jpeg')?>"><meta property="og:type" content="website"><title>TV Sumaré | Portal Regional</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css?v=homologacao-final"><link rel="stylesheet" href="assets/tvsumare-final-fixes.css?v=3"><link rel="icon" href="assets/logo-tv-sumare.jpeg"><style>.news-no-image{display:flex;align-items:center;justify-content:center;min-height:80px;background:linear-gradient(135deg,#0f2f68,#1d4ed8);color:#fff;font-weight:900;text-align:center;border-radius:18px;padding:12px}.hero-main-news.no-real-image{background:linear-gradient(180deg,rgba(6,26,77,.15),rgba(6,26,77,.96)),linear-gradient(135deg,#0f2f68,#123c8c)!important}.ad-banner.clean{min-height:92px}.city-news-grid.compact{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}.home-video-list .home-video-item{min-height:100px}.side-news-card img,.side-news-card .news-no-image{width:120px;height:82px;object-fit:cover;flex:0 0 120px}.home-section-hidden{display:none!important}</style></head><body>
<?php include 'header.php'; ?>
<main><section class="container home-editorial">
  <div class="trend-row"><strong>EM ALTA</strong><a href="noticias.php?categoria=Cidade">Cidade</a><a href="noticias.php?categoria=Empregos">Empregos</a><a href="noticias.php?categoria=Economia">Negócios</a><a href="noticias.php?categoria=Saúde">Saúde</a><a href="noticias.php?categoria=Educação">Educação</a><a href="noticias.php?categoria=Cultura">Cultura</a></div>

  <?php if($hero): $himg=tvs_display_image($hero); ?>
  <section class="editorial-hero">
    <a class="hero-main-news <?=$himg===''?'no-real-image':''?>" href="<?=tvs_h(tvs_news_url($hero))?>" style="background-image:linear-gradient(180deg,rgba(6,26,77,.10),rgba(6,26,77,.94)),url('<?=tvs_h($himg)?>')">
      <div><span class="category-tag"><?=tvs_h($hero['category']??'Notícia')?></span> <?php if(!empty($hero['city'])):?><span class="category-tag"><?=tvs_h($hero['city'])?></span><?php endif; ?></div>
      <h1><?=tvs_h(tvs_title($hero['title']??'Notícia principal',86))?></h1><p><?=tvs_h($hero['subtitle']??tvs_excerpt_clean($hero['summary']??$hero['body']??'',190))?></p><div class="meta">⏱ <?=tvs_read_time($hero['body']??'')?> min • TV Sumaré</div>
    </a>
    <?php if($secondary): ?><div class="hero-side-news"><?php foreach($secondary as $s): ?><a class="side-news-card" href="<?=tvs_h(tvs_news_url($s))?>"><?php $si=tvs_card_img_html($s); if($si) echo $si; ?><div><span><?=tvs_h((tvs_infer_city($s)?:'Região').' • '.($s['category']??'Notícia'))?></span><h2><?=tvs_h(tvs_title($s['title']??'Sem título',74))?></h2></div></a><?php endforeach; ?></div><?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="brand-strip-enterprise" aria-label="Tecnologia">
    <strong>TV Sumaré Enterprise</strong>
    <span>Tecnologia by Vitrine AI Pro</span>
    <em>Sumaré • Hortolândia • Paulínia • Nova Odessa • Americana • Campinas</em>
  </section>

  <div class="ad-banner clean"><span>ANUNCIE NA TV SUMARÉ</span><strong>Sua marca em destaque para Sumaré, Hortolândia, Nova Odessa, Paulínia, Americana e Campinas</strong><a href="anuncie.php">Conheça os planos</a></div>

  <section class="home-live-video-grid">
    <div class="home-live-card"><div class="section-kicker">🔴 Ao Vivo</div><h2><?=tvs_h($siteSettings['live_title']??'TV Sumaré Ao Vivo')?></h2><?php $embed=trim((string)($siteSettings['live_embed']??'')); if($embed && stripos($embed,'<iframe')!==false): ?><div class="home-live-embed"><?=$embed?></div><?php else: ?><div class="home-live-placeholder"><span class="pulse-dot"></span><strong>Nenhuma transmissão programada</strong><p>Assista aos últimos boletins da TV Sumaré Play enquanto a próxima transmissão é preparada.</p></div><?php endif; ?><p><?=tvs_h($siteSettings['live_description']??'Acompanhe boletins, entrevistas e transmissões especiais da TV Sumaré.')?></p><div class="home-actions"><a class="btn btn-primary" href="aovivo.php">Ver programação</a><a class="btn btn-outline" href="videos.php">Últimos vídeos</a></div></div>
    <?php if($videos): ?><div class="home-videos-card"><div class="section-heading compact"><h2>🎥 TV Sumaré Play</h2><a href="videos.php">Ver todos</a></div><div class="home-video-list"><?php foreach($videos as $v): ?><a class="home-video-item" href="videos.php"><span><?=tvs_video_thumb_html($v)?></span><div><span><?=tvs_h(($v['city']??'Região').' • '.($v['category']??'Vídeo'))?></span><strong><?=tvs_h(tvs_title($v['title']??'Vídeo TV Sumaré',82))?></strong><p><?=tvs_h(tvs_excerpt($v['description']??'Conteúdo em vídeo da TV Sumaré.',105))?></p></div></a><?php endforeach; ?></div></div><?php endif; ?>
  </section>

  <section class="content-layout"><div>
    <?php if($latest): ?><div class="section-heading"><h2>Últimas Notícias</h2><a href="noticias.php">Ver todas</a></div><div class="news-grid news-grid--wide"><?php foreach($latest as $n): ?><article class="news-card"><div class="news-thumb"><?=tvs_card_img_html($n)?></div><div class="news-body"><span class="category-tag"><?=tvs_h($n['category']??'Notícia')?></span><?php if(!empty($n['city'])):?><span class="category-tag"><?=tvs_h($n['city'])?></span><?php endif; ?><h3><?=tvs_h(tvs_title($n['title']??'Sem título',88))?></h3><p><?=tvs_h($n['subtitle']??tvs_excerpt_clean($n['summary']??$n['body']??'',120))?></p><a class="btn" href="<?=tvs_h(tvs_news_url($n))?>">Ler matéria</a></div></article><?php endforeach; ?></div><?php endif; ?>

    <?php if($empregos): ?><div class="section-heading spaced"><h2>💼 Empregos e Oportunidades</h2><a href="noticias.php?categoria=Empregos">Ver vagas</a></div><div class="opportunity-grid"><?php foreach($empregos as $n): ?><a href="<?=tvs_h(tvs_news_url($n))?>"><span><?=tvs_h($n['city']??'Região')?></span><strong><?=tvs_h(tvs_title($n['title']??'Oportunidade',86))?></strong><p><?=tvs_h(tvs_excerpt_clean($n['subtitle']??$n['summary']??$n['body']??'',120))?></p></a><?php endforeach; ?></div><?php endif; ?>

    <?php if($byCity): ?><div class="section-heading spaced"><h2>Destaques por Cidade</h2><a href="noticias.php">Cobertura regional</a></div><div class="city-news-grid compact"><?php foreach($byCity as $city=>$n): ?><section class="city-news-box"><h3><?=tvs_h($city)?></h3><a href="<?=tvs_h(tvs_news_url($n))?>"><?=tvs_card_img_html($n)?><span><?=tvs_h(tvs_title($n['title']??'Sem título',78))?></span></a></section><?php endforeach; ?></div><?php endif; ?>

    <?php if($empresas): ?><div class="section-heading spaced"><h2>Guia Comercial em Destaque</h2><a href="guia.php">Ver guia</a></div><div class="home-business-strip"><?php foreach($empresas as $e): ?><article><?php if(!empty($e['image'])):?><img src="<?=tvs_h($e['image'])?>" onerror="this.style.display='none'" alt=""><?php endif; ?><strong><?=tvs_h($e['name']??$e['empresa']??'Empresa')?></strong><p><?=tvs_h($e['category']??$e['categoria']??'Guia Comercial')?></p><?php if(!empty($e['whatsapp'])):?><a href="https://wa.me/<?=preg_replace('/\D+/','',$e['whatsapp'])?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
  </div>

  <aside class="sidebar"><div class="widget"><div class="widget-title">🔥 Mais Lidas</div><ol class="rank-list"><?php foreach($popular as $p): ?><li><a href="<?=tvs_h(tvs_news_url($p))?>"><?=tvs_h(tvs_title($p['title']??'Sem título',88))?></a></li><?php endforeach; ?></ol></div><div class="widget"><div class="widget-title">📢 Anuncie Aqui</div><p>Sua empresa pode aparecer em banners, matérias e vídeos da TV Sumaré.</p><a href="anuncie.php" class="btn btn-outline">Conheça os planos</a></div></aside>
  </section>
</section></main>
<?php if(!empty($editorialSections)): ?>
<section class="container tvs-sections-20 tvs-sections-modern">
  <div class="section-heading modern-heading"><div><span>Editorias</span><h2>Notícias por assunto</h2><p>Cobertura regional organizada por temas, com identidade TV Sumaré.</p></div><a href="noticias.php">Ver todas</a></div>
  <div class="tvs-topic-grid">
  <?php foreach($editorialSections as $secName=>$items): if(empty($items)) continue; $main=$items[0]; $img=tvs_display_image($main); if($img==='') $img='assets/tvsumare-noticia-padrao.svg'; ?>
    <article class="tvs-topic-card">
      <a class="tvs-topic-image" href="<?=tvs_h(tvs_news_url($main))?>"><img src="<?=tvs_h($img)?>" onerror="this.src='assets/tvsumare-noticia-padrao.svg'" alt=""></a>
      <div class="tvs-topic-content">
        <h3><?=tvs_h($secName)?><span>.</span></h3>
        <a class="tvs-topic-title" href="<?=tvs_h(tvs_news_url($main))?>"><?=tvs_h(tvs_title($main['title']??'',82))?></a>
        <?php foreach(array_slice($items,1,2) as $mini): ?>
          <a class="tvs-topic-mini" href="<?=tvs_h(tvs_news_url($mini))?>"><?=tvs_h(tvs_title($mini['title']??'',74))?></a>
        <?php endforeach; ?>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php include 'rodape.php'; ?></body></html>
