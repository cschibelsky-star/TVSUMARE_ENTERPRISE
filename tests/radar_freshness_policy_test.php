<?php
require_once dirname(__DIR__).'/admin/radar_freshness_policy.php';
$now=strtotime('2026-11-02 12:00:00 UTC');
$base=['source_date'=>'2026-11-02 10:00:00 UTC'];
$cases=[
 ['recent',$base,true,'recent_24h'],
 ['missing',[],false,'missing_source_date'],
 ['expired',array_merge($base,['deadline_at'=>'2026-11-01 23:00:00 UTC']),false,'expired_deadline'],
 ['old',['source_date'=>'2026-10-30 10:00:00 UTC'],false,'older_than_48h'],
 ['needs_review',['source_date'=>'2026-11-01 10:00:00 UTC'],false,'relevance_review_required'],
 ['verified',['source_date'=>'2026-11-01 10:00:00 UTC','current_relevance_verified'=>true],true,'verified_current'],
 ['new_fact',['source_date'=>'2026-10-30 10:00:00 UTC','verified_new_fact'=>true,'current_relevance_verified'=>true],true,'verified_current'],
];
foreach($cases as [$name,$item,$eligible,$reason]){
 $result=tvs_radar_freshness_classify($item,$now);
 if($result['eligible']!==$eligible || $result['reason']!==$reason){
  fwrite(STDERR,"FAIL {$name}: ".json_encode($result)."\n"); exit(1);
 }
}
echo "PASS: ".count($cases)." freshness cases\n";
