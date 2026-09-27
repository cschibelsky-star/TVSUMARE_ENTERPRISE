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

$started=microtime(true);
$cfg=function_exists('tvs_radar_config') ? tvs_radar_config() : ['per_city'=>20];
$perCity=max(1,min(30,(int)($cfg['per_city']??20)));

$n=function_exists('tvs_radar_update_queue')
  ? tvs_radar_update_queue($perCity)
  : 0;

$status=function_exists('tvs_radar_status') ? tvs_radar_status() : [];
$status=array_merge(is_array($status)?$status:[],[
  'last_discovery_run'=>date('c'),
  'last_discovery_generated'=>$n,
  'last_discovery_interval_minutes'=>15,
  'last_discovery_message'=>$n>0
    ? "{$n} matéria(s) encaminhada(s) pela descoberta contínua."
    : 'Nenhuma pauta nova encaminhada nesta rodada de descoberta.'
]);

if(function_exists('tvs_radar_save_status')){
  tvs_radar_save_status($status);
}

$duration=(int)round((microtime(true)-$started)*1000);
echo "RADAR_DISCOVERY generated={$n} per_city={$perCity} duration_ms={$duration}\n";
