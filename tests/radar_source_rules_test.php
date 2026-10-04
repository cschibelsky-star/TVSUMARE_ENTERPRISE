<?php
require_once dirname(__DIR__).'/admin/radar_queue_rules.php';
$cases=[
 ['https://americana.sp.gov.br/americana-index.php?a=noticia&id=123','Matéria municipal de Americana',true],
 ['https://americana.sp.gov.br/americana-index.php','Matéria municipal de Americana',false],
 ['https://novomomento.com.br/nova-odessa-idosos-terca-d-setembro-amarelo/','Nova Odessa idosos terça d setembro amarelo',true],
 ['https://exemplo.com/category/saude/doacao-de-sangue-em-sumare','Doação de sangue em Sumaré',false],
 ['https://exemplo.com/noticias/educacao/alunos-de-sumare-chegam-a-semifinal','Alunos de Sumaré chegam à semifinal',true],
 ['https://g1.globo.com/sp/sao-paulo/o-que-fazer-em-sao-paulo/','Agenda cultural de Campinas e região traz shows',false],
];
foreach($cases as [$url,$title,$expected]){
 if(tvs_radar_is_article_path($url,$title,'Sumaré')!==$expected) throw new RuntimeException('Classificador divergente: '.$url);
 $item=['title'=>$title,'body'=>str_repeat('A prefeitura informou detalhes do serviço público e de seu atendimento à população. ',12),'source_url'=>$url,'published_at'=>'2026-10-04'];
 $reason=in_array('URL corresponde a página de listagem',tvs_radar_queue_item_readiness($item)['reasons'],true);
 if($reason===$expected) throw new RuntimeException('Prontidão divergente: '.$url);
}
echo "RADAR_SOURCE_RULES_TEST_OK: ".count($cases)." casos e prontidão consistente\n";
