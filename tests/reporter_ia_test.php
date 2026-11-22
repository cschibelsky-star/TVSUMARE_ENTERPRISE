<?php
define('TVSUMARE_TEST_MODE', true);

$captured=[];
$GLOBALS['tvs_gemini_test_handler']=function($apiKey,$prompt,$generationConfig,$timeout) use (&$captured){
  $captured=['api_key'=>$apiKey,'prompt'=>$prompt,'config'=>$generationConfig,'timeout'=>$timeout];
  return [
    'ok'=>true,
    'model'=>'test-gemini',
    'text'=>json_encode([
      'title'=>'Sumaré amplia atendimento nas unidades de saúde',
      'subtitle'=>'Medida reorganiza o serviço e orienta moradores sobre o atendimento.',
      'summary'=>'Sumaré amplia atendimento nas unidades de saúde.',
      'body'=>'A Prefeitura de Sumaré ampliou o atendimento nas unidades de saúde nesta semana. A medida reorganiza o serviço para os moradores da cidade.\n\nAs orientações e os horários devem ser consultados nos canais oficiais informados no material apurado.',
      'category'=>'Saúde',
      'tags'=>['Sumaré','Saúde','Atendimento'],
      'seo_title'=>'Sumaré amplia atendimento nas unidades de saúde',
      'meta_description'=>'Sumaré amplia atendimento nas unidades de saúde e orienta moradores sobre o serviço.',
      'slug'=>'sumare-amplia-atendimento-nas-unidades-de-saude',
      'instagram_caption'=>'Sumaré amplia atendimento nas unidades de saúde. Confira as orientações.',
      'whatsapp_text'=>'Sumaré amplia atendimento nas unidades de saúde. Leia mais.'
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
  ];
};

require_once __DIR__.'/../admin/gemini.php';

$fail=[];
function assert_true($condition,$label){
  global $fail;
  if(!$condition) $fail[]=$label; else echo "OK: {$label}\n";
}

$result=gemini_reporter_article('fixture-key',
  "Cidade prioritária: Sumaré\nTema solicitado pelo editor: atendimento nas unidades de saúde\nCategoria: Saúde\nFONTE 1: Prefeitura de Sumaré\nTítulo original: Sumaré amplia atendimento\nConteúdo apurado: A Prefeitura de Sumaré ampliou o atendimento nas unidades de saúde nesta semana.",
  ['city'=>'Sumaré','theme'=>'atendimento nas unidades de saúde','category'=>'Saúde']
);

assert_true(is_array($result),'Repórter retorna estrutura de matéria');
assert_true(($result['generation_mode']??'')==='gemini','Repórter identifica geração Gemini');
assert_true(($result['category']??'')==='Saúde','Repórter preserva categoria editorial');
assert_true(strpos((string)($result['body']??''),'ampliou o atendimento')!==false,'Repórter retorna corpo editorial');
assert_true(strpos((string)($captured['prompt']??''),'ASSUNTO APURADO: atendimento nas unidades de saúde')!==false,'Prompt preserva assunto apurado');
assert_true(strpos((string)($captured['prompt']??''),'Não copie trechos longos da fonte')!==false,'Prompt exige reescrita editorial');

if($fail){
  foreach($fail as $label) fwrite(STDERR,"FAIL: {$label}\n");
  exit(1);
}
echo "REPORTER_IA_TEST=PASS\n";
