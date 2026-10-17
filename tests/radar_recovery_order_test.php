<?php
// Exercise production functions without booting the dashboard or calling providers.
$source=file_get_contents(dirname(__DIR__).'/admin/radar-regional.php');
function recovery_load($source,$name){
  $tokens=token_get_all($source); $capture=false; $found=false; $depth=0; $code='';
  for($i=0;$i<count($tokens);$i++){
    $token=$tokens[$i];
    if(is_array($token) && $token[0]===T_FUNCTION){
      $j=$i+1;
      while(isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0]===T_WHITESPACE) $j++;
      if(isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][1]===$name) $capture=true;
    }
    if(!$capture) continue;
    $text=is_array($token)?$token[1]:$token; $code.=$text;
    if($token==='{'){$depth++;$found=true;}
    if($token==='}' && --$depth===0 && $found){eval($code);return;}
  }
  throw new RuntimeException('Function missing: '.$name);
}
function recovery_assert($ok,$message){if(!$ok)throw new RuntimeException($message);}
$GLOBALS['events']=[]; $GLOBALS['status']=['existing'=>'keep'];
function tvs_radar_process_due_source_resolution($limit){
  $GLOBALS['events'][]=['source',$limit];
  $GLOBALS['resolved']=true;
  return ['executed_at'=>'2026-10-07T12:00:00Z','processed'=>2,'resolved'=>1,'remaining_due'=>3];
}
function tvs_radar_status(){return $GLOBALS['status'];}
function tvs_radar_save_status($status){$GLOBALS['status']=$status;}
function tvs_radar_force_editor_queue_pass($limit,$ignoreSchedule=true){
  recovery_assert(!empty($GLOBALS['resolved']),'Editor ran before source resolution');
  $GLOBALS['events'][]=['editor',$limit,$ignoreSchedule];
  return [];
}
function tvs_radar_select_backlog_ids_v13($limit){
  $GLOBALS['events'][]=['backlog',$limit];
  recovery_assert(count($GLOBALS['events'])===3,'Recovery duplicated or missing');
  return [];
}
function tvs_radar_record_run_telemetry($data){}
recovery_load($source,'tvs_radar_run_backlog_batch_v13');
foreach([1=>4,20=>12,40=>12] as $limit=>$recoveryLimit){
  $GLOBALS['events']=[]; $GLOBALS['resolved']=false;
  $result=tvs_radar_run_backlog_batch_v13($limit);
  recovery_assert($GLOBALS['events']===[['source',$recoveryLimit],['editor',$recoveryLimit,false],['backlog',$limit]],'Source/editor/backlog order or bounds changed');
  recovery_assert($result['status']==='sem_pautas_elegiveis','Empty backlog result changed');
  recovery_assert($GLOBALS['status']['existing']==='keep' && $GLOBALS['status']['last_source_resolution_processed']===2 && $GLOBALS['status']['last_source_resolution_resolved']===1 && $GLOBALS['status']['last_source_resolution_remaining_due']===3,'Source telemetry lost');
}
function tvs_ai_editor_process_article($key,$item,$context){
  $GLOBALS['provider_calls']++;
  $GLOBALS['tvs_ai_last_reason']='provider_limit';
  $GLOBALS['tvs_ai_last_error']='test provider cooldown';
  return null;
}
function tvs_radar_word_count($text){return str_word_count($text);}
recovery_load($source,'tvs_radar_retry_pending_editor_articles');
$done=['title'=>'Done','body'=>'Body','ai_editor_processed'=>1,'ai_editor_attempts'=>1];
$scheduled=['title'=>'Scheduled','body'=>'Body','ai_editor_next_retry_at'=>date('c',time()+7200),'ai_editor_attempts'=>2];
$final=['title'=>'Final','body'=>'Body','ai_editor_attempts'=>3,'ai_editor_stage'=>'manual_review'];
$pending=['title'=>'Due','body'=>'Body','ai_editor_attempts'=>1];
$approval=[$done,$scheduled,$final,$pending]; $GLOBALS['provider_calls']=0;
$result=tvs_radar_retry_pending_editor_articles($approval,12,false);
recovery_assert($GLOBALS['provider_calls']===1 && $result['attempted']===1,'Completed/scheduled/final editor retried');
recovery_assert($approval[0]===$done && $approval[1]===$scheduled,'Protected editor state changed');
recovery_assert($approval[2]['ai_editor_stage']==='manual_review' && $approval[2]['ai_editor_attempts']===3,'Final editor limit changed');
recovery_assert($approval[3]['ai_editor_attempts']===1 && strtotime($approval[3]['ai_editor_next_retry_at'])>=time()+3590,'Transient provider limit consumed attempt or lost cooldown');
echo "RADAR_RECOVERY_ORDER_TEST=PASS\n";
