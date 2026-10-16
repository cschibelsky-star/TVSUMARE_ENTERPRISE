<?php
require_once dirname(__DIR__).'/includes/premium_ads_policy.php';
function premium_duration_assert(bool $passed, string $test): void {
    if (!$passed) { fwrite(STDERR,"FAIL: {$test}\n"); exit(1); }
}
premium_duration_assert(TVS_PREMIUM_MAX_VIDEO_SECONDS === 50.0, 'maximum video length is 50 seconds');
premium_duration_assert(TVS_PREMIUM_BANNER_SECONDS === 10, 'banner rotation remains 10 seconds');
premium_duration_assert(tvs_premium_video_duration_valid(8), 'old 8-second ads are valid');
premium_duration_assert(tvs_premium_video_duration_valid(10), 'old 10-second ads are valid');
premium_duration_assert(tvs_premium_video_duration_valid(49.9), 'long 49.9s ad is valid');
premium_duration_assert(tvs_premium_video_duration_valid(50), 'exactly 50s is valid');
premium_duration_assert(!tvs_premium_video_duration_valid(50.01), 'over 50s is rejected');
premium_duration_assert(!tvs_premium_video_duration_valid(0), 'empty duration rejected');
premium_duration_assert(!tvs_premium_video_duration_valid(-1), 'negative duration rejected');
premium_duration_assert(!tvs_premium_video_duration_valid('oops'), 'nonnumeric duration rejected');
premium_duration_assert(!tvs_premium_video_duration_valid(INF), 'infinite duration rejected');
echo "PREMIUM_AD_DURATION_TEST=PASS\n";
