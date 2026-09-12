<?php
// Cron diário do Radar Regional TV Sumaré para o ambiente VPS.
// O scheduler pode chamar este arquivo periodicamente; a trava abaixo garante no máximo 1 execução por dia.

define('TVS_RADAR_CRON', true);
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once __DIR__.'/monitor_lib.php';

$cronStarted=microtime(true);
$homeLogDir=dirname(dirname(__DIR__)).'/logs';
$appLogDir=dirname(__DIR__).'/logs';
$cronLogDir=(is_dir($homeLogDir) && is_writable($homeLogDir)) ? $homeLogDir : $appLogDir;
if(!is_dir($cronLogDir)) @mkdir($cronLogDir,0775,true);
$cronLogFile=$cronLogDir.'/tvsumare-cron-radar.log';
@file_put_contents($cronLogFile,date('c')." START mode=cron_hostgator pid=".getmypid()."\n",FILE_APPEND|LOCK_EX);
register_shutdown_function(function() use ($cronLogFile,$cronStarted){
  $err=error_get_last();
  if($err && in_array($err['type']??0,[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
    $duration=(int)round((microtime(true)-$cronStarted)*1000);
    @file_put_contents($cronLogFile,date('c')." FATAL duration_ms={$duration} message=".str_replace(["\r","\n"],' ',(string)($err['message']??''))."\n",FILE_APPEND|LOCK_EX);
  }
});

$_SERVER['REQUEST_METHOD']='CRON';
require_once __DIR__.'/radar-regional.php';

$cfg = function_exists('tvs_radar_config') ? tvs_radar_config() : ['per_city'=>6,'auto_daily'=>true,'last_auto_date'=>''];
$today=date('Y-m-d');

if(empty($cfg['auto_daily'])){
  @file_put_contents($cronLogFile,date('c')." SKIP reason=auto_daily_disabled\n",FILE_APPEND|LOCK_EX);
  echo "Radar automatico desativado nas configuracoes.\n";
  exit(0);
}

if((string)($cfg['last_auto_date']??'')===$today){
  @file_put_contents($cronLogFile,date('c')." SKIP reason=already_ran_today date={$today}\n",FILE_APPEND|LOCK_EX);
  echo "Radar automatico ja executado hoje ({$today}).\n";
  exit(0);
}

$n = function_exists('tvs_radar_update_queue')
  ? tvs_radar_update_queue(max(1,min(30,(int)($cfg['per_city']??20))))
  : 0;

$cfg['last_auto_date']=$today;
if(function_exists('tvs_radar_save_config')) tvs_radar_save_config($cfg);

if(function_exists('tvs_radar_save_status')){
  tvs_radar_save_status([
    'last_run'=>date('c'),
    'last_mode'=>'cron_daily',
    'last_generated'=>$n,
    'last_message'=>$n>0?"{$n} matéria(s) gerada(s) pela atualização diária.":'Nenhuma matéria nova gerada na atualização diária.'
  ]);
}

$queue=function_exists('tvs_queue_read') ? tvs_queue_read() : [];
if(!is_array($queue)) $queue=[];

$summary=[
  'date'=>$today,
  'generated_at'=>date('c'),
  'generated'=>$n,
  'new_today'=>0,
  'publishable'=>0,
  'review'=>0,
  'image_review'=>0,
  'dashboard_read_at'=>'',
  'whatsapp_sent_at'=>'',
  'radar_url'=>'admin/radar-regional.php'
];

foreach($queue as $item){
  $created=substr((string)($item['created_at']??''),0,10);
  if($created!==$today) continue;
  if(($item['status']??'aguardando')!=='aguardando') continue;

  $summary['new_today']++;

  if(!empty($item['image_review_required'])){
    $summary['image_review']++;
    continue;
  }

  if(function_exists('tvs_radar_can_direct_approve') && tvs_radar_can_direct_approve($item)){
    $summary['publishable']++;
  } else {
    $summary['review']++;
  }
}

$summary['whatsapp_text']='TV Sumaré — Atualização diária concluída'."\n"
  .$summary['new_today'].' nova(s) matéria(s) no Radar'."\n"
  .$summary['publishable'].' publicável(is)'."\n"
  .$summary['review'].' para revisão editorial'."\n"
  .$summary['image_review'].' para revisão de imagem'."\n"
  .'Acesse o painel da TV Sumaré para conferir.';

$summary['whatsapp_status']='aguardando_configuracao';
$summary['whatsapp_error']='';
$whatsappWebhook=trim((string)(getenv('TVSUMARE_WHATSAPP_WEBHOOK_URL') ?: ''));

if($whatsappWebhook!==''){
  $summary['whatsapp_status']='falha';
  if(function_exists('tvs_outbound_curl_options')){
    $curlOptions=tvs_outbound_curl_options($whatsappWebhook,15);
    if($curlOptions!==null && function_exists('curl_init')){
      $payload=json_encode([
        'event'=>'tvsumare.radar.daily',
        'date'=>$today,
        'message'=>$summary['whatsapp_text'],
        'summary'=>$summary
      ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $ch=curl_init($whatsappWebhook);
      curl_setopt_array($ch,$curlOptions+[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>$payload
      ]);
      $response=curl_exec($ch);
      $httpCode=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
      $curlError=(string)curl_error($ch);
      curl_close($ch);
      if($response!==false && $httpCode>=200 && $httpCode<300){
        $summary['whatsapp_status']='enviado';
        $summary['whatsapp_sent_at']=date('c');
      } else {
        $summary['whatsapp_error']=$curlError!=='' ? $curlError : 'HTTP '.$httpCode;
      }
    } else {
      $summary['whatsapp_error']='Webhook bloqueado pela política de saída ou cURL indisponível.';
    }
  } else {
    $summary['whatsapp_error']='Proteção de saída indisponível.';
  }
}

$notificationFile=dirname(__DIR__).'/data/radar_notification.json';
if(function_exists('tvs_save_json_file')) tvs_save_json_file($notificationFile,$summary);

$duration=(int)round((microtime(true)-$cronStarted)*1000);
@file_put_contents($cronLogFile,date('c')." END generated={$n} new_today={$summary['new_today']} publishable={$summary['publishable']} review={$summary['review']} image_review={$summary['image_review']} whatsapp={$summary['whatsapp_status']} duration_ms={$duration}\n",FILE_APPEND|LOCK_EX);

echo "Radar diario executado. Materias geradas: {$n}; WhatsApp: {$summary['whatsapp_status']}\n";
