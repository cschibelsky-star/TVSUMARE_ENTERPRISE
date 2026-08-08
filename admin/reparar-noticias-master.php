<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once dirname(__DIR__).'/includes/tvs_public_helpers.php';
function tvs_master_save_json($path,$data){ file_put_contents($path,json_encode(array_values($data),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
$base=dirname(__DIR__);
$newsFile=$base.'/data/noticias.json';
$lixeiraFile=$base.'/data/lixeira_noticias.json';
$ultFile=$base.'/data/ultimahora.json';
$news=tvs_json($newsFile); $oldNews=count($news);
$clean=[]; $trash=[]; $seen=[];
foreach((array)$news as $n){
  $n=tvs_normalize_news_item($n);
  if(tvs_is_news_old($n,21)){ $n['motivo_lixeira']='Matéria antiga acima de 30 dias'; $trash[]=$n; continue; }
  if(function_exists('tvs_is_regional_news_strict') ? !tvs_is_regional_news_strict($n) : !tvs_news_has_region($n)){ $n['motivo_lixeira']='Fora da região monitorada'; $trash[]=$n; continue; }
  $url=tvs_norm_key($n['source_url']??$n['url']??$n['link']??'');
  $tk=tvs_strict_title_key($n['title']??'');
  $sig=md5(tvs_lc($n['city']??'').'|'.$tk);
  $keys=array_filter([$url?'url:'.$url:'',$tk?'sig:'.$sig:'']);
  $dup=false; foreach($keys as $k){ if(isset($seen[$k])){ $dup=true; break; } }
  if($dup){ $n['motivo_lixeira']='Duplicada por URL ou título similar'; $trash[]=$n; continue; }
  foreach($keys as $k){ $seen[$k]=1; }
  $img=tvs_real_image($n);
  if($img===''){
    $n['image']='assets/tvsumare-noticia-padrao.svg';
    $n['image_credit']='Imagem institucional: TV Sumaré';
  }
  $clean[]=$n;
}
usort($clean,'tvs_sort_recent');
tvs_master_save_json($newsFile,$clean);
$lixeira=tvs_json($lixeiraFile); if(!is_array($lixeira)) $lixeira=[]; $lixeira=array_slice(array_merge($lixeira,$trash),-500); tvs_master_save_json($lixeiraFile,$lixeira);
$ult=tvs_public_breaking(tvs_json($ultFile),7,12); tvs_master_save_json($ultFile,$ult);
?><!doctype html><html lang="pt-br"><head><meta charset="utf-8"><title>Reparo Notícias Master</title><link rel="stylesheet" href="admin.css"></head><body><div class="main"><div class="box"><h1>Reparo Notícias Master concluído</h1><p><b>Notícias antes:</b> <?=htmlspecialchars($oldNews)?></p><p><b>Notícias mantidas:</b> <?=count($clean)?></p><p><b>Movidas para lixeira:</b> <?=count($trash)?></p><p><b>Últimas notícias ativas:</b> <?=count($ult)?></p><a class="btn" href="noticias.php">Ver notícias publicadas</a> <a class="btn" href="../noticias.php" target="_blank">Ver portal</a></div></div></body></html>
