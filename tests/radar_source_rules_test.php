<?php
require_once dirname(__DIR__).'/admin/radar_queue_rules.php';
foreach([
['https://americana.sp.gov.br/americana-index.php?a=noticia&id=123','Matéria municipal de Americana',true],
['https://americana.sp.gov.br/americana-index.php','Matéria municipal de Americana',false],
['https://novomomento.com.br/nova-odessa-idosos-terca-d-setembro-amarelo/','Nova Odessa idosos terça d setembro amarelo',true],
['https://exemplo.com/category/saude/doacao-de-sangue-em-sumare','Doação de sangue em Sumaré',false],
['https://exemplo.com/noticias/educacao/alunos-de-sumare-chegam-a-semifinal','Alunos de Sumaré chegam à semifinal',true],
['https://g1.globo.com/sp/sao-paulo/o-que-fazer-em-sao-paulo/','Agenda cultural de Campinas e região traz shows',false]
] as [$url,$title,$expected]){
if(tvs_radar_is_article_path($url,$title,'Sumaré')!==$expected)throw new RuntimeException('Classificador: '.$url);
$check=tvs_radar_queue_item_readiness(['title'=>$title,'body'=>str_repeat('A prefeitura informou detalhes do atendimento público à população. ',12),'source_url'=>$url,'published_at'=>'2026-10-04']);
if(in_array('URL corresponde a página de listagem',$check['reasons'],true)===$expected)throw new RuntimeException('Prontidão: '.$url);
}
echo "RADAR_SOURCE_RULES_TEST=PASS\n";

$pending=['id'=>'keep','url'=>'https://news.google.com/rss/articles/test','pipeline_stage'=>'aguardando_fonte','source_resolution_attempts'=>6,'title'=>'Pauta preservada'];
$terminal=tvs_radar_normalize_source_terminal_state($pending);
if($terminal['pipeline_stage']!=='fonte_esgotada'||$terminal['id']!=='keep'||$terminal['title']!==$pending['title'])throw new RuntimeException('Terminal não normalizado ou pauta alterada');
if(tvs_radar_normalize_source_terminal_state($terminal)!==$terminal)throw new RuntimeException('Normalização não idempotente');
$retry=$pending;$retry['source_resolution_attempts']=5;
if(tvs_radar_normalize_source_terminal_state($retry)!==$retry)throw new RuntimeException('Tentativa elegível encerrada');
$retry['url_resolution_status']='unresolved_final';
if(tvs_radar_normalize_source_terminal_state($retry)['pipeline_stage']!=='fonte_esgotada')throw new RuntimeException('TTL final ignorado');
foreach(['revisao_manual_pipeline','fonte_resolvida','aguardando_enriquecimento'] as $stage){$protected=$pending;$protected['pipeline_stage']=$stage;if(tvs_radar_normalize_source_terminal_state($protected)!==$protected)throw new RuntimeException('Estado protegido alterado');}
$original=$pending;$original['url']='https://noticiasumare.com.br/materia';
if(tvs_radar_normalize_source_terminal_state($original)!==$original)throw new RuntimeException('Fonte já resolvida alterada');
echo "RADAR_TERMINAL_STATE_TEST=PASS\n";

$legacy=['id'=>'legacy','title'=>'Escola em Sumaré recebe ação de conscientização','body'=>str_repeat('A prefeitura informou detalhes do atendimento público à população. ',12),'city'=>'Sumaré','source_url'=>'https://sumare.portaldacidade.com/noticias/saude/projeto-leva-informacoes-sobre-febre-maculosa-a-alunos-de-escola-em-sumare-3119','published_at'=>'2026-10-04'];
$record=['city'=>'Sumaré','url'=>$legacy['source_url'],'title'=>'Projeto leva informações sobre febre maculosa a alunos de escola em Sumaré'];
$restored=tvs_radar_restore_original_title($legacy,[$record]);
if(empty($restored['source_original_title'])||!tvs_radar_queue_item_readiness($restored)['ready'])throw new RuntimeException('Título original não recuperou prontidão');
if($restored['id']!==$legacy['id']||$restored['title']!==$legacy['title']||$restored['body']!==$legacy['body'])throw new RuntimeException('Conteúdo editorial alterado');
if(tvs_radar_restore_original_title($restored,[])!==$restored)throw new RuntimeException('Proveniência existente alterada');
$wrongCity=$record;$wrongCity['city']='Campinas';
$wrongUrl=$record;$wrongUrl['url'].='-outra';
if(tvs_radar_restore_original_title($legacy,[$wrongCity,$wrongUrl])!==$legacy)throw new RuntimeException('Correspondência não exata aceita');
$other=$record;$other['title']='Projeto leva informações sobre febre maculosa a alunos de outra escola em Sumaré';
if(tvs_radar_restore_original_title($legacy,[$record,$other])!==$legacy)throw new RuntimeException('Histórico ambíguo aceito');
$listing=$legacy;$listing['source_url']='https://exemplo.com/category/saude/doacao-de-sangue-em-sumare';
$listingRecord=['url'=>$listing['source_url'],'city'=>'Sumaré','title'=>'Doação de sangue em Sumaré'];
if(tvs_radar_restore_original_title($listing,[$listingRecord])!==$listing)throw new RuntimeException('Listagem liberada');
echo "RADAR_PROVENANCE_RECOVERY_TEST=PASS\n";
