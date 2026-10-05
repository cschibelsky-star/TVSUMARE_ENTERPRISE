<?php
// Mesma validação para Radar, revisão e ferramentas CLI.
require_once dirname(__DIR__).'/monitor_lib.php';

function tvs_radar_normalize_title_for_match($title){
  $title=tvs_lower(tvs_clean_text((string)$title));
  $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$title);
  if($ascii!==false) $title=$ascii;
  $title=preg_replace('~[^\p{L}\p{N}]+~u',' ',$title);
  return trim(preg_replace('~\s+~u',' ',$title));
}

function tvs_radar_title_match_score($expected,$candidate){
  if(function_exists('tvs_radar_clean_google_title')){
    $expected=tvs_radar_clean_google_title($expected);
    $candidate=tvs_radar_clean_google_title($candidate);
  }
  $a=tvs_radar_normalize_title_for_match($expected);
  $b=tvs_radar_normalize_title_for_match($candidate);

  if($a==='' || $b==='') return 0;
  if($a===$b) return 100;

  $wa=array_values(array_unique(array_filter(
    explode(' ',$a),
    static fn($word)=>tvs_strlen($word)>=4
  )));
  $wb=array_values(array_unique(array_filter(
    explode(' ',$b),
    static fn($word)=>tvs_strlen($word)>=4
  )));

  if(!$wa || !$wb) return 0;

  $common=count(array_intersect($wa,$wb));
  $union=count(array_unique(array_merge($wa,$wb)));
  if($union<1) return 0;

  return (int)round(($common/$union)*100);
}

function tvs_radar_is_article_path($url,$title='',$city=''){
  $queryParams=[];
  parse_str((string)(parse_url((string)$url,PHP_URL_QUERY)??''),$queryParams);
  $isQueryArticle=
    tvs_lower((string)($queryParams['a']??''))==='noticia'
    && preg_match('~^[0-9]+$~',(string)($queryParams['id']??''))===1;

  // Alguns portais públicos, como Americana, identificam matérias por query string
  // (?a=noticia&id=...). A confirmação final ainda valida título, corpo e cidade.
  if($isQueryArticle) return true;

  $path=(string)(parse_url((string)$url,PHP_URL_PATH)??'');
  $path=trim($path,'/');
  if($path==='') return false;

  $segments=array_values(array_filter(explode('/',$path)));
  if(!$segments) return false;

  $last=(string)end($segments);
  if($last==='') return false;

  $generic=[
    'category','categoria','tag','tags','author','autor','search','busca',
    'page','pagina','arquivo','archive','editoria','secao','seção',
    'cidade','cidades','noticia','noticias','notícia','notícias'
  ];
  $alwaysListing=[
    'category','categoria','tag','tags','author','autor','search','busca',
    'page','pagina','arquivo','archive','editoria','secao','seção'
  ];

  // Segmentos estruturais como /noticias/ podem conter artigos depois deles.
  // Já categoria/tag/busca/autor/página são rotas de listagem e nunca devem
  // ser aceitas como fonte final, mesmo quando há slug adicional.
  foreach($segments as $segment){
    if(in_array(tvs_lower((string)$segment),$alwaysListing,true)) return false;
  }
  if(count($segments)===1 && in_array(tvs_lower($segments[0]),$generic,true)){
    return false;
  }

  $citySlug=tvs_slug((string)$city);
  if(
    $citySlug!=='' &&
    (
      tvs_slug($path)===$citySlug ||
      tvs_slug($last)===$citySlug
    )
  ){
    return false;
  }

  // Último segmento precisa parecer uma manchete, inclusive em URLs WordPress
  // de um único nível (/titulo-da-materia).
  $slugWords=array_values(array_filter(preg_split('~[-_]+~',$last)));
  if(count($slugWords)<4) return false;

  $slugText=str_replace(['-','_'],' ',$last);
  $slugScore=tvs_radar_title_match_score($title,$slugText);

  return $slugScore>=42;
}

function tvs_radar_normalize_source_terminal_state(array $item): array {
    $stage=(string)($item['pipeline_stage']??'');
    if(!in_array($stage,['','pauta_encontrada','precisa_resolver_fonte','aguardando_fonte','fonte_esgotada'],true))return $item;
    $url=(string)($item['url']??$item['source_url']??'');
    if(strtolower((string)parse_url($url,PHP_URL_HOST))!=='news.google.com')return $item;
    if(($item['url_resolution_status']??'')!=='unresolved_final'&&(int)($item['source_resolution_attempts']??0)<6)return $item;
    if($stage==='fonte_esgotada'&&($item['url_resolution_status']??'')==='unresolved_final')return $item;
    $item['url_resolution_status']='unresolved_final';
    $item['pipeline_stage']='fonte_esgotada';
    $item['pipeline_reason']='Fonte original esgotou as tentativas/TTL; preservada fora do backlog ativo para auditoria.';
    $item['pipeline_updated_at']=date('c');
    return $item;
}

/** Restore missing provenance only from an exact URL/city match in collected records. */
function tvs_radar_restore_original_title(array $item, array $records): array {
    if(trim((string)($item['source_original_title']??''))!=='')return $item;
    $url=trim((string)($item['source_url']??$item['url']??''));
    $city=trim((string)($item['city']??''));
    if($url===''||$city==='')return $item;
    $titles=[];
    foreach($records as $record){
        if(!is_array($record))continue;
        $recordUrl=trim((string)($record['source_url']??$record['url']??''));
        if($recordUrl!==$url||trim((string)($record['city']??''))!==$city)continue;
        $title=trim((string)($record['source_original_title']??$record['title']??''));
        if($title===''||!tvs_radar_is_article_path($url,$title,$city))continue;
        $titles[tvs_radar_normalize_title_for_match($title)]=$title;
    }
    if(count($titles)!==1)return $item;
    $item['source_original_title']=reset($titles);
    $item['source_title_recovery_method']='exact_url_city_history';
    $item['source_title_recovered_at']=date('c');
    return $item;
}
