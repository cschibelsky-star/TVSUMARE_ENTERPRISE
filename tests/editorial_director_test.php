<?php
require_once __DIR__.'/../includes/editorial_director.php';
function check($condition,$case) { if(!$condition) { fwrite(STDERR,"FAIL: ".$case."\n"); exit(1); } }
foreach([['Sumaré','cristian'],['Sumare / SP','cristian'],['SUMARÉ (SP)','cristian'],['Campinas','virtual'],['Campinas, SP','virtual'],['Hortolândia - SP','virtual'],['Brasil','virtual'],['','review'],['Região','review'],['RMC','review'],['Campinas / RMC','review'],['Campinas, Valinhos','review'],['Sumaré e Campinas','review'],['Sumaré, Campinas','review'],['Sumaré e região','review']] as [$city,$expected]) check(tvs_director_classify_city($city)===$expected,$city);
$official=tvs_director_official_ids();
$globalCfg=['heygen_avatar_id'=>'global-avatar','heygen_voice_id'=>'global-voice'];
$d=tvs_director_decide(['city'=>'Sumaré','script'=>'Matéria local'],$globalCfg);
check($d['ok'] && $d['avatar_id']==='ecc586fbd3e94cd4a07347c098364fc8' && $d['voice_id']==='ca1ad88a041a40eea2f1c8583f970ce4','fallback oficial de Cristian');
$overrideCfg=$globalCfg+['heygen_cristian_avatar_id'=>'override-avatar','heygen_cristian_voice_id'=>'override-private-voice'];
$overrideCfg['heygen_brand_kit_id']='brand-configurado';
$override=tvs_director_decide(['city'=>'Sumaré','script'=>'Matéria local'],$overrideCfg);
check($override['ok'] && $override['avatar_id']==='override-avatar' && $override['voice_id']==='override-private-voice','override explícito de Cristian');
$overridePayload=tvs_director_apply_payload($globalCfg,$override,$overrideCfg);
check($overridePayload['avatar_id']==='override-avatar' && $overridePayload['voice_id']==='override-private-voice','payload respeita override local');
check(($overridePayload['brand_kit_id']??'')==='brand-configurado','BrandKit configurado preservado');
$cfg=['heygen_cristian_avatar_id'=>'avatar_ok','heygen_cristian_voice_id'=>'voice_ok'];
$d=tvs_director_decide(['city'=>'Sumaré','script'=>'Matéria local'],$cfg);
check($d['ok'] && $d['presenter_name']==='Cristian Schibelsky','Cristian');
$p=tvs_director_apply_payload(['avatar_id'=>'outro','voice_id'=>'outra'],$d,$cfg);
check($p['avatar_id']==='avatar_ok' && $p['voice_id']==='voice_ok','prioridade da identidade');
$v=tvs_director_decide(['city'=>'Campinas','script'=>'Pauta de Campinas'],$globalCfg+['heygen_cristian_avatar_id'=>'override-avatar','heygen_cristian_voice_id'=>'override-voice']);
$p=tvs_director_apply_payload($globalCfg+['brand_kit_id'=>'brand-configurado'],$v,$globalCfg);
check($v['ok'] && !isset($p['avatar_id']) && !isset($p['voice_id']),'virtual sem IDs globais');
check(($v['presenter_name']??'')==='' && strpos(tvs_director_instructions($v),'sem nome')!==false,'noticia externa sem identidade nominal');
check(!tvs_director_decide(['city'=>'Campinas','script'=>'Eu sou Cristian Schibelsky.'],$cfg)['ok'],'roteiro legado');
check(tvs_director_decide(['city'=>'Sumaré','script'=>'texto'],[])['ok'],'fallback oficial sem configuração');
check(!tvs_director_decide(['city'=>'Região','script'=>'texto'],$cfg)['ok'],'revisao geografica');
check($official['avatar_id']==='ecc586fbd3e94cd4a07347c098364fc8' && $official['voice_id']==='ca1ad88a041a40eea2f1c8583f970ce4','IDs oficiais');
echo "EDITORIAL_DIRECTOR_TEST=PASS\n";
