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
assert_schedule(str_starts_with(tvs_ad_display_status(['status'=>'publicado','start_at'=>'2099-10-16T11:01']),'Agendado'),'future approved campaign is scheduled');
assert_schedule(str_starts_with(tvs_ad_display_status(['status'=>'publicado','end_at'=>'2020-01-01T00:00']),'Encerrado'),'past campaign ends');
assert_schedule(str_starts_with(tvs_ad_display_status(['status'=>'publicado']),'Mídia indisponível'),'missing media never marked playing');
echo "PREMIUM_AD_SCHEDULE_TEST=PASS\n";
