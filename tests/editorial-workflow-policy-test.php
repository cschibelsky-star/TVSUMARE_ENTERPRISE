<?php
require_once dirname(__DIR__).'/includes/editorial_workflow_policy.php';
function check($ok,$label){if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}echo "PASS $label\n";}
$source=str_repeat('Fonte regional com dados verificaveis. ',12);
$article=['title'=>'Noticia da regiao','body'=>str_repeat('Texto jornalistico validado. ',15),'source_body'=>$source,'source'=>'Veiculo','source_url'=>'https://example.org/noticia','city'=>'Sumaré','category'=>'Cidade','editorial_ai_completed'=>true];
check(tvs_editorial_source_ready($article),'source at least 350 chars');
check(tvs_editorial_article_ready($article),'complete article');
check(tvs_editorial_next_state('em_edicao','submit',$article)==='pronta_para_revisao','submit complete');
check(tvs_editorial_next_state('pronta_para_revisao','approve',$article)==='aprovada','approval separate from publication');
check(tvs_editorial_next_state('pronta_para_revisao','publish',$article)===null,'no publish from review');
$short=$article;$short['source_body']='Manchete curta';
check(!tvs_editorial_article_ready($short),'short source blocked');
$noAi=$article;$noAi['editorial_ai_completed']=false;
check(!tvs_editorial_article_ready($noAi),'no editorial preparation blocked');
$shortBody=$article;$shortBody['body']='Somente manchete';
check(!tvs_editorial_article_ready($shortBody),'short final body blocked');
check(tvs_editorial_next_state('em_edicao','submit',$short)===null,'cannot submit shell');
echo "EDITORIAL_POLICY_TESTS=PASS\n";
