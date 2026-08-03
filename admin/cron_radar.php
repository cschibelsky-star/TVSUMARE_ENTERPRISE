<?php
// Cron opcional do Radar Regional TV Sumaré.
// Use apenas se quiser execução real automática à meia-noite pelo cPanel.
// Comando sugerido:
// 0 0 * * * /usr/local/bin/php -q /home/USUARIO/public_html/admin/cron_radar.php >/dev/null 2>&1

define('TVS_RADAR_CRON', true);
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once __DIR__.'/monitor_lib.php';

$_SERVER['REQUEST_METHOD']='CRON';
require_once __DIR__.'/radar-regional.php';

$cfg = function_exists('tvs_radar_config') ? tvs_radar_config() : ['per_city'=>6];
$n = function_exists('tvs_radar_update_queue') ? tvs_radar_update_queue(max(1,min(30,(int)($cfg['per_city']??20)))) : 0;
if(function_exists('tvs_radar_save_status')){
  tvs_radar_save_status([
    'last_run'=>date('c'),
    'last_mode'=>'cron_meia_noite',
    'last_generated'=>$n,
    'last_message'=>$n>0?"{$n} matéria(s) gerada(s) pelo cron.":'Nenhuma matéria nova gerada pelo cron.'
  ]);
}
echo "Radar executado. Materias geradas: {$n}\n";
