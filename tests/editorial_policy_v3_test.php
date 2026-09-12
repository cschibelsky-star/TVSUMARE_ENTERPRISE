<?php
require_once __DIR__.'/../includes/tvs_editorial_policy_v3.php';

$fail=[];
function assert_true($cond,$label){ global $fail; if(!$cond) $fail[]=$label; else echo "OK: {$label}\n"; }

$mossoro=[
  'city'=>'Sumaré',
  'title'=>'Prefeitura realiza recuperação asfáltica e de pavimentação no Barrocas e Sumaré',
  'source'=>'Prefeitura de Mossoró',
  'source_url'=>'https://www.prefeiturademossoro.com.br/noticia/exemplo'
];
assert_true(tvs_v3_is_regional_news($mossoro)===false,'Mossoro rotulado como Sumare e bloqueado');

$sumare=[
  'city'=>'',
  'title'=>'Sumaré amplia atendimento nas unidades de saúde',
  'source'=>'Prefeitura de Sumaré',
  'source_url'=>'https://sumare.sp.gov.br/'
];
assert_true(tvs_v3_is_regional_news($sumare)===true,'Sumare com evidencia real e aceito');
assert_true(tvs_v3_region_city_detect($sumare)==='Sumaré','Cidade regional detectada pelo conteudo');

$health=[
  'category'=>'Saúde',
  'title'=>'Programa Respire Saúde amplia atendimentos',
  'summary'=>'Ação de atendimento nas UBS da cidade.'
];
assert_true(tvs_v3_section($health)==='Saúde','Saude nao cruza para Empregos');

$jobs=[
  'category'=>'Empregos',
  'title'=>'PAT anuncia 120 vagas de trabalho',
  'summary'=>'Processo seletivo com vagas em empresas da região.'
];
assert_true(tvs_v3_section($jobs)==='Empregos','Empregos preserva categoria explicita');

$economy=[
  'category'=>'Economia',
  'title'=>'Sebrae Móvel faz atendimentos durante programação de aniversário',
  'summary'=>'Ação orienta empreendedores sobre gestão, inovação e desenvolvimento de negócios.'
];
assert_true(tvs_v3_section($economy)==='Cidade','Economia nao cruza para Saude ou Empregos');

$security=[
  'category'=>'Cidade',
  'title'=>'Presidente da Câmara é preso durante operação policial',
  'summary'=>'Caso de prisão e operação policial com impacto regional.'
];
assert_true(tvs_v3_section($security)==='Cidade','Categoria explicita valida tem prioridade editorial');

if($fail){ foreach($fail as $label) fwrite(STDERR,"FAIL: {$label}\n"); exit(1); }
echo "EDITORIAL_POLICY_V3_TEST=PASS\n";
