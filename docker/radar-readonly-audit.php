<?php
// Auditoria agregada, somente leitura; sem URLs, títulos ou conteúdo nos logs.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/admin/radar_queue_rules.php';
$items=json_decode(@file_get_contents(dirname(__DIR__).'/data/materias_aprovacao.json')?:'[]',true);
$report=['total'=>0,'ready'=>0,'editor_pending'=>0,'validation_pending'=>0,'reasons'=>[]];
foreach((array)$items as $item){
 if(!is_array($item))continue;
 $report['total']++;
 if(empty($item['ai_editor_processed'])){$report['editor_pending']++;continue;}
 $validation=tvs_radar_queue_item_readiness($item);
 if($validation['ready']){$report['ready']++;continue;}
 $report['validation_pending']++;
 foreach($validation['reasons'] as $reason)$report['reasons'][$reason]=($report['reasons'][$reason]??0)+1;
}
echo 'RADAR_READONLY_AUDIT='.json_encode($report,JSON_UNESCAPED_UNICODE).PHP_EOL;
