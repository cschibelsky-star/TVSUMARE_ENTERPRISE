<?php
// Regression test: extracts the actual approve_both handler, mocks providers, never publishes.
declare(strict_types=1);
$source = file_get_contents(__DIR__ . '/../admin/revisao-videos.php');
if ($source === false) { fwrite(STDERR, "Cannot read review source\n"); exit(1); }
$start = strpos($source, "if(\$action==='approve_both'){");
$end = strpos($source, "if(\$action==='reject')", $start === false ? 0 : $start);
if ($start === false || $end === false) { fwrite(STDERR, "Handlers not found\n"); exit(1); }
$handler = substr($source, $start, $end - $start);
class ReviewRedirect extends RuntimeException { public array $params; public function __construct(array $p) { parent::__construct('redirect'); $this->params=$p; } }
function rv_redirect(array $p): void { throw new ReviewRedirect($p); }
function tvp_publish_video(array $job): array { $GLOBALS['events'][]='tvplay'; return $GLOBALS['tvplayResult']; }
function tvs_youtube_upload_branded(array $job): array { $GLOBALS['events'][]='youtube'; return $GLOBALS['youtubeResult']; }
function tvp_save_video_jobs(array $jobs): void { $GLOBALS['events'][]='save'; $GLOBALS['savedJobs']=$jobs; }
$scenarios = [
 ['tvplay'=>['ok'=>false,'error'=>'mock tvplay'], 'youtube'=>['ok'=>false], 'events'=>['tvplay'], 'status'=>'revisao_video'],
 ['tvplay'=>['ok'=>true], 'youtube'=>['ok'=>false,'error'=>'mock youtube'], 'events'=>['tvplay','save','youtube'], 'status'=>'publicado'],
 ['tvplay'=>['ok'=>true], 'youtube'=>['ok'=>true,'youtube_video_id'=>'mock-id','url'=>'https://example.test/mock'], 'events'=>['tvplay','save','youtube','save'], 'status'=>'publicado'],
];
foreach ($scenarios as $i=>$scenario) {
 $GLOBALS['events']=[]; $GLOBALS['savedJobs']=null;
 $GLOBALS['tvplayResult']=$scenario['tvplay']; $GLOBALS['youtubeResult']=$scenario['youtube'];
 $action='approve_both'; $jobId='mock'; $idx=0;
 $job=['id'=>'mock','status'=>'revisao_video']; $jobs=[$job];
 try { eval($handler); fwrite(STDERR,"Case $i did not redirect\n"); exit(1); }
 catch (ReviewRedirect $redirect) {}
 if ($GLOBALS['events']!==$scenario['events']) { fwrite(STDERR,"Case $i events: ".json_encode($GLOBALS['events'])."\n"); exit(1); }
 $actual=$GLOBALS['savedJobs'][0]['status']??'revisao_video';
 if ($actual!==$scenario['status']) { fwrite(STDERR,"Case $i status $actual\n"); exit(1); }
 if ($i===2 && ($GLOBALS['savedJobs'][0]['youtube_video_id']??'')!=='mock-id') { fwrite(STDERR,"YouTube id not saved\n"); exit(1); }
 echo "PASS case $i\n";
}
