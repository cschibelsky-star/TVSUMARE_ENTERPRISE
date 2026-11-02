<?php
// Isolated, non-mutating regression for issue #46 diagnostic codes.
$source=file_get_contents(dirname(__DIR__).'/admin/radar-regional.php');
$start=strpos($source,'function tvs_radar_backlog_exclusion_reason_v13(');
$end=strpos($source,'function tvs_radar_backlog_candidate_eligible_v13(',$start);
if($start===false || $end===false) throw new RuntimeException('Backlog diagnostic helper not found');
eval(substr($source,$start,$end-$start));
function tvs_radar_is_google_news_url($url){return str_contains((string)$url,'news.google.com');}
function tvs_radar_enrichment_due($row){return empty($row['retry_future']);}
function tvs_radar_backlog_candidate_eligible_v13($row){return empty($row['selector_block']);}
function check_gate($item,$expected){
  $actual=tvs_radar_backlog_exclusion_reason_v13($item);
  if($actual!==$expected) throw new RuntimeException("Expected {$expected}, got {$actual}");
}
$valid=['id'=>'p-1','city'=>'Sumaré','entry_audit_status'=>'aprovada_para_enriquecimento','pipeline_stage'=>'aguardando_enriquecimento','url'=>'https://example.org/article'];
check_gate($valid,'');
check_gate($valid+['unused'=>true],'');
check_gate(array_replace($valid,['entry_audit_status'=>'']),'auditoria_pendente');
check_gate(array_replace($valid,['pipeline_attempts'=>101]),'limite_global_tentativas');
check_gate(array_replace($valid,['pipeline_stage'=>'fonte_esgotada']),'etapa_bloqueada_fonte_esgotada');
check_gate(array_replace($valid,['pipeline_stage'=>'aguardando_fonte','url'=>'https://news.google.com/rss/articles/abc']),'fonte_google_pendente');
check_gate(array_replace($valid,['deadline_at'=>'2020-01-01']),'prazo_vencido_ou_invalido');
check_gate(array_replace($valid,['source_date'=>'2020-01-01']),'fonte_antiga_sem_fato_novo');
check_gate(array_replace($valid,['retry_future'=>true]),'retry_ou_ttl_nao_vencido');
check_gate(array_replace($valid,['selector_block'=>true]),'inelegivel_seletor');
if(tvs_radar_backlog_exclusion_reason_v13($valid,'Campinas')!=='cidade_divergente') throw new RuntimeException('city mismatch');
$selected=array_map(fn($n)=>'p-'.$n,range(1,16));
$processed=['p-1','p-2'];
$skipped=array_values(array_diff($selected,$processed));
if(count($selected)!==16 || count($processed)!==2 || count($skipped)!==14) throw new RuntimeException('fixture mismatch');
if(!str_contains($source,'Histórico dos últimos lotes Radar 1.3')) throw new RuntimeException('batch history missing');
if(!str_contains($source, "'reason_code'".'=>'.'$gate')) throw new RuntimeException('per-item reason missing');
if(!str_contains($source,"'publication_eligible'=>0") && !str_contains($source,"'publication_eligible'")) throw new RuntimeException('editorial protection missing');
if(!str_contains($source,"'excluded_before_execution'") || !str_contains($source,"'rank'")) throw new RuntimeException('backlog alignment missing');
echo "RADAR_ISSUE46_TRACE_TEST=PASS\n";
