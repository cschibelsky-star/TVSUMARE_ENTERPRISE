<?php
/**
 * Fontes regionais suplementares do Radar.
 *
 * Registro somente de leitura: não altera data/fontes.json, matérias ou filas.
 * Cada item continua sujeito aos gates de URL original, cidade, data e editoria.
 * A disponibilidade dos feeds é aferida durante a coleta; um feed vazio ou
 * indisponível não autoriza criar notícias artificiais.
 */
if (!function_exists('tvs_radar_curated_sources_for_city')) {
  function tvs_radar_curated_sources_for_city($city) {
    $sources=[
      'Sumaré'=>[
        ['name'=>'Notícias Sumaré','url'=>'https://noticiasumare.com.br/','rss'=>'https://noticiasumare.com.br/feed/'],
        ['name'=>'Novo Momento — Sumaré','url'=>'https://novomomento.com.br/','rss'=>'https://novomomento.com.br/feed/']
      ],
      'Hortolândia'=>[
        ['name'=>'Portal Hortolândia','url'=>'https://portalhortolandia.com.br/','rss'=>'https://portalhortolandia.com.br/feed/'],
        ['name'=>'Prefeitura de Hortolândia','url'=>'https://www.hortolandia.sp.gov.br/','rss'=>'']
      ],
      'Paulínia'=>[
        ['name'=>'Novo Momento — Paulínia','url'=>'https://novomomento.com.br/','rss'=>'https://novomomento.com.br/feed/'],
        ['name'=>'Prefeitura de Paulínia','url'=>'https://www.paulinia.sp.gov.br/','rss'=>'']
      ],
      'Nova Odessa'=>[
        ['name'=>'Novo Momento — Nova Odessa','url'=>'https://novomomento.com.br/','rss'=>'https://novomomento.com.br/feed/'],
        ['name'=>'SB Notícias — Nova Odessa','url'=>'https://sbnoticias.com.br/public/noticias','rss'=>'']
      ],
      'Americana'=>[
        ['name'=>'Novo Momento — Americana','url'=>'https://novomomento.com.br/','rss'=>'https://novomomento.com.br/feed/'],
        ['name'=>'SB Notícias — Americana','url'=>'https://sbnoticias.com.br/public/noticias','rss'=>'']
      ],
      'Campinas'=>[
        ['name'=>'Hora Campinas','url'=>'https://horacampinas.com.br/','rss'=>'https://horacampinas.com.br/feed/'],
        ['name'=>'Portal Hortolândia — Campinas','url'=>'https://portalhortolandia.com.br/','rss'=>'https://portalhortolandia.com.br/feed/']
      ]
    ];
    $result=[];
    foreach(($sources[(string)$city]??[]) as $source){
      $result[]=$source+['type'=>'Portal Regional','city'=>(string)$city,'active'=>true,'origin'=>'curated_fallback'];
    }
    return $result;
  }
}

if (!function_exists('tvs_radar_seed_hml_sources')) {
  function tvs_radar_seed_hml_sources($dataDir) {
    // Nunca altera a configuração da produção ou um cadastro já existente.
    if((string)getenv('TVSUMARE_ENV')!=='homologation') return ['status'=>'skipped_environment'];
    $file=rtrim((string)$dataDir,'/').'/fontes.json';
    if(is_file($file)) return ['status'=>'already_present'];
    $cities=['Sumaré','Hortolândia','Paulínia','Nova Odessa','Americana','Campinas'];
    $sources=[];
    foreach($cities as $city) {
      foreach(tvs_radar_curated_sources_for_city($city) as $src) $sources[]=$src;
    }
    if(!$sources) return ['status'=>'no_seed_sources'];
    if(!is_dir($dataDir) && !@mkdir($dataDir,0775,true) && !is_dir($dataDir)) return ['status'=>'directory_unavailable'];
    // Modo x cria exclusivamente se o arquivo não existir, sem sobrescrever.
    $fp=@fopen($file,'x');
    if(!$fp) return ['status'=>is_file($file)?'already_present':'write_denied'];
    $json=json_encode($sources,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $bytes=@fwrite($fp,$json!==false?$json:'[]');
    @fclose($fp);
    if($bytes===false || $bytes===0) return ['status'=>'write_failed'];
    return ['status'=>'seeded','count'=>count($sources)];
  }
}

if (!function_exists('tvs_radar_merge_source_registry')) {
  function tvs_radar_merge_source_registry($registered,$builtins,$city) {
    $out=[]; $seen=[];
    foreach(array_merge((array)$registered,(array)$builtins) as $source){
      if(!is_array($source)) continue;
      $sourceCity=trim((string)($source['city']??'Região'));
      if($sourceCity!=='' && $sourceCity!=='Região' && $sourceCity!==(string)$city) continue;
      $url=trim((string)($source['rss']??''))?:trim((string)($source['url']??''));
      if($url==='') continue;
      $name=mb_strtolower(trim((string)($source['name']??'')),'UTF-8');
      $key=mb_strtolower($sourceCity.'|'.$url,'UTF-8');
      $nameKey=mb_strtolower($sourceCity.'|'.$name,'UTF-8');
      if(isset($seen[$key]) || ($name!=='' && isset($seen[$nameKey]))) continue;
      $seen[$key]=true;
      if($name!=='') $seen[$nameKey]=true;
      $out[]=$source;
    }
    return $out;
  }
}
