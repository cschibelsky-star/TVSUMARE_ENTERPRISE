<?php
$path='/var/www/html/admin/reporter-ia.php';
$code=file_get_contents($path);
if($code===false){
    fwrite(STDERR,"Reporter IA não encontrado\n");
    exit(1);
}

$required=[
    'job state'=>'function rpia_job_state($j)',
    'dedupe key'=>'function rpia_job_dedupe_key($j)',
    'latest approved'=>'function rpia_is_latest_approved_job($job,$jobs)',
    'send lock'=>'send_lock_at',
];
foreach($required as $label=>$marker){
    if(strpos($code,$marker)===false){
        fwrite(STDERR,"Reporter dedupe {$label}: marcador moderno ausente; abortando\n");
        exit(2);
    }
    echo "Reporter dedupe {$label}: fluxo moderno preservado.\n";
}

if(strpos($code,"return 'superseded';")===false){
    $old=<<<'OLD'
  if(in_array($s,['publicado','published'],true)) return 'published';
OLD;
    $new=<<<'NEW'
  if(in_array($s,['publicado','published'],true)) return 'published';
  if(in_array($s,['superseded','substituido','substituído'],true)) return 'superseded';
NEW;
    $count=substr_count($code,$old);
    if($count!==1){
        fwrite(STDERR,"Reporter superseded state: trecho esperado count={$count}; abortando\n");
        exit(3);
    }
    $code=str_replace($old,$new,$code);
    echo "Reporter superseded state: aplicado.\n";
}else{
    echo "Reporter superseded state: já aplicado.\n";
}

if(strpos($code,'function rpia_supersede_ready_duplicates($jobs)')===false){
    $anchor=<<<'ANCHOR'

$msg=''; $err='';
ANCHOR;
    $pos=strpos($code,$anchor);
    if($pos===false){
        fwrite(STDERR,"Reporter dedupe helper anchor não encontrado\n");
        exit(4);
    }
    $helpers=<<<'PHP'

function rpia_supersede_ready_duplicates($jobs){
  $seen=[];
  $changed=false;
  foreach($jobs as $i=>$j){
    if(!empty($j['archived']) || rpia_job_state($j)!=='ready') continue;
    $key=rpia_job_dedupe_key($j);
    if(!isset($seen[$key])){ $seen[$key]=$i; continue; }
    $keep=$seen[$key];
    $keepDate=(string)($jobs[$keep]['created_at']??'');
    $candidateDate=(string)($j['created_at']??'');
    if($candidateDate>$keepDate){
      $archive=$keep;
      $seen[$key]=$i;
      $keep=$i;
    }else{
      $archive=$i;
    }
    $jobs[$archive]['status']='superseded';
    $jobs[$archive]['archived']='1';
    $jobs[$archive]['superseded_by']=(string)($jobs[$keep]['id']??'');
    $jobs[$archive]['superseded_at']=date('c');
    $jobs[$archive]['updated_at']=date('c');
    $changed=true;
  }
  return [$jobs,$changed];
}
PHP;
    $code=substr($code,0,$pos).$helpers.substr($code,$pos);
    echo "Reporter supersede helpers: aplicados.\n";
}else{
    echo "Reporter supersede helpers: já aplicados.\n";
}

if(strpos($code,'$dedupeChanged')===false){
    $old=<<<'OLD'
$jobs=rpia_read('videos_ia.json'); $activeJobs=array_values(array_filter($jobs,function($j){ return empty($j['archived']) && !in_array(rpia_job_state($j),['cancelled','failed','published'],true); })); $historyJobs=array_values(array_filter($jobs,function($j){ return !empty($j['archived']) || in_array(rpia_job_state($j),['cancelled','failed','published'],true); })); $callbackUrl=
OLD;
    $new=<<<'NEW'
$jobs=rpia_read('videos_ia.json'); [$jobs,$dedupeChanged]=rpia_supersede_ready_duplicates($jobs); if($dedupeChanged) rpia_write('videos_ia.json',$jobs); $activeJobs=array_values(array_filter($jobs,function($j){ return empty($j['archived']) && !in_array(rpia_job_state($j),['cancelled','failed','published','superseded'],true); })); $historyJobs=array_values(array_filter($jobs,function($j){ return !empty($j['archived']) || in_array(rpia_job_state($j),['cancelled','failed','published','superseded'],true); })); $callbackUrl=
NEW;
    $count=substr_count($code,$old);
    if($count!==1){
        fwrite(STDERR,"Reporter persistent queue dedupe: trecho esperado count={$count}; abortando\n");
        exit(5);
    }
    $code=str_replace($old,$new,$code);
    echo "Reporter persistent queue dedupe: aplicado.\n";
}else{
    echo "Reporter persistent queue dedupe: já aplicado.\n";
}

if(strpos($code,"b.textContent='Enviando...'")===false){
    $old=<<<'OLD'
<form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="send_heygen"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn orange">Enviar para geração</button></form>
OLD;
    $new=<<<'NEW'
<form method="post" onsubmit="var b=this.querySelector('button'); if(b.disabled) return false; b.disabled=true; b.textContent='Enviando...';"><?=tvs_csrf_field()?><input type="hidden" name="action" value="send_heygen"><input type="hidden" name="job_id" value="<?=rpia_h($j['id']??'')?>"><button class="btn orange">Enviar para geração</button></form>
NEW;
    $count=substr_count($code,$old);
    if($count!==1){
        fwrite(STDERR,"Reporter client double-click guard: trecho esperado count={$count}; abortando\n");
        exit(6);
    }
    $code=str_replace($old,$new,$code);
    echo "Reporter client double-click guard: aplicado.\n";
}else{
    echo "Reporter client double-click guard: já aplicado.\n";
}

if(file_put_contents($path,$code,LOCK_EX)===false){
    fwrite(STDERR,"Falha ao gravar Reporter IA\n");
    exit(7);
}
echo "REPORTER_DEDUP_HARDENING_APPLIED=SIM\n";
