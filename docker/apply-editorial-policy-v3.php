<?php
/** Aplica politica editorial publica V3 de forma idempotente no runtime/release. */
$root=rtrim((string)(getenv('TVSUMARE_ROOT')?:dirname(__DIR__)),'/');
$helpers=$root.'/includes/tvs_public_helpers.php';
$code=file_get_contents($helpers);
if($code===false){ fwrite(STDERR,"EDITORIAL_V3: helpers nao encontrado\n"); exit(1); }

if(strpos($code,"tvs_editorial_policy_v3.php")===false){
  $code=preg_replace("~^<\\?php\\s*~","<?php\nrequire_once __DIR__.'/tvs_editorial_policy_v3.php';\n",$code,1,$count);
  if($count!==1){ fwrite(STDERR,"EDITORIAL_V3: falha ao incluir policy\n"); exit(2); }
}

/**
 * Substitui uma funcao PHP inteira sem depender de formatacao/indentacao.
 * A busca encontra a assinatura e faz balanceamento de chaves, ignorando
 * chaves dentro de strings simples/duplas e comentarios.
 */
function tvs_editorial_replace_function(string $code,string $name,string $replacement): string {
  if(!preg_match('/function\s+'.preg_quote($name,'/').'\s*\(/',$code,$m,PREG_OFFSET_CAPTURE)){
    fwrite(STDERR,"EDITORIAL_V3: funcao nao localizada: {$name}\n");
    exit(3);
  }

  $start=(int)$m[0][1];
  $open=strpos($code,'{',$start);
  if($open===false){
    fwrite(STDERR,"EDITORIAL_V3: abertura nao localizada: {$name}\n");
    exit(3);
  }

  $len=strlen($code);
  $depth=0;
  $state='code';
  $escape=false;

  for($i=$open;$i<$len;$i++){
    $ch=$code[$i];
    $next=$i+1<$len?$code[$i+1]:'';

    if($state==='single'){
      if($escape){ $escape=false; continue; }
      if($ch==='\\'){ $escape=true; continue; }
      if($ch==="'"){ $state='code'; }
      continue;
    }
    if($state==='double'){
      if($escape){ $escape=false; continue; }
      if($ch==='\\'){ $escape=true; continue; }
      if($ch==='"'){ $state='code'; }
      continue;
    }
    if($state==='line'){
      if($ch==="\n"){ $state='code'; }
      continue;
    }
    if($state==='block'){
      if($ch==='*' && $next==='/'){ $state='code'; $i++; }
      continue;
    }

    if($ch==="'"){ $state='single'; continue; }
    if($ch==='"'){ $state='double'; continue; }
    if($ch==='/' && $next==='/'){ $state='line'; $i++; continue; }
    if($ch==='#'){ $state='line'; continue; }
    if($ch==='/' && $next==='*'){ $state='block'; $i++; continue; }

    if($ch==='{'){ $depth++; continue; }
    if($ch==='}'){
      $depth--;
      if($depth===0){
        $end=$i+1;
        return substr($code,0,$start).$replacement.substr($code,$end);
      }
    }
  }

  fwrite(STDERR,"EDITORIAL_V3: fechamento nao localizado: {$name}\n");
  exit(3);
}

$replacements=[
  'tvs_detect_outside_city' => "function tvs_detect_outside_city(\$n){\n  return function_exists('tvs_v3_external_municipal_source') ? tvs_v3_external_municipal_source(\$n) : '';\n}",
  'tvs_region_city_detect' => "function tvs_region_city_detect(\$n){\n  return function_exists('tvs_v3_region_city_detect') ? tvs_v3_region_city_detect(\$n) : '';\n}",
  'tvs_is_regional_news_strict' => "function tvs_is_regional_news_strict(\$n){\n  return function_exists('tvs_v3_is_regional_news') ? tvs_v3_is_regional_news(\$n) : false;\n}",
  'tvs_curated_sections' => "function tvs_curated_sections(\$news){\n  return function_exists('tvs_v3_curated_sections') ? tvs_v3_curated_sections(\$news) : [];\n}",
  'tvs_category_match' => "function tvs_category_match(\$n,\$terms){\n  \$normalized=array_map(function(\$t){ return tvs_lc((string)\$t); },(array)\$terms);\n  if(function_exists('tvs_v3_section') && array_intersect(\$normalized,['emprego','empregos','vagas','oportunidade'])){\n    return tvs_v3_section(\$n)==='Empregos';\n  }\n  \$txt=tvs_lc((\$n['category']??'').' '.(\$n['title']??'').' '.(\$n['subtitle']??'').' '.(\$n['summary']??'').' '.(\$n['body']??''));\n  foreach((array)\$terms as \$t){ if(strpos(\$txt,tvs_lc(\$t))!==false) return true; }\n  return false;\n}"
];

foreach($replacements as $name=>$replacement){
  $code=tvs_editorial_replace_function($code,$name,$replacement);
}

if(file_put_contents($helpers,$code,LOCK_EX)===false){ fwrite(STDERR,"EDITORIAL_V3: falha ao gravar helpers\n"); exit(4); }

$out=[];$rc=0;
exec('php -l '.escapeshellarg($helpers).' 2>&1',$out,$rc);
if($rc!==0){ fwrite(STDERR,implode("\n",$out)."\n"); exit(5); }

echo "TVSUMARE_EDITORIAL_V3=APPLIED\n";
