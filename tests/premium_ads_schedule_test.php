<?php
require_once dirname(__DIR__).'/includes/home_feature_ads.php';
require_once dirname(__DIR__).'/includes/premium_ads_schedule.php';
function assert_schedule($ok, $label) {
    if (!$ok) { fwrite(STDERR,"FAIL: ".$label."\n"); exit(1); }
}
assert_schedule(tvs_ad_schedule_validate('','')==='','Open-ended schedule permitted');
assert_schedule(tvs_ad_schedule_validate('2026-10-16T11:01','')==='','future start without end');
assert_schedule(tvs_ad_schedule_validate('2026-10-16T11:01','2026-10-17T11:01')==='','valid range');
assert_schedule(tvs_ad_schedule_validate('2026-10-17T11:01','2026-10-16T11:01')!=='','reject reversed range');
assert_schedule(tvs_ad_schedule_validate('2026-02-30T09:00','')!=='','reject invalid day');
assert_schedule(tvs_ad_schedule_validate('2026-10-16T25:00','')!=='','reject invalid time');
assert_schedule(tvs_ad_schedule_validate('2026-10-16 11:01','')!=='','reject non datetime-local input');
assert_schedule(tvs_ad_display_status(['status'=>'aprovado'])==='aprovado','preserve approval status');
assert_schedule(str_starts_with(tvs_ad_display_status(['status'=>'publicado','start_at'=>'2099-10-16T11:01']),'Mídia indisponível'),'schedule cannot hide missing media');
// The fixture is isolated and removed after testing; no production campaign is created.
$directory=dirname(__DIR__).'/uploads/anuncios';
if(!is_dir($directory) && !mkdir($directory,0755,true) && !is_dir($directory)) exit(1);
$name='premium_test_'.bin2hex(random_bytes(6)).'.png';
$path=$directory.'/'.$name;
$image=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9iWRpW0AAAAASUVORK5CYII=',true);
if($image===false || file_put_contents($path,$image)===false) exit(1);
try {
    $ad=['status'=>'publicado','banner'=>'uploads/anuncios/'.$name];
    assert_schedule(str_starts_with(tvs_ad_display_status($ad+['start_at'=>'2099-10-16T11:01']),'Agendado'),'future approved campaign is scheduled');
    assert_schedule(str_starts_with(tvs_ad_display_status($ad+['end_at'=>'2020-01-01T00:00']),'Encerrado'),'past campaign ends');
    assert_schedule(str_starts_with(tvs_ad_display_status($ad),'Em exibição'),'valid current campaign is active');
} finally { unlink($path); }
assert_schedule(str_starts_with(tvs_ad_display_status(['status'=>'publicado']),'Mídia indisponível'),'missing media never marked playing');
echo "PREMIUM_AD_SCHEDULE_TEST=PASS\n";
