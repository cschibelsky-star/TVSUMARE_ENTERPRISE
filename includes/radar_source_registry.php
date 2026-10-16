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
        ['name'=>'Notícias Sumaré','url'=>'https://noticiasumare.com.br/','rss'=>'https://noticiasumare.com.br/feed/']
      ],
      'Hortolândia'=>[
        ['name'=>'Portal Hortolândia','url'=>'https://portalhortolandia.com.br/','rss'=>'https://portalhortolandia.com.br/feed/']
      ],
      'Paulínia'=>[
        ['name'=>'Novo Momento — Paulínia','url'=>'https://novomomento.com.br/','rss'=>'https://novomomento.com.br/feed/']
      ],
      'Nova Odessa'=>[
        ['name'=>'Novo Momento — Nova Odessa','url'=>'https://novomomento.com.br/','rss'=>'https://novomomento.com.br/feed/']
      ],
      'Americana'=>[
        ['name'=>'Novo Momento — Americana','url'=>'https://novomomento.com.br/','rss'=>'https://novomomento.com.br/feed/']
      ],
      'Campinas'=>[
        ['name'=>'Hora Campinas','url'=>'https://horacampinas.com.br/','rss'=>'https://horacampinas.com.br/feed/']
      ]
    ];
    $result=[];
    foreach(($sources[(string)$city]??[]) as $source){
      $result[]=$source+['type'=>'Portal Regional','city'=>(string)$city,'active'=>true,'origin'=>'curated_fallback'];
    }
    return $result;
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
