<?php
require_once dirname(__DIR__).'/monitor_lib.php';

if(!function_exists('tvs_monitor_age_days')){
function tvs_monitor_age_days($item){ $raw=$item['published_at']??$item['created_at']??$item['date']??''; if(!$raw)return 0; $ts=strtotime((string)$raw); return $ts?(int)floor((time()-$ts)/86400):0; }
function tvs_monitor_is_old($item,$maxDays=14){ return tvs_monitor_age_days($item)>$maxDays; }
function tvs_monitor_title_key($title){
  $t=tvs_clean_text((string)$title); $t=preg_replace('/\s*(?:[-–—|•:]\s*)?(G1|Portal ON|sampi\.net\.br|Sampi Campinas|Hora Campinas|Notícia FM|Noticias FM|Hortonews|Google News|Google Notícias|R7|UOL)\s*$/iu','',$t); $t=tvs_lower($t); $t=preg_replace('/[^a-z0-9áàâãéèêíïóôõöúçñ]+/u',' ',$t); $drop=['prefeitura','municipal','de','da','do','das','dos','em','para','com','e','a','o','as','os','um','uma','nesta','neste','veja','como','saiba']; $parts=array_values(array_filter(explode(' ',trim($t)),function($w)use($drop){return strlen($w)>2&&!in_array($w,$drop,true);})); return implode(' ',array_slice($parts,0,14));
}
function tvs_monitor_discard($item,$reason){
  $file=dirname(__DIR__).'/data/pautas_descartadas.json'; $arr=tvs_read_json_file($file); if(!is_array($arr))$arr=[];
  $arr[]=['data'=>date('d/m H:i'),'created_at'=>date('c'),'status'=>'DESCARTADA','city'=>$item['city']??'Região','cidade'=>$item['city']??'Região','source'=>$item['source']??'Fonte','fonte'=>$item['source']??'Fonte','title'=>$item['title']??'','titulo'=>$item['title']??'','reason'=>$reason,'motivo'=>$reason,'source_url'=>$item['url']??'','url'=>$item['url']??'','description'=>$item['description']??'','summary'=>$item['description']??'','body'=>$item['body']??($item['text']??''),'text'=>$item['text']??($item['body']??($item['description']??'')),'image'=>$item['image']??'','image_source_type'=>$item['image_source_type']??'','image_review_required'=>$item['image_review_required']??0,'published_at'=>$item['published_at']??''];
  $arr=array_slice($arr,-300); tvs_save_json_file($file,$arr);
}
function tvs_monitor_invalid_extraction($item,$article,&$reason=''){
  $itemTitle=trim((string)($item['title']??''));
  $articleTitle=trim((string)($article['title']??''));
  $description=trim((string)($article['description']??($item['description']??'')));
  $body=trim((string)($article['body']??''));
  $combined=$articleTitle.' '.$description.' '.$body;
  if(preg_match('/^google\s+(?:news|not[ií]cias)$/iu',$articleTitle) || preg_match('/^google\s+(?:news|not[ií]cias)$/iu',$itemTitle)){
    $reason='Google News retornou página genérica sem matéria jornalística.';
    return true;
  }
  if(stripos($combined,'Comprehensive up-to-date news coverage, aggregated from sources all over the world by Google News')!==false){
    $reason='Boilerplate do Google News detectado na extração; matéria original não foi resolvida.';
    return true;
  }
  return false;
}
function tvs_monitor_seen_keys($drafts,$news){ $seen=[]; foreach(array_merge((array)$drafts,(array)$news) as $n){ if(!empty($n['source_url']))$seen['url:'.$n['source_url']]=1; if(!empty($n['url']))$seen['url:'.$n['url']]=1; $tk=tvs_monitor_title_key($n['title']??''); $city=tvs_lower($n['city']??''); if($tk)$seen['title:'.md5($city.'|'.$tk)]=1; } return $seen; }
function tvs_monitor_item_is_duplicate($item,$seen){ $url=$item['url']??''; if($url&&isset($seen['url:'.$url]))return true; $tk=tvs_monitor_title_key($item['title']??''); $city=tvs_lower($item['city']??''); return $tk&&isset($seen['title:'.md5($city.'|'.$tk)]); }
}
