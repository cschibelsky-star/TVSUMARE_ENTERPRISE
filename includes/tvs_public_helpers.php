<?php
if(!function_exists('tvs_h')){
function tvs_h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function tvs_json($file){ if(!file_exists($file)) return []; $d=json_decode(file_get_contents($file),true); return is_array($d)?$d:[]; }
function tvs_lc($s){ return function_exists('mb_strtolower')?mb_strtolower((string)$s,'UTF-8'):strtolower((string)$s); }
function tvs_date_ts($n){ return strtotime($n['published_at']??$n['created_at']??$n['date']??'now') ?: time(); }
function tvs_sort_recent($a,$b){ return tvs_date_ts($b)<=>tvs_date_ts($a); }
function tvs_words($text){ return str_word_count(strip_tags((string)$text),0,'áàâãéèêíïóôõöúçñÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ'); }
function tvs_read_time($body){ return max(1,(int)ceil(tvs_words($body)/220)); }
function tvs_excerpt($text,$max=150){ $text=trim(preg_replace('/\s+/',' ',strip_tags((string)$text))); if(function_exists('mb_strimwidth')) return mb_strimwidth($text,0,$max,'...','UTF-8'); return strlen($text)>$max?substr($text,0,$max).'...':$text; }
function tvs_title($title,$max=92){ $title=trim((string)$title); if(function_exists('mb_strimwidth')) return mb_strimwidth($title,0,$max,'...','UTF-8'); return strlen($title)>$max?substr($title,0,$max).'...':$title; }
function tvs_news_url($n){ return 'noticia.php?id='.urlencode($n['id']??''); }
function tvs_norm_key($s){ $s=tvs_lc(strip_tags((string)$s)); $s=preg_replace('/[^a-z0-9áàâãéèêíïóôõöúçñ]+/u',' ', $s); return trim(preg_replace('/\s+/',' ', $s)); }
function tvs_infer_city($n){
  $city=trim((string)($n['city']??'')); if($city!=='') return $city;
  $txt=tvs_lc(($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??'').' '.($n['source']??''));
  $cities=['Sumaré','Hortolândia','Paulínia','Nova Odessa','Americana','Campinas'];
  foreach($cities as $c){ if(strpos($txt,tvs_lc($c))!==false) return $c; }
  return '';
}
function tvs_is_sensitive($n){
  $txt=tvs_lc(($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??'').' '.($n['category']??''));
  $terms=['suicídio','suicidio','feminicídio','feminicidio','homicídio','homicidio','assassinato','morte de criança','bebê','bebe','criança morta','violência infantil','violencia infantil','abuso','estupro','tráfico','trafico','latrocínio','latrocinio','tiros','tiro','morre baleada','morto','morta','prisão','preso'];
  foreach($terms as $t){ if(strpos($txt,$t)!==false) return true; }
  return false;
}
function tvs_is_category_asset($img){ return (bool)preg_match('~(^|/)assets/cat-|placeholder|sprite|icon|icone|logo-tv-sumare|sem-imagem|default~i',(string)$img); }
function tvs_real_image($n){
  $candidates=[];
  foreach(['image','og_image','rss_image','media','media_url','thumbnail','thumb','featured_image','image_url'] as $k){ if(!empty($n[$k])) $candidates[]=$n[$k]; }
  foreach($candidates as $img){ $img=trim((string)$img); if($img!=='' && !tvs_is_category_asset($img)) return $img; }
  return '';
}
function tvs_display_image($n){
  $img=tvs_real_image($n);
  return $img!=='' ? $img : 'assets/tvsumare-noticia-padrao.svg';
}
function tvs_clean_text($text){
  $text=trim(preg_replace('/\s+/',' ',strip_tags((string)$text)));
  $text=preg_replace('/(G1|Portal ON|sampi\.net\.br|Hora Campinas|CNN Brasil|R7|UOL)\s*$/iu','',$text);
  $text=preg_replace('/\s+[-–—|]\s*(G1|Portal ON|sampi\.net\.br|Hora Campinas|CNN Brasil|R7|UOL)\s*$/iu','',$text);
  return trim($text);
}
function tvs_excerpt_clean($text,$max=150){ return tvs_excerpt(tvs_clean_text($text),$max); }
function tvs_img_abs($img,$site_url=''){
  $img=trim((string)$img); if($img==='') return '';
  if(preg_match('~^https?://~i',$img)) return $img;
  return rtrim((string)$site_url,'/').'/'.ltrim($img,'/');
}
function tvs_dedupe_news($news){
  $out=[]; $seen=[];
  foreach($news as $n){
    $id=(string)($n['id']??'');
    $url=tvs_norm_key($n['url']??$n['link']??$n['source_url']??'');
    $titleKey=substr(tvs_norm_key($n['title']??''),0,92);
    $sumKey=substr(tvs_norm_key(($n['summary']??'').' '.($n['subtitle']??'')),0,90);
    if($titleKey==='') continue;
    $keys=array_filter([$id!==''?'id:'.$id:'', $url!==''?'u:'.$url:'', 't:'.$titleKey, $sumKey!==''?'s:'.$sumKey:'']);
    $dup=false; foreach($keys as $k){ if(isset($seen[$k])){ $dup=true; break; } }
    if($dup) continue;
    foreach($keys as $k){ $seen[$k]=1; }
    $out[]=$n;
  }
  return $out;
}
function tvs_pick_news(&$pool,&$used,$filter=null,$limit=1){
  $out=[];
  foreach($pool as $n){
    $id=(string)($n['id']??md5($n['title']??json_encode($n)));
    $titleKey='t:'.substr(tvs_norm_key($n['title']??''),0,86);
    if(isset($used[$id]) || isset($used[$titleKey])) continue;
    if($filter && !$filter($n)) continue;
    $used[$id]=1; $used[$titleKey]=1; $out[]=$n;
    if(count($out)>=$limit) break;
  }
  return $out;
}
function tvs_category_match($n,$terms){
  $txt=tvs_lc(($n['category']??'').' '.($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??''));
  foreach((array)$terms as $t){ if(strpos($txt,tvs_lc($t))!==false) return true; }
  return false;
}
function tvs_news_category($n){ return trim((string)($n['category']??'Notícia')); }
function tvs_video_url($v){ return trim((string)($v['url']??$v['video_url']??$v['captioned_video_url']??$v['videoUrl']??'')); }
function tvs_video_thumb($v){ $img=trim((string)($v['thumb']??$v['thumbnail']??$v['image']??'')); return tvs_is_category_asset($img)?'':$img; }
function tvs_load_real_videos($limit=0){
  $raw=[];
  foreach(['data/videos.json','data/videos_ia.json'] as $file){
    $arr=tvs_json($file);
    foreach($arr as $v){
      $url=tvs_video_url($v);
      $status=tvs_lc($v['status']??'active');
      if($url==='' || in_array($status,['erro','error','failed','paused','rascunho','sugerido','roteiro','roteiro_revisao','aprovado_video','gerando','fila','pendente'],true)) continue;
      $raw[]=$v;
    }
  }
  usort($raw,function($a,$b){ return tvs_date_ts($b)<=>tvs_date_ts($a); });
  $seen=[]; $out=[];
  foreach($raw as $v){
    $url=tvs_video_url($v);
    $titleKey=substr(tvs_norm_key($v['title']??''),0,90);
    $newsId=(string)($v['news_id']??$v['noticia_id']??'');
    $keys=array_filter(['url:'.$url, $titleKey?'t:'.$titleKey:'', $newsId?'n:'.$newsId:'']);
    $dup=false; foreach($keys as $k){ if(isset($seen[$k])){ $dup=true; break; } }
    if($dup) continue;
    foreach($keys as $k){ $seen[$k]=1; }
    $out[]=$v;
    if($limit>0 && count($out)>=$limit) break;
  }
  return $out;
}
function tvs_youtube_embed($url){
  $url=trim((string)$url); $id='';
  if(preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~',$url,$m)) $id=$m[1];
  elseif(preg_match('~youtube\.com/watch\?[^\s]*v=([A-Za-z0-9_-]{6,})~',$url,$m)) $id=$m[1];
  elseif(preg_match('~youtube\.com/embed/([A-Za-z0-9_-]{6,})~',$url,$m)) $id=$m[1];
  elseif(preg_match('~youtube\.com/shorts/([A-Za-z0-9_-]{6,})~',$url,$m)) $id=$m[1];
  return $id!==''?'https://www.youtube.com/embed/'.$id:'';
}
function tvs_find_related_video($news){
  $nid=(string)($news['id']??''); $title=tvs_norm_key($news['title']??''); $videos=tvs_load_real_videos(0);
  foreach($videos as $v){ if($nid!=='' && (string)($v['news_id']??$v['noticia_id']??'')===$nid) return $v; }
  foreach($videos as $v){ $vt=tvs_norm_key($v['title']??''); if($title && $vt){ similar_text(substr($title,0,120),substr($vt,0,120),$pct); if($pct>=58) return $v; } }
  return null;
}
function tvs_expand_article_display($n,$minWords=380){
  $body=trim((string)($n['body']??''));
  if(tvs_words($body)>=$minWords) return $body;
  $title=trim((string)($n['title']??'Notícia regional'));
  $summary=trim((string)($n['summary']??$n['subtitle']??$body));
  $city=tvs_infer_city($n) ?: 'região';
  $cat=trim((string)($n['category']??'Cidade'));
  $source=trim((string)($n['source']??'fonte consultada'));
  $paras=[];
  $paras[]=$summary!==''?$summary:$title.'.';
  $paras[]='A pauta envolve '.$city.' e integra a cobertura regional da TV Sumaré, com foco em informações de interesse público para moradores, trabalhadores, empresas e serviços da região.';
  if(tvs_category_match($n,['emprego','vagas','economia','empresa','negócios','negocios'])){
    $paras[]='Na área de empregos e negócios, a informação ganha relevância porque pode ajudar trabalhadores que buscam recolocação profissional, novas oportunidades de renda, capacitação e acompanhamento do desenvolvimento econômico local.';
    $paras[]='Os interessados devem acompanhar os canais oficiais indicados pela fonte para confirmar prazos, documentos necessários, critérios de participação, endereço de atendimento e eventuais alterações na programação.';
    $paras[]='Para empresas e comerciantes, pautas dessa natureza também ajudam a medir a movimentação do mercado de trabalho regional, principalmente quando envolvem vagas, processos seletivos, feirões, investimentos e ações de contratação.';
  } elseif(tvs_category_match($n,['saúde','saude','educação','educacao','serviço','servicos','serviços','obras'])){
    $paras[]='O tema também impacta quem depende de serviços públicos, acompanha ações municipais ou precisa de informações sobre atendimento, campanhas, obras, escolas, unidades de saúde e programas voltados à população.';
    $paras[]='Moradores devem observar os canais oficiais para confirmar horários, locais, critérios de participação e possíveis mudanças na programação divulgada.';
  } else {
    $paras[]='O caso faz parte do monitoramento regional da TV Sumaré, que acompanha informações de interesse público em Sumaré, Hortolândia, Paulínia, Nova Odessa, Americana e Campinas.';
    $paras[]='Novas informações poderão ser divulgadas por órgãos oficiais, entidades envolvidas e veículos regionais à medida que a pauta tiver desdobramentos.';
  }
  if($source!=='') $paras[]='Segundo as informações consultadas em '.$source.', a orientação é que o público acompanhe atualizações oficiais para detalhes complementares.';
  $paras[]='A TV Sumaré seguirá acompanhando os principais acontecimentos da região e atualizará esta publicação sempre que houver novas informações relevantes.';
  return trim(implode("\n\n", array_filter($paras)));
}

/* ===== TVSUMARE_ENTERPRISE_1.0_MASTER_BUILD_1.0.2 - Núcleo definitivo de notícias ===== */
if(!function_exists('tvs_source_names')){
function tvs_source_names(){
  return ['G1','g1','Portal ON','Sampi Campinas','sampi.net.br','Sampi','Hora Campinas','Notícia FM','Noticias FM','Hortonews','Google News','Google Notícias','Prefeitura de Sumaré','Prefeitura de Hortolândia','Prefeitura de Paulínia','Prefeitura de Nova Odessa','Prefeitura de Americana','Prefeitura de Campinas','O Regional Net','Todo Dia','R7','UOL','CNN Brasil'];
}
function tvs_clean_source_suffix($text){
  $text=trim(preg_replace('/\s+/u',' ',strip_tags((string)$text)));
  if($text==='') return '';
  foreach(tvs_source_names() as $src){
    $q=preg_quote($src,'/');
    $text=preg_replace('/\s*(?:[-–—|•:]\s*)?'.$q.'\s*$/iu','',$text);
    $text=preg_replace('/\s+'.$q.'\s*$/iu','',$text);
  }
  $text=preg_replace('/\s*(?:[-–—|•:]\s*)?(Google News|Google Notícias)\s+[A-Za-zÀ-ÿ\s]+$/iu','',$text);
  return trim($text," \t\n\r\0\x0B-–—|•:");
}
function tvs_news_age_days($n){
  $raw=$n['published_at']??$n['created_at']??$n['date']??'';
  if(!$raw) return 0;
  $ts=strtotime((string)$raw);
  if(!$ts) return 0;
  return (int)floor((time()-$ts)/86400);
}
function tvs_is_news_old($n,$maxDays=30){
  $age=tvs_news_age_days($n);
  return $age>$maxDays;
}
function tvs_news_has_region($n){
  $cities=['sumaré','sumare','hortolândia','hortolandia','paulínia','paulinia','nova odessa','americana','campinas'];
  $txt=tvs_lc(($n['city']??'').' '.($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??''));
  foreach($cities as $c){ if(strpos($txt,$c)!==false) return true; }
  return false;
}
function tvs_strict_title_key($s){
  $s=tvs_clean_source_suffix($s);
  $s=tvs_norm_key($s);
  $drop=['prefeitura','municipal','de','da','do','das','dos','em','para','com','e','a','o','as','os','um','uma','nesta','neste','veja','como','saiba'];
  $parts=array_values(array_filter(explode(' ',$s), function($w) use ($drop){ return strlen($w)>2 && !in_array($w,$drop,true); }));
  return implode(' ',array_slice($parts,0,14));
}
function tvs_strict_dedupe_news($news){
  $out=[]; $seen=[];
  foreach((array)$news as $n){
    $title=trim((string)($n['title']??''));
    if($title==='') continue;
    $url=tvs_norm_key($n['source_url']??$n['url']??$n['link']??'');
    $tk=tvs_strict_title_key($title);
    $city=tvs_lc(tvs_infer_city($n));
    $sig=md5($city.'|'.substr($tk,0,110));
    $keys=[];
    if(!empty($n['id'])) $keys[]='id:'.$n['id'];
    if($url!=='') $keys[]='url:'.$url;
    if($tk!=='') $keys[]='sig:'.$sig;
    $dup=false;
    foreach($keys as $k){ if(isset($seen[$k])){ $dup=true; break; } }
    if($dup) continue;
    foreach($keys as $k){ $seen[$k]=1; }
    $out[]=$n;
  }
  return $out;
}
function tvs_normalize_news_item($n){
  if(!is_array($n)) $n=[];
  foreach(['title','subtitle','summary','meta_description','seo_title'] as $k){ if(isset($n[$k])) $n[$k]=tvs_clean_source_suffix($n[$k]); }
  if(isset($n['body'])) $n['body']=trim((string)$n['body']);
  if(empty($n['city'])) $n['city']=tvs_infer_city($n);
  $img=tvs_real_image($n);
  $n['display_image']=$img!==''?$img:'assets/tvsumare-noticia-padrao.svg';
  return $n;
}
function tvs_prepare_public_news($news,$maxDays=30){
  $prepared=[];
  foreach((array)$news as $n){
    $n=tvs_normalize_news_item($n);
    if(tvs_is_news_old($n,$maxDays)) continue;
    if(!tvs_news_has_region($n)) continue;
    $prepared[]=$n;
  }
  $prepared=tvs_strict_dedupe_news($prepared);
  usort($prepared,'tvs_sort_recent');
  return $prepared;
}
function tvs_public_breaking($items,$maxDays=7,$limit=12){
  $out=[]; $seen=[];
  foreach((array)$items as $it){
    $title=tvs_clean_source_suffix($it['title']??'');
    if($title==='') continue;
    if(tvs_is_news_old($it,$maxDays)) continue;
    $key=tvs_strict_title_key($title);
    if(isset($seen[$key])) continue;
    $seen[$key]=1;
    $it['title']=$title;
    $out[]=$it;
    if(count($out)>=$limit) break;
  }
  return $out;
}
}

}


/* ===== TVSUMARE_ENTERPRISE_2.0_BUILD_01 - Núcleo editorial sênior ===== */
if(!function_exists('tvs_outside_city_blocklist')){
function tvs_outside_city_blocklist(){
  return ['caraguatatuba','santos','são vicente','sao vicente','praia grande','guarujá','guaruja','ubatuba','são sebastião','sao sebastiao','ilhabela','sorocaba','ribeirão preto','ribeirao preto','são josé dos campos','sao jose dos campos','taubaté','taubate','piracicaba','limeira','jundiaí','jundiai','indaiatuba','atibaia','cosmópolis','cosmopolis','monte mor','vinhedo','valinhos','itapira','mogi mirim','mogi guaçu','mogi guacu','bauru','marília','marilia','presidente prudente'];
}
function tvs_detect_outside_city($n){
  $txt=tvs_lc(($n['city']??'').' '.($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??'').' '.($n['source']??''));
  foreach(tvs_outside_city_blocklist() as $c){ if(strpos($txt,$c)!==false) return $c; }
  return '';
}
function tvs_region_city_detect($n){
  $txt=tvs_lc(($n['city']??'').' '.($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??''));
  $map=['sumaré'=>'Sumaré','sumare'=>'Sumaré','hortolândia'=>'Hortolândia','hortolandia'=>'Hortolândia','paulínia'=>'Paulínia','paulinia'=>'Paulínia','nova odessa'=>'Nova Odessa','americana'=>'Americana','campinas'=>'Campinas'];
  foreach($map as $k=>$v){ if(strpos($txt,$k)!==false) return $v; }
  return '';
}
function tvs_is_regional_news_strict($n){
  $outside=tvs_detect_outside_city($n);
  if($outside!=='') return false;
  return tvs_region_city_detect($n)!=='';
}
function tvs_prepare_public_news_v2($news,$maxDays=21){
  $prepared=[];
  foreach((array)$news as $n){
    $n=tvs_normalize_news_item($n);
    if(tvs_is_news_old($n,$maxDays)) continue;
    if(!tvs_is_regional_news_strict($n)) continue;
    $n['city']=tvs_region_city_detect($n) ?: ($n['city']??'Região');
    $prepared[]=$n;
  }
  $prepared=tvs_strict_dedupe_news($prepared);
  usort($prepared,'tvs_sort_recent');
  return $prepared;
}
function tvs_curated_sections($news){
  $sections=[
    'Empregos'=>['emprego','vagas','trabalho','processo seletivo','recrutamento','PAT'],
    'Saúde'=>['saúde','saude','ubs','vacina','hospital','agentes comunitários'],
    'Educação'=>['educação','educacao','escola','creche','curso','alunos','aulas'],
    'Segurança'=>['segurança','seguranca','operação','operacao','defesa civil','polícia','policia','queimada'],
    'Cidade'=>['cidade','prefeitura','serviço','servico','obra','trânsito','transito','cultura','evento']
  ];
  $out=[];$used=[];
  foreach($sections as $name=>$terms){
    $out[$name]=[];
    foreach($news as $n){
      $id=(string)($n['id']??md5(json_encode($n)));
      if(isset($used[$id])) continue;
      if(tvs_category_match($n,$terms)){$out[$name][]=$n;$used[$id]=1; if(count($out[$name])>=3) break;}
    }
  }
  return $out;
}
}

?>
