<?php
// Run with php -n: real cURL is forbidden, so no request can reach HeyGen.
if (PHP_SAPI !== 'cli' || extension_loaded('curl')) {
    fwrite(STDERR, "Run this test with php -n (no real HTTP transport).\n");
    exit(1);
}
foreach (['CURLOPT_RETURNTRANSFER', 'CURLOPT_CUSTOMREQUEST', 'CURLOPT_HTTPHEADER',
          'CURLOPT_POSTFIELDS', 'CURLOPT_TIMEOUT', 'CURLOPT_FOLLOWLOCATION',
          'CURLINFO_HTTP_CODE'] as $i => $name) define($name, $i + 1);

$calls = [];
$guardCalls = [];
$blocked = false;
$fixture = ['http'=>200, 'body'=>'{"data":{"video_id":"v_test"}}', 'error'=>''];
if (!function_exists('curl_init')) {
    function curl_init($url) {
        return (object)['url'=>$url, 'options'=>[]];
    }
    function curl_setopt_array($ch, $options) { $ch->options = $options; return true; }
    function curl_exec($ch) {
        global $calls, $fixture;
        $calls[] = ['url'=>$ch->url, 'options'=>$ch->options];
        return $fixture['body'];
    }
    function curl_error($ch) { global $fixture; return $fixture['error']; }
    function curl_getinfo($ch, $option) { global $fixture; return $fixture['http']; }
    function curl_close($ch) {}
}
function tvs_outbound_curl_options($url, $timeout) {
    global $blocked, $guardCalls;
    $guardCalls[] = [$url, $timeout];
    return $blocked ? null : [CURLOPT_TIMEOUT=>$timeout, CURLOPT_FOLLOWLOCATION=>false];
}
// Isolate configuration from real data files; keep the production config loader.
function tvs_heygen_config_paths() { return []; }
putenv('HEYGEN_API_KEY=mock-key-never-sent');
putenv('HEYGEN_AVATAR_ID=cfg-avatar');
putenv('HEYGEN_VOICE_ID=cfg-voice');
putenv('HEYGEN_ORIENTATION=landscape');
require dirname(__DIR__).'/includes/heygen_helper.php';

$assertions = 0;
function expect($condition, $label) {
    global $assertions;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $assertions++;
}
function respond($body, $http = 200) {
    global $fixture;
    $fixture = ['body'=>is_array($body) ? json_encode($body) : $body, 'http'=>$http, 'error'=>''];
}
function request() { global $calls; return $calls[count($calls)-1]; }
$job = ['script'=>'<b>Notícia regional.</b> Notícia regional. Veja https://example.com',
        'heygen_avatar_id'=>'job-avatar', 'heygen_voice_id'=>'job-voice'];
$r = tvp_send_heygen($job);
$q = request();
$p = json_decode($q['options'][CURLOPT_POSTFIELDS], true);
expect($r['ok'] && $r['video_id'] === 'v_test' && $r['session_id'] === '', 'creation video_id and existing job contract');
expect($q['url'] === 'https://api.heygen.com/v3/videos', 'create v3 endpoint');
expect($q['options'][CURLOPT_CUSTOMREQUEST] === 'POST', 'create method');
expect($p['type'] === 'avatar' && $p['avatar_id'] === 'cfg-avatar' && $p['voice_id'] === 'cfg-voice', 'fixed configured presenter retained despite conflicting job IDs');
expect($p['script'] === 'Notícia regional. Veja', 'script cleaning retained');
expect($p['aspect_ratio'] === '16:9' && $p['resolution'] === '720p' && $p['output_format'] === 'mp4', 'legacy output dimensions mapped to v3');
expect($p['engine'] === ['type'=>'avatar_iii'] && $p['voice_settings']['speed'] == 1, 'renderer and voice speed retained');
expect(!isset($p['video_inputs'], $p['dimension']), 'no v2 payload fields');
expect(in_array('X-Api-Key: mock-key-never-sent', $q['options'][CURLOPT_HTTPHEADER], true), 'existing authentication header retained');
expect($q['options'][CURLOPT_TIMEOUT] === 75 && $q['options'][CURLOPT_FOLLOWLOCATION] === false, 'outbound guard options retained');
expect(end($guardCalls) === [$q['url'],75], 'creation passes through outbound guard');
putenv('HEYGEN_ORIENTATION=portrait');
respond(['data'=>['id'=>'v_resource_id']]);
expect(tvp_send_heygen($job)['video_id'] === 'v_resource_id', 'resource id fallback');
expect(json_decode(request()['options'][CURLOPT_POSTFIELDS],true)['aspect_ratio'] === '9:16', 'portrait mapping');
respond(['video_id'=>'v_flat']);
expect(tvp_send_heygen($job)['video_id'] === 'v_flat', 'flat response compatibility');
respond(['data'=>[]]);
expect(!tvp_send_heygen($job)['ok'], 'accepted creation without identifier fails');

respond(['data'=>['id'=>'old-id', 'status'=>'completed', 'video_url'=>'https://files.heygen.ai/final.mp4',
    'captioned_video_url'=>'https://files.heygen.ai/captioned.mp4', 'thumbnail_url'=>'https://files.heygen.ai/thumb.jpg',
    'video_url_caption'=>'must-not-use-v1-field', 'progress'=>100]]);
$r = tvp_check_heygen(['heygen_video_id'=>'old/id ?']);
$q = request();
expect($q['url'] === 'https://api.heygen.com/v3/videos/old%2Fid%20%3F', 'legacy stored ID safely encoded into v3 path');
expect($q['options'][CURLOPT_CUSTOMREQUEST] === 'GET' && !isset($q['options'][CURLOPT_POSTFIELDS]), 'status GET without payload');
expect($r['video_id'] === 'old/id ?' && $r['video_status'] === 'completed' && $r['progress'] === 100, 'status job contract retained');
expect($r['video_url'] === 'https://files.heygen.ai/final.mp4' && $r['captioned_video_url'] === 'https://files.heygen.ai/captioned.mp4' && $r['thumb'] === 'https://files.heygen.ai/thumb.jpg', 'v3 delivery URLs');
expect(end($guardCalls) === [$q['url'],40], 'status passes through outbound guard');
foreach (['waiting','pending','processing'] as $status) {
    respond(['data'=>['status'=>$status,'video_url'=>'premature','captioned_video_url'=>'premature']]);
    $r=tvp_check_heygen(['heygen_video_id'=>'v_test']);
    expect($r['ok'] && $r['video_status'] === $status && $r['video_url'] === '' && $r['captioned_video_url'] === '', 'incomplete URLs withheld: '.$status);
}
respond(['data'=>['status'=>'failed','failure_message'=>'Render failed','failure_code'=>'render_error']]);
expect(tvp_check_heygen(['heygen_video_id'=>'v_test'])['failure_message'] === 'Render failed', 'v3 failure_message');
respond(['data'=>['status'=>'failed','failure_code'=>'render_error']]);
expect(tvp_check_heygen(['heygen_video_id'=>'v_test'])['failure_message'] === 'render_error', 'v3 failure_code fallback');
respond(['data'=>['status'=>'failed']]);
expect(tvp_check_heygen(['heygen_video_id'=>'v_test'])['failure_message'] !== '', 'failed generation always has a reason');
foreach ([400,401,404,429,500] as $http) {
    respond(['error'=>['message'=>'simulated']],$http);
    $r=tvp_check_heygen(['heygen_video_id'=>'v_test']);
    expect(!$r['ok'] && $r['http'] === $http, 'status HTTP error propagated: '.$http);
    expect(!tvp_send_heygen($job)['ok'], 'creation HTTP error propagated: '.$http);
}
respond('not-json');
expect(!tvp_send_heygen($job)['ok'], 'invalid JSON rejected');
respond(false);
expect(!tvp_check_heygen(['heygen_video_id'=>'v_test'])['ok'], 'transport failure propagated');
respond(str_repeat('x',2097153));
expect(!tvp_send_heygen($job)['ok'], 'response size limit retained');
$before=count($calls);
$blocked=true;
expect(!tvp_send_heygen($job)['ok'] && !tvp_check_heygen(['heygen_video_id'=>'v_test'])['ok'] && count($calls)===$before, 'outbound rejection prevents both requests');
$blocked=false;
expect(!tvp_check_heygen([])['ok'] && !tvp_send_heygen(['script'=>''])['ok'] && count($calls)===$before, 'missing ID and empty script never send');
putenv('HEYGEN_API_KEY');
expect(!tvp_send_heygen($job)['ok'] && count($calls)===$before, 'missing key never sends');
foreach ($calls as $call) expect(!preg_match('~/v[12]/~',$call['url']), 'no legacy HTTP endpoint used');
echo "HEYGEN_V3_HTTP=PASS ($assertions assertions, all HTTP simulated)\n";
