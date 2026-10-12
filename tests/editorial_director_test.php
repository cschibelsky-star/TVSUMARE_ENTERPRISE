<?php
require_once __DIR__.'/../includes/editorial_director.php';
function check($condition,$case) { if(!$condition) { fwrite(STDERR,"FAIL: ".$case."\n"); exit(1); } }
foreach([['Sumaré','cristian'],['Sumare / SP','cristian'],['SUMARÉ (SP)','cristian'],['Campinas','virtual'],['Hortolândia - SP','virtual'],['Brasil','virtual'],['','review'],['Região','review'],['RMC','review'],['Sumaré e Campinas','review'],['Sumaré, Campinas','review'],['Sumaré e região','review']] as [$city,$expected]) check(tvs_director_classify_city($city)===$expected,$city);
$cfg=['heygen_cristian_avatar_id'=>'avatar_ok','heygen_cristian_voice_id'=>'voice_ok'];
$d=tvs_director_decide(['city'=>'Sumaré','script'=>'Matéria local'],$cfg);
check($d['ok'] && $d['presenter_name']==='Cristian Schibelsky','Cristian');
$p=tvs_director_apply_payload(['avatar_id'=>'outro','voice_id'=>'outra'],$d,$cfg);
check($p['avatar_id']==='avatar_ok' && $p['voice_id']==='voice_ok','prioridade da identidade');
$v=tvs_director_decide(['city'=>'Campinas','script'=>'Pauta de Campinas'],$cfg);
$p=tvs_director_apply_payload(['avatar_id'=>'antigo','voice_id'=>'antiga'],$v,$cfg);
check($v['ok'] && !isset($p['avatar_id']) && !isset($p['voice_id']),'virtual sem IDs globais');
check(!tvs_director_decide(['city'=>'Campinas','script'=>'Eu sou Cristian Schibelsky.'],$cfg)['ok'],'roteiro legado');
check(!tvs_director_decide(['city'=>'Sumaré','script'=>'texto'],[])['ok'],'IDs obrigatorios');
check(!tvs_director_decide(['city'=>'Região','script'=>'texto'],$cfg)['ok'],'revisao geografica');
echo "OK: 18 verificacoes editoriais\n";
