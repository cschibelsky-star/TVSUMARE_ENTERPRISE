<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/voice_policy.php';
$p=tvs_voice_policy_catalog();
function verify_voice_policy($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}}
verify_voice_policy($p['locale']==='pt-BR','locale pt-BR');
verify_voice_policy(count($p['approved_narrators'])===3,'three approved voices');
verify_voice_policy(tvs_voice_policy_allows_generic_voice('c8ac31e97555494fb8502599e6bc5461'),'Adriano');
verify_voice_policy(tvs_voice_policy_allows_generic_voice('14be84ff0ad848d79bd867a79a870f4a'),'Yuri');
verify_voice_policy(tvs_voice_policy_allows_generic_voice('2799eccbd72d48e0af3202a147d51146'),'Aida');
verify_voice_policy(!tvs_voice_policy_allows_generic_voice('cbdcad7a79e44262b4f4dad0a1b1fba9'),'presenter voice not automatically selected for generic narration');
verify_voice_policy($p['protect_explicit_presenter_voice']===true,'presenter protection');
verify_voice_policy($p['auto_publish']===false,'manual publishing');
verify_voice_policy(str_contains(tvs_voice_policy_prompt(),'português brasileiro'),'prompt language');
verify_voice_policy(str_contains(tvs_voice_policy_prompt(),'nunca substituí-los automaticamente'),'presenter cannot be replaced');
verify_voice_policy(str_contains(tvs_voice_policy_prompt(),'Vitrine iá pró'),'brand pronunciation');
echo "voice_policy_test: OK\n";
