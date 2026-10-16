<?php
// Smoke sintético da deduplicação operacional do Repórter IA.
$jobs=[
 ['id'=>'old','news_id'=>'n1','title'=>'Pauta A','status'=>'roteiro_pronto','created_at'=>'2026-08-10T10:00:00-03:00'],
 ['id'=>'new','news_id'=>'n1','title'=>'Pauta A','status'=>'roteiro_pronto','created_at'=>'2026-08-10T11:00:00-03:00'],
 ['id'=>'other','news_id'=>'n2','title'=>'Pauta B','status'=>'roteiro_pronto','created_at'=>'2026-08-10T09:00:00-03:00'],
];
function state1($j){$s=strtolower((string)($j['status']??'roteiro_pronto'));return $s==='superseded'?'superseded':'ready';}
function key1($j){$n=trim((string)($j['news_id']??''));return $n!==''?'news:'.$n:'title:'.strtolower(trim((string)($j['title']??'')));}
$seen=[];$changed=false;
foreach($jobs as $i=>$j){if(state1($j)!=='ready')continue;$k=key1($j);if(!isset($seen[$k])){$seen[$k]=$i;continue;}$keep=$seen[$k];$a=(string)$jobs[$keep]['created_at'];$b=(string)$j['created_at'];if($b>$a){$old=$keep;$seen[$k]=$i;$keep=$i;$archive=$old;}else{$archive=$i;}$jobs[$archive]['status']='superseded';$jobs[$archive]['archived']='1';$jobs[$archive]['superseded_by']=$jobs[$keep]['id'];$changed=true;}
$active=array_values(array_filter($jobs,fn($j)=>empty($j['archived'])&&state1($j)==='ready'));
$ids=array_column($active,'id');sort($ids);$expected=['new','other'];sort($expected);
if(!$changed||$ids!==$expected){fwrite(STDERR,"REPORTER_DEDUP_TEST=FAIL\n");exit(1);}echo "REPORTER_DEDUP_TEST=PASS active=".implode(',',$ids)."\n";
