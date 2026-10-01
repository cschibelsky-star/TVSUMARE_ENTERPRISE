<?php
// Radar de descoberta contínua da TV Sumaré.
// Responsabilidade exclusiva: descobrir e encaminhar novas pautas para o pipeline.
// Backlog, migrações, retenção e demais rotinas editoriais permanecem em admin/cron_radar.php.

define('TVS_RADAR_CRON', true);
require_once __DIR__.'/config.php';
require_once __DIR__.'/admin/gemini.php';
require_once __DIR__.'/admin/monitor_lib.php';

$_SERVER['REQUEST_METHOD']='CRON';
require_once __DIR__.'/admin/radar-regional.php';

$offlineResolutionMarker=__DIR__.'/data/source_resolution_offline_v14_done.json';
if(!is_file($offlineResolutionMarker)){
  $report=tvs_radar_resolve_google_backlog_offline(80);
  tvs_save_json_file($offlineResolutionMarker,$report);
  echo 'SOURCE_RESOLUTION_OFFLINE_V14 '.json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
  exit(0);
}

// Antes do Editor IA, corrige matérias já editadas que ficaram bloqueadas
// apenas porque a URL salva aponta para uma página de listagem do veículo.
$queueUrlRepair=function_exists('tvs_radar_repair_queue_listing_urls')
  ? tvs_radar_repair_queue_listing_urls(20)
  : ['attempted'=>0,'resolved'=>0,'ready'=>0,'failed'=>0];

echo 'QUEUE_LISTING_URL_REPAIR '.json_encode($queueUrlRepair,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";

// Recuperação editorial é uma fila contínua e independente da descoberta.
// Na primeira execução desta versão, força uma passagem única para destravar
// itens que estavam aguardando janelas antigas de retry.
$editorRecoveryMarker=__DIR__.'/data/editor_queue_forced_recovery_20261001_done.json';
if(!is_file($editorRecoveryMarker) && function_exists('tvs_radar_force_editor_queue_pass')){
  $forcedEditorRecovery=tvs_radar_force_editor_queue_pass(20,true);
  tvs_save_json_file($editorRecoveryMarker,$forcedEditorRecovery);
  echo 'EDITOR_QUEUE_FORCED_RECOVERY_V2 '.json_encode($forcedEditorRecovery,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}

$editorRecovery=function_exists('tvs_radar_force_editor_queue_pass')
  ? tvs_radar_force_editor_queue_pass(20,false)
  : ['attempted'=>0,'recovered'=>0,'after_ready'=>0,'after_pending'=>0];

echo 'EDITOR_QUEUE_RECOVERY '.json_encode($editorRecovery,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";

$started=microtime(true);
$cfg=function_exists('tvs_radar_config') ? tvs_radar_config() : ['per_city'=>20];
$perCity=max(1,min(30,(int)($cfg['per_city']??20)));

// Fila dedicada de resolução de fonte: roda a cada ciclo de descoberta, mas em
// pequenos lotes para não monopolizar o scheduler. Não chama Repórter/Editor IA
// e nunca publica; apenas tenta converter Google News em URL original validada.
$sourceResolution=function_exists('tvs_radar_process_due_source_resolution')
  ? tvs_radar_process_due_source_resolution(12)
  : ['processed'=>0,'resolved'=>0,'retriable'=>0,'final'=>0,'remaining_due'=>0];

echo 'SOURCE_RESOLUTION_QUEUE '.json_encode($sourceResolution,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";

// Persiste imediatamente a telemetria do resolvedor antes da coleta/enriquecimento,
// que pode levar muitos minutos. Assim o painel não fica mostrando um ciclo antigo
// enquanto o restante do Radar ainda está processando.
if(function_exists('tvs_radar_status') && function_exists('tvs_radar_save_status')){
  $sourceStatus=tvs_radar_status();
  $sourceStatus=is_array($sourceStatus)?$sourceStatus:[];
  $sourceStatus['last_source_resolution_run']=(string)($sourceResolution['executed_at']??date('c'));
  $sourceStatus['last_source_resolution_processed']=(int)($sourceResolution['processed']??0);
  $sourceStatus['last_source_resolution_resolved']=(int)($sourceResolution['resolved']??0);
  $sourceStatus['last_source_resolution_remaining_due']=(int)($sourceResolution['remaining_due']??0);
  $sourceStatus['source_resolution_in_progress']=1;
  tvs_radar_save_status($sourceStatus);
}

$n=function_exists('tvs_radar_update_queue')
  ? tvs_radar_update_queue($perCity)
  : 0;

$status=function_exists('tvs_radar_status') ? tvs_radar_status() : [];
$status=array_merge(is_array($status)?$status:[],[
  'last_discovery_run'=>date('c'),
  'last_discovery_generated'=>$n,
  'last_discovery_interval_minutes'=>15,
  'last_source_resolution_run'=>(string)($sourceResolution['executed_at']??date('c')),
  'last_source_resolution_processed'=>(int)($sourceResolution['processed']??0),
  'last_source_resolution_resolved'=>(int)($sourceResolution['resolved']??0),
  'last_source_resolution_remaining_due'=>(int)($sourceResolution['remaining_due']??0),
  'source_resolution_in_progress'=>0,
  'last_discovery_message'=>$n>0
    ? "{$n} matéria(s) encaminhada(s) pela descoberta contínua."
    : 'Nenhuma pauta nova encaminhada nesta rodada de descoberta.'
]);

if(function_exists('tvs_radar_save_status')){
  tvs_radar_save_status($status);
}

$duration=(int)round((microtime(true)-$started)*1000);
echo "RADAR_DISCOVERY generated={$n} per_city={$perCity} duration_ms={$duration}\n";
