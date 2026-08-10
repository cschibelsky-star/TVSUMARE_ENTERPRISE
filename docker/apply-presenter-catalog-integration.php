<?php
function pci_patch(&$code,$old,$new,$label){$n=substr_count($code,$old);if($n!==1){fwrite(STDERR,"{$label}: trecho esperado count={$n}; abortando\n");exit(2);} $code=str_replace($old,$new,$code);}

$reporter='/var/www/html/admin/reporter-ia.php'; $r=file_get_contents($reporter); if($r===false) exit(1);
pci_patch($r,"require_once dirname(__DIR__).'/includes/heygen_helper.php';","require_once dirname(__DIR__).'/includes/heygen_helper.php';\nrequire_once dirname(__DIR__).'/includes/presenter_catalog.php';",'Reporter include catalog');
$old="function rpia_human_voice_id(){ return 'cbdcad7a79e44262b4f4dad0a1b1fba9'; }";
$new="function rpia_human_voice_id(){ return 'cbdcad7a79e44262b4f4dad0a1b1fba9'; }\nfunction rpia_catalog_profile_for_job(\$job){ \$avatar=trim((string)(\$job['heygen_avatar_id']??'')); \$profile=\$avatar!==''?tvs_presenter_find_by_avatar(\$avatar):null; if(!\$profile){ \$theme=trim((string)(\$job['presenter_theme']??'')); if(\$theme!=='') \$profile=tvs_presenter_get(\$theme); } return \$profile; }";
pci_patch($r,$old,$new,'Reporter catalog helper');
$old="  foreach(['avatar_id'=>'heygen_avatar_id','voice_id'=>'heygen_voice_id','style_id'=>'heygen_style_id','brand_kit_id'=>'heygen_brand_kit_id'] as \$api=>\$local){ \$jobValue=trim((string)(\$job[\$local]??'')); \$cfgValue=trim((string)(\$cfg[\$local]??'')); \$v=\$jobValue!==''?\$jobValue:\$cfgValue; if(\$api==='voice_id') \$v=rpia_human_voice_id(); if(\$v!=='') \$payload[\$api]=\$v; }";
$new="  \$catalogProfile=rpia_catalog_profile_for_job(\$job); if(\$catalogProfile){ \$policy=tvs_presenter_allowed(\$catalogProfile); if(!\$policy['allowed']) throw new RuntimeException(\$policy['reason']); }\n  foreach(['avatar_id'=>'heygen_avatar_id','voice_id'=>'heygen_voice_id','style_id'=>'heygen_style_id','brand_kit_id'=>'heygen_brand_kit_id'] as \$api=>\$local){ \$jobValue=trim((string)(\$job[\$local]??'')); \$cfgValue=trim((string)(\$cfg[\$local]??'')); \$v=\$jobValue!==''?\$jobValue:\$cfgValue; if(\$catalogProfile){ if(\$api==='avatar_id') \$v=trim((string)(\$catalogProfile['avatar_id']??\$v)); elseif(\$api==='voice_id') \$v=trim((string)(\$catalogProfile['voice_id']??\$v)); elseif(\$api==='style_id' && trim((string)(\$catalogProfile['style_id']??''))!=='') \$v=trim((string)\$catalogProfile['style_id']); } if(\$api==='voice_id' && \$v==='') \$v=rpia_human_voice_id(); if(\$v!=='') \$payload[\$api]=\$v; }";
pci_patch($r,$old,$new,'Reporter enforce catalog');
if(file_put_contents($reporter,$r)===false) exit(3);

$boletim='/var/www/html/admin/boletim-ia.php'; $b=file_get_contents($boletim); if($b===false) exit(4);
$needle="require_once dirname(__DIR__).'/config.php';";
if(strpos($b,$needle)!==false) pci_patch($b,$needle,$needle."\nrequire_once dirname(__DIR__).'/includes/presenter_catalog.php';",'Boletim include catalog'); else { $pos=strpos($b,"require_login();"); if($pos===false) exit(5); $pos+=strlen("require_login();"); $b=substr($b,0,$pos)."\nrequire_once dirname(__DIR__).'/includes/presenter_catalog.php';".substr($b,$pos); }
$old="function bia_pick_presenter(\$theme,\$cfg){ return \$cfg['presenters'][\$theme]??\$cfg['presenters']['giro']; }";
if(strpos($b,$old)!==false){
  $new="function bia_pick_presenter(\$theme,\$cfg){ \$pick=tvs_presenter_for_theme(\$theme); if(is_array(\$pick['profile']??null)){ \$p=\$pick['profile']; return ['name'=>(string)(\$p['name']??''),'tone'=>(string)(\$p['tone']??''),'avatar_id'=>(string)(\$p['avatar_id']??''),'voice_id'=>(string)(\$p['voice_id']??''),'style_id'=>(string)(\$p['style_id']??''),'catalog_status'=>(string)(\$p['status']??'nao_testado'),'catalog_environment'=>(string)(\$pick['policy']['environment']??''),'catalog_fallback'=>!empty(\$pick['fallback'])]; } return \$cfg['presenters'][\$theme]??\$cfg['presenters']['giro']; }";
  pci_patch($b,$old,$new,'Boletim catalog selection');
} else {
  $marker="function bia_presenter_labels()"; $p=strpos($b,$marker); if($p===false){fwrite(STDERR,"Boletim presenter selector marker ausente\n");exit(6);} $fn="function bia_catalog_presenter(\$theme,\$legacy){ \$pick=tvs_presenter_for_theme(\$theme); if(is_array(\$pick['profile']??null)){ \$p=\$pick['profile']; return array_merge(\$legacy,['name'=>(string)(\$p['name']??''),'tone'=>(string)(\$p['tone']??''),'avatar_id'=>(string)(\$p['avatar_id']??''),'voice_id'=>(string)(\$p['voice_id']??''),'style_id'=>(string)(\$p['style_id']??''),'catalog_status'=>(string)(\$p['status']??'nao_testado')]); } return \$legacy; }\n"; $b=substr($b,0,$p).$fn.substr($b,$p);
  $b=str_replace("\$presenter=\$cfg['presenters'][\$theme]??\$cfg['presenters']['giro'];","\$presenter=bia_catalog_presenter(\$theme,\$cfg['presenters'][\$theme]??\$cfg['presenters']['giro']);",$b,$count); if($count<1){fwrite(STDERR,"Boletim presenter assignment não localizado\n");exit(7);}
}
if(file_put_contents($boletim,$b)===false) exit(8);
echo "PRESENTER_CATALOG_INTEGRATION_APPLIED=SIM\n";
