<?php
// Teste isolado: nenhuma leitura/escrita do backlog real e nenhuma chamada de IA.
$source=file_get_contents(dirname(__DIR__).'/admin/radar-regional.php');
$start=strpos($source,'function tvs_radar_select_backlog_ids_v13(');
$end=strpos($source,'function tvs_radar_run_backlog_batch_v13(',$start);
if($start===false || $end===false) throw new RuntimeException('Funcoes do Radar nao encontradas');
$selector=substr($source,$start,$end-$start);
eval($selector);
$cities=['Sumaré','Paulínia','Campinas'];
function tvs_radar_discovery_read(){return $GLOBALS['fixture_discovery'];}
function tvs_radar_backlog_candidate_eligible_v13($cand){return !empty($cand['eligible']) && (int)($cand['next_retry']??0)<=time();}
function tvs_radar_is_google_news_url($url){return str_contains((string)$url,'news.google.com');}
function check_case($yes,$message){if(!$yes) throw new RuntimeException($message);}
$GLOBALS['fixture_discovery']=[];
foreach($cities as $city){
  for($i=0;$i<12;$i++) $GLOBALS['fixture_discovery'][]=['id'=>$city.'-'.$i,'city'=>$city,'eligible'=>true,'url'=>'https://example.org/news/'.$i,'sf_score'=>80,'pipeline_attempts'=>0];
}
$ids=tvs_radar_select_backlog_ids_v13(20);
check_case(count($ids)===20,'Lote deveria selecionar 20 pautas elegiveis');
check_case(count(array_unique($ids))===20,'IDs duplicados no lote');
foreach($cities as $city) check_case(count(array_filter($ids,fn($id)=>str_starts_with($id,$city.'-')))>0,'Cidade ignorada: '.$city);
$GLOBALS['fixture_discovery']=[];
for($i=0;$i<30;$i++) $GLOBALS['fixture_discovery'][]=['id'=>'Campinas-'.$i,'city'=>'Campinas','eligible'=>true,'url'=>'https://example.org/item/'.$i];
check_case(count(tvs_radar_select_backlog_ids_v13(20))===20,'Limite indevido por cidade no seletor');
for($i=0;$i<25;$i++) $GLOBALS['fixture_discovery'][$i]['next_retry']=time()+3600;
check_case(count(tvs_radar_select_backlog_ids_v13(20))===5,'Retry futuro nao respeitado');
check_case(count(tvs_radar_select_backlog_ids_v13(3))===3,'Limite menor nao respeitado');
check_case(str_contains($source,"'backlog_v13'=>true"),'Flag de backlog ausente');
check_case(str_contains($source,"'ignore_ready_quota'=>true"),'Cota de prontas indevidamente aplicada ao backlog');
check_case(str_contains($source,'array_intersect_key($actuallyProcessed,array_fill_keys($ids,true))'),'Metrica de IDs efetivos ausente');
echo "RADAR_BACKLOG_BATCH_V13_TEST=PASS\n";
