<?php
require_once dirname(__DIR__).'/includes/editorial_lifecycle.php';
require_once dirname(__DIR__).'/includes/image_url_helpers.php';
// Public and admin modules each define tvs_clean_text; test real functions in isolation.
function loadProductionFunction($path,$name){
    $tokens=token_get_all(file_get_contents($path));
    $count=count($tokens);
    for($i=0;$i<$count;$i++){
        if(!is_array($tokens[$i]) || $tokens[$i][0]!==T_FUNCTION) continue;
        $j=$i+1;
        while($j<$count && is_array($tokens[$j]) && $tokens[$j][0]===T_WHITESPACE) $j++;
        if(!is_array($tokens[$j]) || $tokens[$j][0]!==T_STRING || $tokens[$j][1]!==$name) continue;
        $code=''; $depth=0; $opened=false;
        for(;$i<$count;$i++){
            $token=$tokens[$i]; $code.=is_array($token)?$token[1]:$token;
            if($token==='{'){ $depth++; $opened=true; }
            if($token==='}' && --$depth===0 && $opened){ eval($code); return; }
        }
    }
    throw new RuntimeException('Production function not found: '.$name);
}
foreach(['tvs_lc','tvs_date_ts','tvs_sort_recent','tvs_is_category_asset','tvs_real_image'] as $name){
    loadProductionFunction(dirname(__DIR__).'/includes/tvs_public_helpers.php',$name);
}
require_once dirname(__DIR__).'/admin/radar_queue_rules.php';
$checks=0;
function check($condition, $message){ global $checks; $checks++; if(!$condition) throw new RuntimeException($message); }
check(tvs_date_ts([])===0,'Missing dates must not become now');
check(tvs_date_ts(['published_at'=>'garbage','created_at'=>'2026-10-01T00:00:00Z'])===1790812800,'Invalid preferred date must fall back to valid legacy date');
check(tvs_date_ts(['published_at'=>'tomorrow'])===0,'Relative dates rejected');
check(tvs_date_ts(['published_at'=>'2026-02-30'])===0,'Calendar overflow rejected');
check(tvs_editorial_display_date(tvs_editorial_timestamp('2026-10-12T01:00:00Z'))==='11/10/2026','Brazil timezone at midnight boundary');
check(tvs_editorial_display_date(0)==='Data não informada','Unknown date label');
$items=[['id'=>'missing'],['id'=>'old','published_at'=>'2026-09-01'],['id'=>'new','published_at'=>'2026-10-01']];
usort($items,'tvs_sort_recent');
check(array_column($items,'id')===['new','old','missing'],'Public ordering deterministic');
$ready=['title'=>'Sumaré anuncia novas atividades culturais na cidade','body'=>str_repeat('Atividades abertas à população com informações oficiais. ',12),'source_url'=>'https://example.com/noticias/sumare-evento','created_at'=>date(DATE_ATOM)];
check(!tvs_radar_queue_item_readiness($ready)['ready'],'Capture time alone cannot confirm source date');
$ready['source_published_at']=date(DATE_ATOM);
check(tvs_radar_queue_item_readiness($ready)['ready'],'Source date qualifies complete candidate');
check(tvs_real_image(['image'=>'javascript:alert(1)','og_image'=>'https://example.com/photo.jpg'])==='https://example.com/photo.jpg','Reject unsupported scheme and try next candidate');
check(tvs_real_image(['image'=>'uploads/missing.jpg','og_image'=>'https://example.com/photo.jpg'])==='https://example.com/photo.jpg','Missing local file does not mask next candidate');
check(tvs_real_image(['image'=>'https://example.com/failed.jpg','unavailable_images'=>['https://example.com/failed.jpg'],'og_image'=>'https://example.com/ok.jpg'])==='https://example.com/ok.jpg','Known unavailable reference does not mask alternate');
check(tvs_real_image(['image'=>'data:image/png;base64,AA=='])==='','Inline non-source reference rejected');
check(tvs_real_image(['image'=>['url'=>'https://example.com/photo.jpg']])==='','Unexpected image shape rejected');
$dir=sys_get_temp_dir().'/tvs-editorial-'.bin2hex(random_bytes(8));
mkdir($dir,0700);
$newsPath=$dir.'/news.json'; $queuePath=$dir.'/queue.json';
$draft=['id'=>'q1','published_at'=>'2026-10-01T12:00:00Z','created_at'=>'2026-10-02T12:00:00Z','source_url'=>'https://example.com/source','audit_extra'=>['retained'=>true]];
$other=['id'=>'q2','title'=>'Untouched'];
$legacy=['id'=>'n0','title'=>'Existing news','unknown_fields'=>['keep'=>true]];
try {
    file_put_contents($newsPath,json_encode([$legacy]));
    file_put_contents($queuePath,json_encode([$draft,$other]));
    $record=tvs_editorial_publication_metadata($draft,'radar','q1','2026-10-12T12:00:00Z');
    $record['id']='n1';
    check($record['source_published_at']==='2026-10-01T12:00:00+00:00','Source date preserved separately');
    check($record['published_at']==='2026-10-12T12:00:00Z','Local publication time');
    check($record['created_at']===$draft['created_at'],'Capture time retained');
    check($record['updated_at']===$record['published_at'],'Update only on editorial mutation');
    $record['origin_snapshot_sha']=hash('sha256',json_encode($draft));
    $result=tvs_editorial_persist_publication($newsPath,$queuePath,'radar','q1',$record);
    check($result['ok'] && $result['queue_cleaned'],'Publication persisted and queue transitioned');
    $saved=tvs_editorial_read_strict($newsPath);
    check($saved[0]===$legacy,'Existing news preserved byte-equivalent fields');
    check($saved[1]['audit_extra']===$draft['audit_extra'],'Editorial metadata preserved');
    check(tvs_editorial_read_strict($queuePath)===[$other],'Unrelated queue entry preserved');
    check(tvs_editorial_matches_publication($draft,$saved),'Publication link excludes correction card');
    check(tvs_editorial_matches_publication(['url'=>$draft['source_url']],$saved),'Discovery alias excluded by exact URL');
    check(!tvs_editorial_matches_publication(['id'=>'different','url'=>'https://example.com/other'],$saved),'Unrelated candidate remains visible');
    $retry=tvs_editorial_persist_publication($newsPath,$queuePath,'radar','q1',$record);
    check($retry['ok'] && $retry['existing'] && count(tvs_editorial_read_strict($newsPath))===2,'Double submit does not duplicate news');
    // Crash after news commit leaves origin in queue. Retry must only clean it.
    file_put_contents($queuePath,json_encode([$draft,$other]));
    $retry=tvs_editorial_persist_publication($newsPath,$queuePath,'radar','q1',$record);
    check($retry['ok'] && tvs_editorial_read_strict($queuePath)===[$other],'Interrupted cleanup recovers idempotently');
    $before=file_get_contents($newsPath);
    $stale=tvs_editorial_publication_metadata($other,'radar','q2',date(DATE_ATOM));
    $stale['origin_snapshot_sha']='old-review';
    check(!tvs_editorial_persist_publication($newsPath,$queuePath,'radar','q2',$stale)['ok'],'Stale review rejected');
    check(file_get_contents($newsPath)===$before,'Stale review preserves published dataset');
    file_put_contents($newsPath,'{broken');
    $beforeQueue=file_get_contents($queuePath);
    check(!tvs_editorial_persist_publication($newsPath,$queuePath,'radar','q2',$other)['ok'],'Corrupt news rejected');
    check(file_get_contents($newsPath)==='{broken' && file_get_contents($queuePath)===$beforeQueue,'Corrupt datasets never replaced or consumed');
    check(!tvs_editorial_atomic_write($dir.'/missing/file.json',[$other]),'Write failure reported');
    // Execute the real Radar publish handler without booting UI, cron or AI providers.
    loadProductionFunction(dirname(__DIR__).'/admin/radar-regional.php','tvs_publish_from_queue');
    function tvs_queue_read(){ global $queueFile; return tvs_editorial_read_strict($queueFile); }
    $newsFile=$newsPath; $queueFile=$queuePath;
    $candidate=$ready+['id'=>'actual-handler','ai_editor_processed'=>1,'source'=>'Official source','image'=>'','created_at'=>'2026-10-01T00:00:00Z','audit_extra'=>'retained'];
    file_put_contents($newsPath,json_encode([$legacy]));
    file_put_contents($queuePath,json_encode([$candidate,$other]));
    check(tvs_publish_from_queue('actual-handler',[]),'Real handler succeeds');
    $saved=tvs_editorial_read_strict($newsPath);
    check(count($saved)===2 && $saved[1]['radar_queue_id']==='actual-handler','Real handler stores origin link');
    check($saved[1]['source_published_at']===$candidate['source_published_at'],'Real handler retains source date');
    check($saved[1]['image_status']==='missing' && $saved[1]['audit_extra']==='retained','Real handler retains metadata and missing image status');
    check(tvs_editorial_read_strict($queuePath)===[$other],'Real handler removes only published candidate');
    file_put_contents($newsPath,'{broken');
    file_put_contents($queuePath,json_encode([$candidate,$other]));
    check(!tvs_publish_from_queue('actual-handler',[]),'Real handler reports failed publication');
    check(count(tvs_editorial_read_strict($queuePath))===2,'Real handler preserves queue on failure');
} finally {
    foreach(glob($dir.'/*') as $path) unlink($path);
    foreach(glob($dir.'/.*') as $path) if(is_file($path)) unlink($path);
    rmdir($dir);
}
echo "EDITORIAL_LIFECYCLE={$checks} PASS\n";
