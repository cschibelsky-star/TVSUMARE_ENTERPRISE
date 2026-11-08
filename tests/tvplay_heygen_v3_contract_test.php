<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/heygen_helper.php';
function assert_true($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$source = file_get_contents(__DIR__.'/../includes/heygen_helper.php');
assert_true(!str_contains($source, "'/v2/video/generate'"), 'Legacy v2 create endpoint must not be in active helper');
assert_true(!str_contains($source, "'/v1/video_status.get"), 'Legacy v1 polling endpoint must not be in active helper');
assert_true(str_contains($source, "'/v3/video-agents'"), 'Video Agent v3 create endpoint missing');
assert_true(str_contains($source, "'/v3/videos/'"), 'Video Agent v3 result endpoint missing');

$created = tvp_heygen_v3_created(['data'=>['session_id'=>'s123','video_id'=>null,'status'=>'processing']]);
assert_true($created['ok'] && $created['session_id']==='s123' && $created['video_id']==='', 'Session-first request incorrectly rejected');
$invalid = tvp_heygen_v3_created(['data'=>['status'=>'processing']]);
assert_true(!$invalid['ok'], 'Missing both identifiers must fail closed');

$pending = tvp_heygen_v3_status_data(
    ['data'=>['status'=>'completed','video_id'=>'v123','progress'=>100]],
    ['data'=>['status'=>'processing','video_url'=>'https://example.invalid/premature.mp4']],
    'v123'
);
assert_true($pending['video_id']==='v123', 'Session video ID not retained');
assert_true($pending['video_url']==='', 'Premature video URL exposed before completed video');

$ready = tvp_heygen_v3_status_data(
    ['data'=>['status'=>'completed','video_id'=>'v123']],
    ['data'=>['status'=>'completed','video_url'=>'https://example.invalid/video.mp4','thumbnail_url'=>'https://example.invalid/cover.jpg']],
    'v123'
);
assert_true($ready['video_url']==='https://example.invalid/video.mp4', 'Completed video URL missing');
assert_true($ready['thumb']==='https://example.invalid/cover.jpg', 'Completed thumbnail missing');

$failed = tvp_heygen_v3_status_data(['data'=>['status'=>'failed','failure_message'=>'Provider error']],null);
assert_true($failed['failure_message']==='Provider error', 'Session failure not propagated');

echo "TVPLAY_HEYGEN_V3_CONTRACT_TEST=PASS\n";
