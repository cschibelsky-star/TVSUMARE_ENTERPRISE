<?php
/** Aplica politica editorial publica V3 de forma idempotente no runtime. */
$helpers='/var/www/html/includes/tvs_public_helpers.php';
$code=file_get_contents($helpers);
if($code===false){ fwrite(STDERR,"EDITORIAL_V3: helpers nao encontrado\n"); exit(1); }

if(strpos($code,"tvs_editorial_policy_v3.php")===false){
  $code=preg_replace("~^<\\?php\\s*~","<?php\nrequire_once __DIR__.'/tvs_editorial_policy_v3.php';\n",$code,1,$count);
  if($count!==1){ fwrite(STDERR,"EDITORIAL_V3: falha ao incluir policy\n"); exit(2); }
}

$patterns=[
  '~function tvs_detect_outside_city\(\$n\)\{.*?\n\}~s' => "function tvs_detect_outside_city(\$n){\n  return function_exists('tvs_v3_external_municipal_source') ? tvs_v3_external_municipal_source(\$n) : '';\n}",
  '~function tvs_region_city_detect\(\$n\)\{.*?\n\}~s' => "function tvs_region_city_detect(\$n){\n  return function_exists('tvs_v3_region_city_detect') ? tvs_v3_region_city_detect(\$n) : '';\n}",
  '~function tvs_is_regional_news_strict\(\$n\)\{.*?\n\}~s' => "function tvs_is_regional_news_strict(\$n){\n  return function_exists('tvs_v3_is_regional_news') ? tvs_v3_is_regional_news(\$n) : false;\n}",
  '~function tvs_curated_sections\(\$news\)\{.*?\n\}~s' => "function tvs_curated_sections(\$news){\n  return function_exists('tvs_v3_curated_sections') ? tvs_v3_curated_sections(\$news) : [];\n}",
  '~function tvs_category_match\(\$n,\$terms\)\{.*?\n\}~s' => "function tvs_category_match(\$n,\$terms){\n  \$normalized=array_map(function(\$t){ return tvs_lc((string)\$t); },(array)\$terms);\n  if(function_exists('tvs_v3_section') && array_intersect(\$normalized,['emprego','empregos','vagas','oportunidade'])){\n    return tvs_v3_section(\$n)==='Empregos';\n  }\n  \$txt=tvs_lc((\$n['category']??'').' '.(\$n['title']??'').' '.(\$n['subtitle']??'').' '.(\$n['summary']??'').' '.(\$n['body']??''));\n  foreach((array)\$terms as \$t){ if(strpos(\$txt,tvs_lc(\$t))!==false) return true; }\n  return false;\n}"
];

foreach($patterns as $pattern=>$replacement){
  $updated=preg_replace($pattern,$replacement,$code,1,$count);
  if($updated===null || $count!==1){ fwrite(STDERR,"EDITORIAL_V3: ancora nao localizada: {$pattern}\n"); exit(3); }
  $code=$updated;
}

if(file_put_contents($helpers,$code,LOCK_EX)===false){ fwrite(STDERR,"EDITORIAL_V3: falha ao gravar helpers\n"); exit(4); }

$out=[];$rc=0;
exec('php -l '.escapeshellarg($helpers).' 2>&1',$out,$rc);
if($rc!==0){ fwrite(STDERR,implode("\n",$out)."\n"); exit(5); }

echo "TVSUMARE_EDITORIAL_V3=APPLIED\n";
