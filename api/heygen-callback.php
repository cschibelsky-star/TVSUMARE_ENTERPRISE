<?php
// Webhook público da HeyGen Video Agent v3.
// Token obrigatório; callbacks repetidos ou fora de ordem não podem regredir o job.
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__).'/config.php';

function cb_path($name){ return dirname(__DIR__).'/data/'.$name; }
function cb_read($name){ $p=cb_path($name); if(!file_exists($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function cb_write($name,$data){ $p=cb_path($name); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true); return file_put_contents($p,json_encode(array_values($data),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)!==false; }
function cb_config(){ $p=cb_path('reporter_ia_config.json'); if(!file_exists($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function cb_log($payload){ $p=cb_path('heygen_callbacks.log'); @file_put_contents($p,date('c').' '.json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX); }
function cb_first($arr,$keys,$default=''){ foreach($keys as $k){ if(isset($arr[$k]) && $arr[$k]!==null && $arr[$k]!=='') return $arr[$k]; } return $default; }
function cb_state($job){
  $s=strtolower((string)($job['status']??''));
  if(in_array($s,['publicado','published'],true)) return 'published';
  if(in_array($s,['video_pronto','completed','complete','ready','pronto'],true)||!empty($job['video_url'])||!empty($job['captioned_video_url'])) return 'completed';
  if(in_array($s,['cancelado','cancelled','canceled'],true)) return 'cancelled';
  if(in_array($s,['heygen_falhou','failed','falhou','erro'],true)) return 'failed';
  if(in_array($s,['heygen_agente_processando','processing','enviado','submitted'],true)||!empty($job['heygen_session_id'])||!empty($job['heygen_video_id'])) return 'processing';
  return 'ready';
}
function cb_friendly_failure(){ return 'O provedor não conseguiu concluir este vídeo. Os detalhes técnicos foram registrados para diagnóstico.'; }

$cfg=cb_config();
$expected=trim((string)($cfg['heygen_callback_token']??''));
$given=trim((string)($_GET['token']??''));
if($expected==='' || !hash_equals($expected,$given)){
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'invalid token'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

$raw=file_get_contents('php://input');
if(!is_string($raw) || strlen($raw)>2097152){ http_response_code(413); echo json_encode(['ok'=>false,'error'=>'payload too large']); exit; }
$payload=json_decode($raw,true);
if(!is_array($payload)){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid json']); exit; }
cb_log($payload);
$fingerprint=hash('sha256',$raw);

$data=is_array($payload['data']??null)?$payload['data']:$payload;
$callbackId=(string)cb_first($payload,['callback_id'],'');
if($callbackId==='') $callbackId=(string)cb_first($data,['callback_id'],'');
$sessionId=(string)cb_first($data,['session_id','id'],'');
$videoId=(string)cb_first($data,['video_id','id'],'');
$status=(string)cb_first($data,['status','video_status','session_status'],'');
$videoUrl=(string)cb_first($data,['video_url','download_url','url'],'');
$captionedUrl=(string)cb_first($data,['captioned_video_url'],'');
$thumb=(string)cb_first($data,['thumbnail_url','thumb'],'');
$failure=(string)cb_first($data,['failure_message','error','message'],'');
$statusLower=strtolower($status);

$jobs=cb_read('videos_ia.json');
$found=false; $duplicate=false; $ignored=false;
foreach($jobs as $i=>$job){
  $match=false;
  if($callbackId!=='' && (($job['id']??'')===$callbackId || ($job['heygen_callback_id']??'')===$callbackId)) $match=true;
  if(!$match && $sessionId!=='' && (($job['heygen_session_id']??'')===$sessionId)) $match=true;
  if(!$match && $videoId!=='' && (($job['heygen_video_id']??'')===$videoId)) $match=true;
  if(!$match) continue;
  $found=true;
  if(($job['heygen_last_callback_hash']??'')===$fingerprint){ $duplicate=true; break; }

  $current=cb_state($job);
  $hasReadyPayload=($videoUrl!=='' || $captionedUrl!=='' || in_array($statusLower,['completed','complete','ready'],true));
  $isFailure=in_array($statusLower,['failed','error'],true);

  // Estados terminais não regridem por callback atrasado. Um payload com vídeo pronto pode
  // recuperar apenas jobs em processamento/falha; nunca altera um item já publicado/cancelado.
  if(in_array($current,['published','cancelled'],true)){
    $jobs[$i]['heygen_last_callback_hash']=$fingerprint;
    $jobs[$i]['heygen_callback_ignored_at']=date('c');
    $ignored=true;
    break;
  }
  if($current==='completed' && !$hasReadyPayload){
    $jobs[$i]['heygen_last_callback_hash']=$fingerprint;
    $jobs[$i]['heygen_callback_ignored_at']=date('c');
    $ignored=true;
    break;
  }

  $jobs[$i]['heygen_callback_received_at']=date('c');
  $jobs[$i]['heygen_last_callback_hash']=$fingerprint;
  if($sessionId!=='') $jobs[$i]['heygen_session_id']=$sessionId;
  if($videoId!=='') $jobs[$i]['heygen_video_id']=$videoId;
  if($status!==''){
    $jobs[$i]['heygen_video_status']=$status;
    $jobs[$i]['heygen_session_status']=$status;
  }
  if($videoUrl!=='' || $captionedUrl!==''){
    if($videoUrl!=='') $jobs[$i]['video_url']=$videoUrl;
    if($captionedUrl!=='') $jobs[$i]['captioned_video_url']=$captionedUrl;
    if($thumb!=='') $jobs[$i]['thumb']=$thumb;
    $jobs[$i]['status']='video_pronto';
    unset($jobs[$i]['heygen_failure'],$jobs[$i]['technical_error']);
  } elseif($isFailure){
    $jobs[$i]['status']='heygen_falhou';
    $jobs[$i]['heygen_failure']=cb_friendly_failure();
    $jobs[$i]['technical_error']=substr($failure!==''?$failure:'Falha informada pelo provedor.',0,1200);
  } else {
    $jobs[$i]['status']='heygen_agente_processando';
  }
  $jobs[$i]['updated_at']=date('c');
  break;
}
if($found && !$duplicate) cb_write('videos_ia.json',$jobs);

echo json_encode(['ok'=>true,'matched'=>$found,'duplicate'=>$duplicate,'ignored'=>$ignored],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
