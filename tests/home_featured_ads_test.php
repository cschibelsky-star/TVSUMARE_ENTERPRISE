<?php
require_once dirname(__DIR__).'/includes/home_feature_ads.php';
function check($condition,$name){if(!$condition){fwrite(STDERR,"FAIL: ".$name."\n");exit(1);}}
check(tvs_premium_link('javascript:alert(1)')==='','reject javascript URL');
check(tvs_premium_link('//danger.example')==='','reject protocol-relative URL');
check(tvs_premium_link('https://example.org/')==='https://example.org/','allow https URL');
check(tvs_premium_link('/eleicoes-2026')==='/eleicoes-2026','allow local URL');
check(tvs_premium_local_media('../config.php','image')==='','reject path traversal');
check(tvs_premium_local_media('uploads/anuncios/a.mp4','image')==='','reject mismatched media');
check(tvs_premium_local_media('uploads/anuncios/a.svg','image')==='','reject executable image');
check(tvs_premium_local_media('uploads/anuncios/a.mp4','video')==='','nonexistent media');
echo "OK: premium home ads basic security assertions\n";
