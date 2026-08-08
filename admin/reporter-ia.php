<?php
require_once (is_file(__DIR__.'/includes/outbound_guard.php') ? __DIR__.'/includes/outbound_guard.php' : dirname(__DIR__).'/includes/outbound_guard.php');
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once __DIR__.'/monitor_lib.php';
require_once dirname(__DIR__).'/includes/heygen_helper.php';
$activeAdmin='reporter_ia';

function rpia_h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function rpia_path($name){ return dirname(__DIR__).'/data/'.$name; }
function rpia_read($name){ $p=rpia_path($name); if(!file_exists($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function rpia_write($name,$data){ $p=rpia_path($name); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true); file_put_contents($p,json_encode(array_values($data),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
function rpia_config_read(){ $p=rpia_path('reporter_ia_config.json'); if(!file_exists($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function rpia_config_save($cfg){ $p=rpia_path('reporter_ia_config.json'); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true); file_put_contents($p,json_encode($cfg,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
function rpia_find_news($id){ foreach(rpia_read('noticias.json') as $n){ if(($n['id']??'')===$id) return $n; } return null; }
function rpia_find_videojob($id,&$idx=null){ $jobs=rpia_read('videos_ia.json'); foreach($jobs as $k=>$j){ if(($j['id']??'')===$id){ $idx=$k; return [$j,$jobs]; } } return [null,$jobs]; }
function rpia_clean_script($text){ $text=tvs_clean_text($text); return preg_replace('/\s+/u',' ',trim($text)); }
function rpia_bool($v){ return in_array((string)$v,['1','true','on','yes','sim'],true); }
function rpia_site_url(){ global $site_url; $url=trim((string)($site_url??'')); return rtrim($url!==''?$url:'https://tvsumare.com.br','/'); }
function rpia_abs_url($path){ return preg_match('~^https?://~i',(string)$path)?(string)$path:rpia_site_url().'/'.ltrim((string)$path,'/'); }
function rpia_new_token(){ return bin2hex(random_bytes(20)); }
function rpia_heygen_key($cfg){ $cfg=tvs_heygen_load_config(is_array($cfg)?$cfg:[]); return trim((string)($cfg['heygen_api_key']??'')); }

function rpia_normalize_status($status){
  $status=trim((string)$status);
  $map=[
    ''=>'roteiro_pronto','roteiro'=>'roteiro_pronto','roteiro_aprovado'=>'roteiro_pronto',
    'generating'=>'processando','heygen_agente_processando'=>'processando',
    'video_pronto'=>'video_pronto','publicado'=>'publicado','cancelado'=>'cancelado',
    'heygen_falhou'=>'falhou','failed'=>'falhou','sem_franquia'=>'sem_franquia'
  ];
  return $map[$status]??$status;
}
function rpia_status_label($status){
  $s=rpia_normalize_status($status);
  $labels=['roteiro_pronto'=>'Roteiro pronto','processando'=>'Processando vídeo','video_pronto'=>'Vídeo pronto','publicado'=>'Publicado','cancelado'=>'Cancelado','falhou'=>'Falhou','sem_franquia'=>'Franquia indisponível'];
  return $labels[$s]??ucfirst(str_replace('_',' ',$s));
}
function rpia_can_send($job){ return in_array(rpia_normalize_status($job['status']??''),['roteiro_pronto','falhou','sem_franquia'],true); }
function rpia_can_check($job){ return rpia_normalize_status($job['status']??'')==='processando' && (!empty($job['heygen_session_id']) || !empty($job['heygen_video_id'])); }
function rpia_is_terminal($job){ return in_array(rpia_normalize_status($job['status']??''),['publicado','cancelado'],true); }

function rpia_log_error($jobId,$context,$technical,$userMessage){
  $items=rpia_read('reporter_ia_errors.json');
  array_unshift($items,['id'=>'err_'.date('YmdHis').'_'.bin2hex(random_bytes(2)),'job_id'=>$jobId,'context'=>$context,'technical'=>tvs_substr((string)$technical,0,3000),'user_message'=>$userMessage,'created_at'=>date('c')]);
  rpia_write('reporter_ia_errors.json',array_slice($items,0,300));
}
function rpia_error_info($technical){
  $raw=(string)$technical;
  $low=mb_strtolower($raw,'UTF-8');
  if(strpos($low,'insufficient credit')!==false || strpos($low,'credits')!==false || strpos($low,'credit')!==false){
    return ['status'=>'sem_franquia','message'=>'Serviço de vídeo temporariamente indisponível por limite de franquia/créditos. A operação foi preservada e pode ser retomada após a regularização.'];
  }
  if(strpos($low,'rate limit')!==false || strpos($low,'too many requests')!==false || strpos($low,'429')!==false){
    return ['status'=>'falhou','message'=>'O serviço de vídeo está temporariamente sobrecarregado. Aguarde alguns minutos e tente novamente.'];
  }
  if(strpos($low,'unauthorized')!==false || strpos($low,'forbidden')!==false || strpos($low,'401')!==false || strpos($low,'403')!==false){
    return ['status'=>'falhou','message'=>'A integração de vídeo precisa de revisão administrativa. Nenhuma nova tentativa automática será feita.'];
  }
  return ['status'=>'falhou','message'=>'Não foi possível concluir esta etapa do vídeo. O erro técnico foi registrado para diagnóstico e o roteiro foi preservado.'];
}

function rpia_heygen_request($method,$endpoint,$key,$payload=null,$timeout=35){
  if(trim((string)$key)==='') return ['ok'=>false,'http'=>0,'error'=>'Chave HeyGen não configurada.'];
  if(!function_exists('curl_init')) return ['ok'=>false,'http'=>0,'error'=>'cURL não está habilitado no servidor.'];
  $url='https://api.heygen.com'.$endpoint;
  $headers=['x-api-key: '.$key,'Accept: application/json'];
  $outboundOptions=tvs_outbound_curl_options($url,(int)$timeout);
  if($outboundOptions===null) return ['ok'=>false,'http'=>0,'error'=>'URL HeyGen bloqueada pela politica de saida.'];
  $ch=curl_init($url);
  $opts=$outboundOptions+[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers];
  if($payload!==null){ $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $headers[]='Content-Type: application/json'; $opts[CURLOPT_HTTPHEADER]=$headers; $opts[CURLOPT_POSTFIELDS]=$body; }
  curl_setopt_array($ch,$opts);
  $res=curl_exec($ch); $curlErr=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if(is_string($res) && strlen($res)>2097152) return ['ok'=>false,'http'=>$http,'error'=>'Resposta HeyGen excedeu o limite seguro.'];
  if($res===false || $res==='') return ['ok'=>false,'http'=>$http,'error'=>'HeyGen sem resposta: '.$curlErr];
  $json=json_decode($res,true);
  if($http>=400) return ['ok'=>false,'http'=>$http,'error'=>'HeyGen HTTP '.$http.': '.substr($res,0,1200),'raw'=>$json?:$res];
  return ['ok'=>true,'http'=>$http,'data'=>$json?:[],'raw'=>$res];
}

function rpia_local_script($news){
  $title=tvs_clean_text($news['title']??'Atualização regional');
  $cat=tvs_clean_text($news['category']??'notícias');
  $body=tvs_clean_text($news['body']??($news['summary']??''));
  $summary=tvs_first_sentence($body,$news['subtitle']??$title);
  $source=tvs_clean_text($news['source']??'fonte consultada');
  $script="Olá. Esta é uma atualização da TV Sumaré. {$title}. {$summary} ";
  if($cat==='Empregos') $script.="A informação pode interessar trabalhadores e moradores que buscam novas oportunidades na região. ";
  elseif($cat==='Segurança') $script.="O caso mobiliza a atenção da população e segue com acompanhamento das autoridades competentes. ";
  elseif($cat==='Saúde') $script.="A informação é relevante para usuários dos serviços públicos e moradores que dependem do atendimento local. ";
  else $script.="O assunto tem impacto regional e será acompanhado conforme novas informações oficiais forem divulgadas. ";
  return $script."Fonte: {$source}. Para acompanhar outras notícias da região, acesse a TV Sumaré.";
}
function rpia_generate_script($news){
  global $gemini_api_key,$anthropic_api_key,$anthropic_model;
  $base="Título: ".($news['title']??'')."\nCidade: ".($news['city']??'')."\nCategoria: ".($news['category']??'')."\nSubtítulo: ".($news['subtitle']??'')."\nResumo: ".($news['summary']??'')."\nCorpo: ".tvs_substr(tvs_clean_text($news['body']??''),0,2500)."\nFonte: ".($news['source']??'Fonte consultada')."\nURL: ".($news['source_url']??($news['url']??''));
  $prompt="Você é apresentador de telejornal regional da TV Sumaré. Gere um roteiro de vídeo com 45 a 60 segundos. Texto falado natural, profissional e jornalístico; sem markdown; sem dizer que é IA; sem inventar dados; termine convidando o público a acompanhar a TV Sumaré.\n\nMatéria:\n".$base;
  $script='';
  $anthropic=trim((string)($anthropic_api_key??''));
  if($anthropic!=='' && function_exists('curl_init')){
    $url='https://api.anthropic.com/v1/messages'; $outbound=tvs_outbound_curl_options($url,25);
    if($outbound!==null){
      $payload=json_encode(['model'=>($anthropic_model?:'claude-sonnet-4-20250514'),'max_tokens'=>900,'messages'=>[['role'=>'user','content'=>$prompt]]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $ch=curl_init($url); curl_setopt_array($ch,$outbound+[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$anthropic,'anthropic-version: 2023-06-01']]);
      $res=curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      if(is_string($res) && strlen($res)<=2097152 && $http<400){ $j=json_decode($res,true); $script=$j['content'][0]['text']??''; }
    }
  }
  if(trim($script)==='' && trim((string)($gemini_api_key??''))!=='' && function_exists('tvs_gemini_generate_text')){ $r=tvs_gemini_generate_text($gemini_api_key,$prompt,['temperature'=>0.25,'maxOutputTokens'=>900],24); if(!empty($r['ok'])) $script=$r['text']??''; }
  $script=rpia_clean_script($script); return ($script!=='' && tvs_strlen($script)>=120)?$script:rpia_local_script($news);
}
function rpia_video_agent_prompt($job,$cfg){
  $orientation=($cfg['heygen_orientation']??'landscape')==='portrait'?'vertical/portrait 9:16':'landscape 16:9';
  return "Crie um vídeo jornalístico regional para a TV Sumaré. Formato: {$orientation}. Duração alvo: 45 a 60 segundos. Tom profissional, claro, objetivo e sem sensacionalismo. Use o avatar e a voz configurados, legendas e identidade visual limpa. Não invente dados.\n\nTítulo: ".tvs_clean_text($job['title']??'Giro regional TV Sumaré')."\nCidade: ".tvs_clean_text($job['city']??'Região')."\nFonte: ".tvs_clean_text($job['source']??'fonte consultada')."\n\nTexto falado obrigatório:\n".trim((string)($job['script']??''));
}
function rpia_heygen_create($job,$cfg){
  $key=rpia_heygen_key($cfg); if($key==='') return ['ok'=>false,'error'=>'Chave HeyGen não configurada.'];
  $payload=['prompt'=>rpia_video_agent_prompt($job,$cfg),'mode'=>'generate','incognito_mode'=>rpia_bool($cfg['heygen_incognito_mode']??'0')];
  foreach(['avatar_id'=>'heygen_avatar_id','voice_id'=>'heygen_voice_id','style_id'=>'heygen_style_id','brand_kit_id'=>'heygen_brand_kit_id'] as $api=>$local){ $v=trim((string)($cfg[$local]??'')); if($v!=='') $payload[$api]=$v; }
  $orientation=trim((string)($cfg['heygen_orientation']??'landscape')); if(in_array($orientation,['landscape','portrait'],true)) $payload['orientation']=$orientation;
  $token=trim((string)($cfg['heygen_callback_token']??'')); if($token!==''){ $payload['callback_url']=rpia_abs_url('api/heygen-callback.php?token='.rawurlencode($token)); $payload['callback_id']=$job['id']??('job_'.time()); }
  $r=rpia_heygen_request('POST','/v3/video-agents',$key,$payload,45); if(!$r['ok']) return ['ok'=>false,'error'=>$r['error']??'Falha ao criar sessão HeyGen.','raw'=>$r];
  $data=$r['data']['data']??($r['data']??[]); $session=$data['session_id']??''; $video=$data['video_id']??'';
  if($session==='' && $video==='') return ['ok'=>false,'error'=>'HeyGen não retornou session_id nem video_id.','raw'=>$r];
  return ['ok'=>true,'session_id'=>$session,'video_id'=>$video,'status'=>$data['status']??'generating','payload'=>$payload];
}
function rpia_heygen_status($job,$cfg){
  $key=rpia_heygen_key($cfg); if($key==='') return ['ok'=>false,'error'=>'Chave HeyGen não configurada.'];
  $session=trim((string)($job['heygen_session_id']??'')); $video=trim((string)($job['heygen_video_id']??'')); $out=['ok'=>true];
  if($session!==''){
    $r=rpia_heygen_request('GET','/v3/video-agents/'.rawurlencode($session),$key,null,25); if(!$r['ok']) return $r;
    $d=$r['data']['data']??($r['data']??[]); $out['session_status']=$d['status']??''; $out['progress']=$d['progress']??null; if($video==='' && !empty($d['video_id'])) $video=$d['video_id'];
  }
  if($video!==''){
    $r=rpia_heygen_request('GET','/v3/videos/'.rawurlencode($video),$key,null,25); if(!$r['ok']) return $r;
    $d=$r['data']['data']??($r['data']??[]); $out['video_id']=$video; $out['video_status']=$d['status']??''; $out['video_url']=$d['video_url']??''; $out['captioned_video_url']=$d['captioned_video_url']??''; $out['thumb']=$d['thumbnail_url']??''; $out['duration']=$d['duration']??null; $out['failure_code']=$d['failure_code']??''; $out['failure_message']=$d['failure_message']??''; $out['video_page_url']=$d['video_page_url']??'';
  }
  return ($session==='' && $video==='')?['ok'=>false,'error'=>'Este job ainda não possui sessão de vídeo.']:$out;
}
function rpia_publish_video($job){
  $videos=rpia_read('videos.json'); foreach($videos as $v){ if(($v['ia_job_id']??'')===($job['id']??'')) return false; }
  $url=$job['captioned_video_url']??($job['video_url']??''); if(trim((string)$url)==='') return false;
  array_unshift($videos,['id'=>'vid_ia_'.date('YmdHis'),'title'=>$job['title']??'TV Sumaré News','category'=>$job['category']??'Giro da Região','description'=>tvs_substr(tvs_clean_text($job['script']??''),0,180),'url'=>$url,'thumb'=>$job['thumb']??($job['image']??'assets/cat-cidade.svg'),'status'=>'active','featured'=>1,'ia_job_id'=>$job['id']??'','created_at'=>date('c')]);
  rpia_write('videos.json',$videos); return true;
}

$msg=''; $err=''; $cfg=tvs_heygen_load_config(rpia_config_read());
foreach(['heygen_api_key','heygen_avatar_id','heygen_voice_id','heygen_style_id','heygen_brand_kit_id','heygen_orientation','heygen_incognito_mode'] as $k){ if((!isset($cfg[$k]) || trim((string)$cfg[$k])==='') && isset($GLOBALS[$k]) && trim((string)$GLOBALS[$k])!=='') $cfg[$k]=$GLOBALS[$k]; }
if(empty($cfg['heygen_orientation'])) $cfg['heygen_orientation']='landscape';
if(empty($cfg['heygen_callback_token'])) $cfg['heygen_callback_token']=rpia_new_token();
rpia_config_save(tvs_heygen_repair_config($cfg));

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  $action=$_POST['action']??'';
  if($action==='save_config'){
    $postedKey=trim((string)($_POST['heygen_api_key']??'')); if($postedKey!=='' && !preg_match('/^\*+$/',$postedKey)) $cfg['heygen_api_key']=$postedKey;
    foreach(['heygen_avatar_id','heygen_voice_id','heygen_style_id','heygen_brand_kit_id','heygen_orientation'] as $k) $cfg[$k]=trim((string)($_POST[$k]??''));
    $cfg['heygen_incognito_mode']=isset($_POST['heygen_incognito_mode'])?'1':'0'; rpia_config_save($cfg); $msg='Configuração de vídeo salva.';
  }
  elseif($action==='generate_script'){
    $news=rpia_find_news($_POST['news_id']??''); if(!$news) $err='Matéria não encontrada.'; else {
      $jobs=rpia_read('videos_ia.json');
      foreach($jobs as $existing){ if(($existing['news_id']??'')===($news['id']??'') && !rpia_is_terminal($existing)){ $err='Já existe um vídeo em andamento para esta matéria.'; break; } }
      if($err===''){
        $job=['id'=>'job_'.date('YmdHis').'_'.bin2hex(random_bytes(2)),'news_id'=>$news['id']??'','title'=>$news['title']??'TV Sumaré News','city'=>$news['city']??'Região','category'=>$news['category']??'Giro da Região','source'=>$news['source']??'Fonte consultada','image'=>$news['image']??'assets/cat-cidade.svg','script'=>rpia_generate_script($news),'status'=>'roteiro_pronto','created_at'=>date('c')];
        array_unshift($jobs,$job); rpia_write('videos_ia.json',$jobs); $msg='Roteiro gerado. Revise e envie para produção quando estiver pronto.';
      }
    }
  }
  elseif($action==='update_script'){
    $idx=null; [$job,$jobs]=rpia_find_videojob($_POST['job_id']??'',$idx); if(!$job) $err='Roteiro não encontrado.'; elseif(rpia_is_terminal($job)) $err='Este item está encerrado e não pode mais ser editado.'; else { $jobs[$idx]['script']=trim((string)($_POST['script']??'')); $jobs[$idx]['status']='roteiro_pronto'; $jobs[$idx]['updated_at']=date('c'); rpia_write('videos_ia.json',$jobs); $msg='Roteiro salvo.'; }
  }
  elseif($action==='send_heygen'){
    $idx=null; [$job,$jobs]=rpia_find_videojob($_POST['job_id']??'',$idx);
    if(!$job) $err='Roteiro não encontrado.'; elseif(!rpia_can_send($job)) $err='Este vídeo não pode ser enviado novamente no estado atual.'; elseif(tvs_strlen(trim((string)($job['script']??'')))<80) $err='Revise o roteiro antes de enviar para produção.'; else {
      $r=rpia_heygen_create($job,$cfg);
      if(!$r['ok']){
        $technical=$r['error']??'Falha desconhecida'; $info=rpia_error_info($technical); $jobs[$idx]['status']=$info['status']; $jobs[$idx]['heygen_failure']=$info['message']; $jobs[$idx]['heygen_last_error_at']=date('c'); $jobs[$idx]['updated_at']=date('c'); rpia_log_error($job['id']??'','send_heygen',$technical,$info['message']); rpia_write('videos_ia.json',$jobs); $err=$info['message'];
      } else {
        $jobs[$idx]['heygen_session_id']=$r['session_id']; if(!empty($r['video_id'])) $jobs[$idx]['heygen_video_id']=$r['video_id']; $jobs[$idx]['heygen_status']=$r['status']; $jobs[$idx]['heygen_callback_id']=$job['id']; $jobs[$idx]['heygen_payload']=$r['payload']; $jobs[$idx]['heygen_failure']=''; $jobs[$idx]['status']='processando'; $jobs[$idx]['updated_at']=date('c'); rpia_write('videos_ia.json',$jobs); $msg='Vídeo enviado para produção. Use Atualizar status para acompanhar o processamento.';
      }
    }
  }
  elseif($action==='check_heygen'){
    $idx=null; [$job,$jobs]=rpia_find_videojob($_POST['job_id']??'',$idx);
    if(!$job) $err='Vídeo não encontrado.'; elseif(!rpia_can_check($job)) $err='Este item não está aguardando processamento.'; else {
      $r=rpia_heygen_status($job,$cfg);
      if(!$r['ok']){
        $technical=$r['error']??'Falha desconhecida'; $info=rpia_error_info($technical); $jobs[$idx]['status']=$info['status']; $jobs[$idx]['heygen_failure']=$info['message']; $jobs[$idx]['updated_at']=date('c'); rpia_log_error($job['id']??'','check_heygen',$technical,$info['message']); rpia_write('videos_ia.json',$jobs); $err=$info['message'];
      } else {
        if(!empty($r['video_id'])) $jobs[$idx]['heygen_video_id']=$r['video_id']; if(array_key_exists('progress',$r)) $jobs[$idx]['heygen_progress']=$r['progress']; $jobs[$idx]['heygen_session_status']=$r['session_status']??''; $jobs[$idx]['heygen_video_status']=$r['video_status']??'';
        if(!empty($r['video_url'])){ $jobs[$idx]['video_url']=$r['video_url']; $jobs[$idx]['captioned_video_url']=$r['captioned_video_url']??''; $jobs[$idx]['thumb']=$r['thumb'] ?: ($jobs[$idx]['image']??'assets/cat-cidade.svg'); $jobs[$idx]['duration']=$r['duration']??null; $jobs[$idx]['video_page_url']=$r['video_page_url']??''; $jobs[$idx]['status']='video_pronto'; $jobs[$idx]['heygen_failure']=''; $msg='Vídeo pronto para publicação no TV Sumaré Play.'; }
        elseif(($r['video_status']??'')==='failed'){ $technical=($r['failure_message']??'').' '.($r['failure_code']??''); $info=rpia_error_info($technical); $jobs[$idx]['status']=$info['status']; $jobs[$idx]['heygen_failure']=$info['message']; rpia_log_error($job['id']??'','video_failed',$technical,$info['message']); $err=$info['message']; }
        else { $jobs[$idx]['status']='processando'; $msg='Processamento em andamento.'; }
        $jobs[$idx]['updated_at']=date('c'); rpia_write('videos_ia.json',$jobs);
      }
    }
  }
  elseif($action==='publish_video'){
    $idx=null; [$job,$jobs]=rpia_find_videojob($_POST['job_id']??'',$idx); if(!$job || rpia_normalize_status($job['status']??'')!=='video_pronto' || (empty($job['video_url']) && empty($job['captioned_video_url']))) $err='O vídeo ainda não está pronto para publicação.'; else { rpia_publish_video($job); $jobs[$idx]['status']='publicado'; $jobs[$idx]['published_at']=date('c'); rpia_write('videos_ia.json',$jobs); $msg='Vídeo publicado no TV Sumaré Play.'; }
  }
  elseif($action==='cancel_job'){
    $idx=null; [$job,$jobs]=rpia_find_videojob($_POST['job_id']??'',$idx); if(!$job) $err='Item não encontrado.'; elseif(rpia_normalize_status($job['status']??'')==='publicado') $err='Vídeo publicado não pode ser cancelado por esta tela.'; else { $jobs[$idx]['status']='cancelado'; $jobs[$idx]['cancelled_at']=date('c'); $jobs[$idx]['updated_at']=date('c'); rpia_write('videos_ia.json',$jobs); $msg='Item movido para o histórico.'; }
  }
}

$news=rpia_read('noticias.json'); usort($news,function($a,$b){ return strcmp($b['published_at']??$b['created_at']??'', $a['published_at']??$a['created_at']??''); }); $news=array_slice($news,0,30);
$jobs=rpia_read('videos_ia.json'); $activeJobs=[]; $history=[];
foreach($jobs as $j){ if(rpia_is_terminal($j)) $history[]=$j; else $activeJobs[]=$j; }
usort($activeJobs,function($a,$b){ $order=['video_pronto'=>0,'processando'=>1,'roteiro_pronto'=>2,'falhou'=>3,'sem_franquia'=>4]; $sa=rpia_normalize_status($a['status']??''); $sb=rpia_normalize_status($b['status']??''); $oa=$order[$sa]??9; $ob=$order[$sb]??9; if($oa!==$ob) return $oa<=>$ob; return strcmp($b['updated_at']??$b['created_at']??'', $a['updated_at']??$a['created_at']??''); });
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Repórter IA | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=131"><style>
.grid2{display:grid;grid-template-columns:1fr 1.25fr;gap:16px}.job{border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin:10px 0;background:#fff}.pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#1d4ed8;font-size:12px;font-weight:800;margin-right:6px}.pill.ok{background:#dcfce7;color:#166534}.pill.warn{background:#fff7ed;color:#9a3412}.pill.err{background:#fef2f2;color:#b91c1c}.job textarea{min-height:125px}.news-list{max-height:620px;overflow:auto}.news-item{border-bottom:1px solid #e5e7eb;padding:10px 0}.muted{color:#64748b}.mini{font-size:12px}.history summary{cursor:pointer;font-weight:800}.actions form{display:inline-block}@media(max-width:900px){.grid2{grid-template-columns:1fr}}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main">
<div class="top"><div><span class="eyebrow">TV Sumaré Play</span><h1>Repórter IA</h1><p class="muted" style="text-align:left">Fluxo operacional: roteiro → produção → vídeo pronto → publicação. A tela mostra somente a próxima ação válida.</p></div><a class="btn secondary" href="../videos.php" target="_blank">Ver TV Sumaré Play</a></div>
<?php if($msg): ?><div class="notice"><?=rpia_h($msg)?></div><?php endif; ?><?php if($err): ?><div class="notice error"><?=rpia_h($err)?></div><?php endif; ?>
<section class="box"><h2>Integração de vídeo</h2><p class="mini muted"><strong>HeyGen:</strong> <?=rpia_heygen_key($cfg)!==''?'configurada':'não configurada'?> • <strong>Avatar:</strong> <?=rpia_h($cfg['heygen_avatar_id']??'')?> • <strong>Voz:</strong> <?=rpia_h($cfg['heygen_voice_id']??'')?>. Erros técnicos ficam registrados internamente e não são expostos ao operador.</p>
<form method="post" class="form"><?=tvs_csrf_field()?><input type="hidden" name="action" value="save_config"><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px"><div><label>API Key</label><input type="password" name="heygen_api_key" placeholder="preencha somente para trocar"></div><div><label>Avatar ID</label><input name="heygen_avatar_id" value="<?=rpia_h($cfg['heygen_avatar_id']??'')?>"></div><div><label>Voice ID</label><input name="heygen_voice_id" value="<?=rpia_h($cfg['heygen_voice_id']??'')?>"></div><div><label>Style ID</label><input name="heygen_style_id" value="<?=rpia_h($cfg['heygen_style_id']??'')?>"></div><div><label>Brand Kit ID</label><input name="heygen_brand_kit_id" value="<?=rpia_h($cfg['heygen_brand_kit_id']??'')?>"></div><div><label>Orientação</label><select name="heygen_orientation"><option value="landscape" <?=($cfg['heygen_orientation']??'landscape')==='landscape'?'selected':''?>>Landscape</option><option value="portrait" <?=($cfg['heygen_orientation']??'')==='portrait'?'selected':''?>>Portrait</option></select></div></div><label style="display:flex;gap:8px;align-items:center;margin-top:10px"><input type="checkbox" name="heygen_incognito_mode" value="1" <?=rpia_bool($cfg['heygen_incognito_mode']??'0')?'checked':''?>> Não usar memória do agente</label><button class="btn secondary" type="submit" style="margin-top:10px">Salvar integração</button></form></section>
<div class="grid2" style="margin-top:18px"><section class="box"><h2>Matérias aprovadas</h2><p class="muted">Gere um roteiro apenas quando quiser produzir vídeo desta matéria.</p><div class="news-list"><?php foreach($news as $n): ?><div class="news-item"><strong><?=rpia_h($n['title']??'Sem título')?></strong><br><small><?=rpia_h(($n['city']??'Região').' • '.($n['category']??'Notícia').' • '.($n['source']??''))?></small><form method="post" style="margin-top:7px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="generate_script"><input type="hidden" name="news_id" value="<?=rpia_h($n['id']??'')?>"><button class="btn orange" type="submit">Gerar roteiro</button></form></div><?php endforeach; ?></div></section>
<section class="box"><h2>Fila operacional</h2><?php if(!$activeJobs): ?><p class="muted">Nenhum vídeo pendente.</p><?php endif; ?><?php foreach($activeJobs as $j): $s=rpia_normalize_status($j['status']??''); $ready=$s==='video_pronto'; ?><article class="job"><span class="pill <?=$ready?'ok':(in_array($s,['falhou','sem_franquia'],true)?'err':'warn')?>"><?=rpia_h(rpia_status_label($s))?></span><span class="pill"><?=rpia_h($j['category']??'Giro da Região')?></span><h3><?=rpia_h($j['title']??'Vídeo TV Sumaré')?></h3><small class="muted"><?=rpia_h($j['city']??'Região')?><?php if(isset($j['heygen_progress']) && $s==='processando'): ?> • <?=rpia_h($j['heygen_progress'])?>%<?php endif; ?></small><?php if(!empty($j['heygen_failure'])): ?><div class="notice error mini" style="margin-top:8px"><?=rpia_h($j['heygen_failure'])?></div><?php endif; ?>
<?php if(in_array($s,['roteiro_pronto','falhou','sem_franquia'],true)): ?><form method="post" style="margin-top:9px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="update_script"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><textarea name="script"><?=rpia_h($j['script']??'')?></textarea><button class="btn secondary" type="submit">Salvar roteiro</button></form><?php endif; ?>
<div class="actions" style="margin-top:8px"><?php if(rpia_can_send($j)): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="send_heygen"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn orange" type="submit"><?=$s==='roteiro_pronto'?'Enviar para produção':'Tentar novamente'?></button></form><?php endif; ?><?php if(rpia_can_check($j)): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="check_heygen"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn secondary" type="submit">Atualizar status</button></form><?php endif; ?><?php if($ready): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="publish_video"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn" type="submit">Publicar no Play</button></form><a class="btn secondary" href="<?=rpia_h($j['captioned_video_url']??$j['video_url'])?>" target="_blank">Abrir vídeo</a><?php endif; ?><?php if($s!=='video_pronto' && $s!=='processando'): ?><form method="post" onsubmit="return confirm('Mover este item para o histórico?');"><?=tvs_csrf_field()?><input type="hidden" name="action" value="cancel_job"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn secondary" type="submit">Cancelar</button></form><?php endif; ?></div></article><?php endforeach; ?>
<?php if($history): ?><details class="history" style="margin-top:14px"><summary>Histórico (<?=count($history)?>)</summary><?php foreach(array_slice($history,0,30) as $j): ?><div class="news-item"><strong><?=rpia_h($j['title']??'Vídeo')?></strong><br><small><?=rpia_h(rpia_status_label($j['status']??'').' • '.($j['city']??'Região'))?></small></div><?php endforeach; ?></details><?php endif; ?></section></div>
</main></div></body></html>
