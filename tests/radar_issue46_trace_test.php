<?php
// Regression of the actual selector and shared gate logic, without real data or API calls.
$source=file_get_contents(dirname(__DIR__).'/admin/radar-regional.php');
$start=strpos($source,'function tvs_radar_backlog_exclusion_reason_v13(');
$end=strpos($source,'function tvs_radar_run_backlog_batch_v13(',$start);
if($start===false || $end===false) throw new RuntimeException('Radar functions not found');
eval(substr($source,$start,$end-$start));
$cities=['Sumaré','Campinas','Paulínia'];
$fixture_discovery=[];
function tvs_radar_discovery_read(){return $GLOBALS['fixture_discovery'];}
function tvs_radar_is_google_news_url($url){return str_contains((string)$url,'news.google.com');}
function tvs_radar_enrichment_due($row){return empty($row['retry_future']);}
function assert_gate($item,$expected){
  $actual=tvs_radar_backlog_exclusion_reason_v13($item);
  if($actual!==$expected) throw new RuntimeException("Expected {$expected}, got {$actual}");
}
$valid=['id'=>'p-1','city'=>'Sumaré','entry_audit_status'=>'aprovada_para_enriquecimento','pipeline_stage'=>'aguardando_enriquecimento','url'=>'https://example.org/article'];
assert_gate($valid,'');
assert_gate(array_replace($valid,['entry_audit_status'=>'']),'auditoria_pendente');
assert_gate(array_replace($valid,['pipeline_attempts'=>101]),'limite_global_tentativas');
assert_gate(array_replace($valid,['pipeline_stage'=>'fonte_esgotada']),'etapa_bloqueada_fonte_esgotada');
assert_gate(array_replace($valid,['pipeline_stage'=>'aguardando_fonte','url'=>'https://news.google.com/rss/articles/a']),'fonte_google_pendente');
assert_gate(array_replace($valid,['deadline_at'=>'2020-01-01']),'prazo_vencido_ou_invalido');
assert_gate(array_replace($valid,['source_date'=>'2020-01-01']),'fonte_antiga_sem_fato_novo');
assert_gate(array_replace($valid,['retry_future'=>true]),'retry_ou_ttl_nao_vencido');
if(tvs_radar_backlog_exclusion_reason_v13($valid,'Campinas')!=='cidade_divergente') throw new RuntimeException('City mismatch');
for($i=1;$i<=16;$i++){
  $fixture_discovery[]=array_replace($valid,[
    'id'=>'p-'.$i,
    'city'=>$cities[($i-1)%3],
    'sf_score'=>100-$i
  ]);
}
$GLOBALS['fixture_discovery']=$fixture_discovery;
$selected=tvs_radar_select_backlog_ids_v13(16);
if(count($selected)!==16 || count(array_unique($selected))!==16) throw new RuntimeException('16 candidates not selected');
$byId=[];
foreach($fixture_discovery as $i=>$item){
  // Replicate a race: 14 items became retry-delayed after selection.
  if($i>=2) $item['retry_future']=true;
  $byId[$item['id']]=$item;
}
$eligible=[]; $excluded=[];
foreach($selected as $id){
  $reason=tvs_radar_backlog_exclusion_reason_v13($byId[$id]);
  if($reason==='') $eligible[]=$id;
  else $excluded[$id]=$reason;
}
if(count($eligible)!==2 || count($excluded)!==14) throw new RuntimeException('16/2/14 eligibility race not reproduced');
foreach($excluded as $reason) if($reason!=='retry_ou_ttl_nao_vencido') throw new RuntimeException('Untraceable deferred item');
if(count(tvs_radar_select_backlog_ids_v13(20))!==16) throw new RuntimeException('Unexpected selector count');
if(!str_contains($source,'Histórico dos últimos lotes Radar 1.3')) throw new RuntimeException('Admin history missing');
if(!str_contains($source, "'reason_code'".'=>'.'$gate')) throw new RuntimeException('Per-item code missing');
if(!str_contains($source,"'excluded_before_execution'") || !str_contains($source,"'rank'")) throw new RuntimeException('Execution alignment missing');
if(!str_contains($source,"'publication_eligible'")) throw new RuntimeException('Human editorial gates missing');
// A pauta pronta encerra apenas o ciclo normal, nunca os outros IDs do backlog.
$continueBacklog="if(empty(\$options['backlog_v13'])) break;";
if(!str_contains($source,$continueBacklog)) throw new RuntimeException('Backlog still stops at first ready story per city');
if(!str_contains($source,"continue; // próxima pauta selecionada")) throw new RuntimeException('Backlog re-enters fallback on already queued story');
if(!str_contains($source,'isset($_GET[\'radar_batch_status\'])')) throw new RuntimeException('Missing read-only batch status endpoint');
if(!str_contains($source,"LOCK_SH|LOCK_NB")) throw new RuntimeException('Batch status endpoint must inspect active lock nonblocking');
if(!str_contains($source,"window.setInterval(inspectBacklogStatus,10000)")) throw new RuntimeException('Missing client status polling');
if(!str_contains($source,"function tvs_radar_execute_full_cycle_v13()")) throw new RuntimeException('Single integrated Radar cycle missing');
if(!str_contains($source,'tvs_radar_run_backlog_batch_v13(20,$lock,$run)')) throw new RuntimeException('Integrated Radar must share collection lock with batch');
if(!str_contains($source,"'action' value='execute_radar'") && !str_contains($source,'name="action" value="execute_radar"')) throw new RuntimeException('Single main action absent');
if(str_contains($source,'>Atualizar Agora</button>') || str_contains($source,'>Processar próximo lote de 20</button>')) throw new RuntimeException('Legacy normal buttons remain visible');
if(!str_contains($source,"'cycle_discovered'")) throw new RuntimeException('Discovery count absent from cycle status');
if(!str_contains($source,"'internal_error_count'")) throw new RuntimeException('Internal error diagnostics absent');
if(!str_contains($source,'$errors=max(0,$processed-$persisted)')) throw new RuntimeException('Batch errors must only reflect selected item persistence');
if(!str_contains($source,'function tvs_radar_operational_event(')) throw new RuntimeException('Operational append-only journal absent');
if(!str_contains($source,'FILE_APPEND|LOCK_EX')) throw new RuntimeException('Append locking absent');
if(!str_contains($source,'$line.PHP_EOL')) throw new RuntimeException('NDJSON line delimiter absent');
if(!str_contains($source,"'candidate_result'")) throw new RuntimeException('Per-candidate event missing');
if(!str_contains($source,'id="radar-operational-log"')) throw new RuntimeException('Operations dashboard absent');
if(!str_contains($source,"'batch_finished'")) throw new RuntimeException('Batch result event missing');
echo "RADAR_ISSUE46_SELECTOR_GATE_TEST=PASS\n";
