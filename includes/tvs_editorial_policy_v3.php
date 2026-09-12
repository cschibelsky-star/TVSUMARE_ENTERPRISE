<?php
/**
 * Politica editorial publica V3 da TV Sumare.
 * Fonte unica para validacao regional e classificacao de secoes.
 */
if(!function_exists('tvs_v3_lc')){
function tvs_v3_lc($s){ return function_exists('mb_strtolower') ? mb_strtolower((string)$s,'UTF-8') : strtolower((string)$s); }
function tvs_v3_allowed_city_map(){
  return [
    'sumaré'=>'Sumaré','sumare'=>'Sumaré',
    'hortolândia'=>'Hortolândia','hortolandia'=>'Hortolândia',
    'paulínia'=>'Paulínia','paulinia'=>'Paulínia',
    'nova odessa'=>'Nova Odessa','americana'=>'Americana','campinas'=>'Campinas'
  ];
}
function tvs_v3_content_text($n){
  return tvs_v3_lc(($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??'').' '.($n['source']??'').' '.($n['source_url']??''));
}
function tvs_v3_region_city_detect($n){
  $txt=tvs_v3_content_text($n);
  foreach(tvs_v3_allowed_city_map() as $k=>$v){ if(strpos($txt,$k)!==false) return $v; }
  return '';
}
function tvs_v3_external_municipal_source($n){
  $source=tvs_v3_lc(trim((string)($n['source']??'')));
  $url=tvs_v3_lc(trim((string)($n['source_url']??'')));
  $content=tvs_v3_content_text($n);
  $combined=trim($source.' '.$url.' '.$content);
  if($combined==='') return '';

  $outside=['mossoró','mossoro','caraguatatuba','santos','são vicente','sao vicente','praia grande','guarujá','guaruja','ubatuba','são sebastião','sao sebastiao','ilhabela','sorocaba','ribeirão preto','ribeirao preto','são josé dos campos','sao jose dos campos','taubaté','taubate','piracicaba','limeira','jundiaí','jundiai','indaiatuba','atibaia','cosmópolis','cosmopolis','monte mor','vinhedo','valinhos','itapira','mogi mirim','mogi guaçu','mogi guacu','bauru','marília','marilia','presidente prudente'];
  foreach($outside as $city){ if(strpos($combined,$city)!==false) return $city; }

  if(preg_match('~prefeitura(?: municipal)? de\s+([^|,;:/]+)~u',$content,$m)){
    $declared=trim($m[1]);
    $allowed=false;
    foreach(tvs_v3_allowed_city_map() as $k=>$v){ if(strpos($declared,$k)!==false){ $allowed=true; break; } }
    if(!$allowed && $declared!=='') return $declared;
  }
  return '';
}
function tvs_v3_is_regional_news($n){
  if(tvs_v3_external_municipal_source($n)!=='') return false;
  return tvs_v3_region_city_detect($n)!=='';
}
function tvs_v3_text_has($txt,$terms){
  foreach((array)$terms as $term){ if(strpos($txt,tvs_v3_lc($term))!==false) return true; }
  return false;
}
function tvs_v3_section($n){
  $explicit=tvs_v3_lc(trim((string)($n['category']??'')));
  $txt=tvs_v3_lc(($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??''));

  // Categoria editorial explicita prevalece sobre palavras incidentais do texto.
  $explicitMap=[
    'emprego'=>'Empregos','empregos'=>'Empregos','vagas'=>'Empregos',
    'saúde'=>'Saúde','saude'=>'Saúde',
    'educação'=>'Educação','educacao'=>'Educação',
    'segurança'=>'Segurança','seguranca'=>'Segurança','polícia'=>'Segurança','policia'=>'Segurança',
    'cidade'=>'Cidade','cultura'=>'Cidade','eventos'=>'Cidade','evento'=>'Cidade',
    'economia'=>'Cidade','negócios'=>'Cidade','negocios'=>'Cidade','política'=>'Cidade','politica'=>'Cidade',
    'esportes'=>'Cidade','esporte'=>'Cidade','meio ambiente'=>'Cidade','turismo'=>'Cidade','inovação'=>'Cidade','inovacao'=>'Cidade'
  ];
  if(isset($explicitMap[$explicit])) return $explicitMap[$explicit];

  if(tvs_v3_text_has($txt,['vaga','vagas','emprego','processo seletivo','recrutamento','pat'])) return 'Empregos';
  if(tvs_v3_text_has($txt,['saúde','saude','ubs','vacina','hospital','dengue','agentes comunitários','agentes comunitarios'])) return 'Saúde';
  if(tvs_v3_text_has($txt,['educação','educacao','escola','creche','aluno','alunos','aulas'])) return 'Educação';
  if(tvs_v3_text_has($txt,['prisão','prisao','preso','polícia','policia','operação','operacao','homicídio','homicidio','tráfico','trafico','defesa civil'])) return 'Segurança';
  return 'Cidade';
}
function tvs_v3_curated_sections($news){
  $out=['Empregos'=>[],'Saúde'=>[],'Educação'=>[],'Segurança'=>[],'Cidade'=>[]];
  $used=[];
  foreach((array)$news as $n){
    $id=(string)($n['id']??md5(json_encode($n)));
    if(isset($used[$id])) continue;
    $section=tvs_v3_section($n);
    if(!isset($out[$section]) || count($out[$section])>=3) continue;
    $out[$section][]=$n;
    $used[$id]=1;
  }
  return $out;
}
}
