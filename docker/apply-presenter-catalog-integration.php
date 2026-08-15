<?php
function pci_patch(&$code,$old,$new,$label){
  $n=substr_count($code,$old);
  if($n===0 && strpos($code,$new)!==false){ echo "{$label}: hardening já aplicado.\n"; return; }
  if($n!==1){fwrite(STDERR,"{$label}: trecho esperado count={$n}; abortando\n");exit(2);}
  $code=str_replace($old,$new,$code);
  echo "{$label}: aplicado.\n";
}

$reporter='/var/www/html/admin/reporter-ia.php'; $r=file_get_contents($reporter); if($r===false) exit(1);
pci_patch($r,"require_once dirname(__DIR__).'/includes/heygen_helper.php';","require_once dirname(__DIR__).'/includes/heygen_helper.php';\nrequire_once dirname(__DIR__).'/includes/presenter_catalog.php';",'Reporter include catalog');
$old="function rpia_human_voice_id(){ return 'cbdcad7a79e44262b4f4dad0a1b1fba9'; }";
$new="function rpia_human_voice_id(){ return 'cbdcad7a79e44262b4f4dad0a1b1fba9'; }\nfunction rpia_catalog_profile_for_job(\$job){ \$avatar=trim((string)(\$job['heygen_avatar_id']??'')); \$profile=\$avatar!==''?tvs_presenter_find_by_avatar(\$avatar):null; if(!\$profile){ \$theme=trim((string)(\$job['boletim_theme']??(\$job['presenter_theme']??''))); if(\$theme!=='') \$profile=tvs_presenter_get(\$theme); } return \$profile; }";
pci_patch($r,$old,$new,'Reporter catalog helper');
$old="  foreach(['avatar_id'=>'heygen_avatar_id','voice_id'=>'heygen_voice_id','style_id'=>'heygen_style_id','brand_kit_id'=>'heygen_brand_kit_id'] as \$api=>\$local){ \$jobValue=trim((string)(\$job[\$local]??'')); \$cfgValue=trim((string)(\$cfg[\$local]??'')); \$v=\$jobValue!==''?\$jobValue:\$cfgValue; if(\$api==='voice_id') \$v=rpia_human_voice_id(); if(\$v!=='') \$payload[\$api]=\$v; }";
$new="  \$catalogProfile=rpia_catalog_profile_for_job(\$job); if(\$catalogProfile){ \$policy=tvs_presenter_allowed(\$catalogProfile); if(!\$policy['allowed']) throw new RuntimeException(\$policy['reason']); }\n  foreach(['avatar_id'=>'heygen_avatar_id','voice_id'=>'heygen_voice_id','style_id'=>'heygen_style_id','brand_kit_id'=>'heygen_brand_kit_id'] as \$api=>\$local){ \$jobValue=trim((string)(\$job[\$local]??'')); \$cfgValue=trim((string)(\$cfg[\$local]??'')); \$v=\$jobValue!==''?\$jobValue:\$cfgValue; if(\$catalogProfile){ if(\$api==='avatar_id') \$v=trim((string)(\$catalogProfile['avatar_id']??\$v)); elseif(\$api==='voice_id') \$v=trim((string)(\$catalogProfile['voice_id']??\$v)); elseif(\$api==='style_id' && trim((string)(\$catalogProfile['style_id']??''))!=='') \$v=trim((string)\$catalogProfile['style_id']); } if(\$api==='voice_id' && \$v==='') \$v=rpia_human_voice_id(); if(\$v!=='') \$payload[\$api]=\$v; }";
pci_patch($r,$old,$new,'Reporter enforce catalog');
if(file_put_contents($reporter,$r,LOCK_EX)===false) exit(3);

$boletim='/var/www/html/admin/boletim-ia.php'; $b=file_get_contents($boletim); if($b===false) exit(4);
$needle="require_once dirname(__DIR__).'/config.php';";
pci_patch($b,$needle,$needle."\nrequire_once dirname(__DIR__).'/includes/presenter_catalog.php';",'Boletim include catalog');

if(strpos($b,'tvs_presenter_for_theme($theme)')===false){
  $compact=<<<'OLD'
function bia_presenter_result($theme,$cfg,$reason,$mode){ $labels=bia_presenter_labels(); if(!isset($labels[$theme])) $theme='noticias'; $p=$cfg['presenters'][$theme]??[]; return ['theme'=>$theme,'theme_label'=>$labels[$theme],'name'=>$p['name']??'Apresentador do Boletim','tone'=>$p['tone']??'informal, próximo, ágil e confiável','avatar_id'=>$p['avatar_id']??'','voice_id'=>$p['voice_id']??'','style_id'=>$p['style_id']??'','selection_reason'=>$reason,'selection_mode'=>$mode]; }
OLD;
  $replacement=<<<'NEW'
function bia_presenter_result($theme,$cfg,$reason,$mode){
  $labels=bia_presenter_labels();
  if(!isset($labels[$theme])) $theme='noticias';
  $p=$cfg['presenters'][$theme]??[];
  $pick=tvs_presenter_for_theme($theme);
  if(is_array($pick['profile']??null)){
    $cp=$pick['profile'];
    $p=array_merge($p,[
      'name'=>$cp['name']??($p['name']??''),
      'tone'=>$cp['tone']??($p['tone']??''),
      'avatar_id'=>$cp['avatar_id']??($p['avatar_id']??''),
      'voice_id'=>$cp['voice_id']??($p['voice_id']??''),
      'style_id'=>$cp['style_id']??($p['style_id']??'')
    ]);
    if(!empty($pick['fallback'])) $reason.=' Perfil editorial substituído por apresentador homologado disponível.';
    $mode.='|catalog_'.(string)($cp['status']??'nao_testado').'|'.(string)($pick['policy']['environment']??'');
  }
  return ['theme'=>$theme,'theme_label'=>$labels[$theme],'name'=>$p['name']??'Apresentador do Boletim','tone'=>$p['tone']??'informal, próximo, ágil e confiável','avatar_id'=>$p['avatar_id']??'','voice_id'=>$p['voice_id']??'','style_id'=>$p['style_id']??'','selection_reason'=>$reason,'selection_mode'=>$mode];
}
NEW;
  $count=substr_count($b,$compact);
  if($count!==1){ fwrite(STDERR,"Boletim catalog editorial result: função compacta count={$count}; abortando\n"); exit(2); }
  $b=str_replace($compact,$replacement,$b);
  echo "Boletim catalog editorial result: aplicado à função compacta.\n";
}else{
  echo "Boletim catalog editorial result: hardening já aplicado.\n";
}

if(file_put_contents($boletim,$b,LOCK_EX)===false) exit(8);
echo "PRESENTER_CATALOG_INTEGRATION_APPLIED=SIM\n";
