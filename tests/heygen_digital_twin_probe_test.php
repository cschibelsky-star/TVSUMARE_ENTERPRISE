<?php
require_once dirname(__DIR__).'/includes/heygen_digital_twin_probe.php';
$id='c68420874a454f3bbf7200a48add0288';
$cases=[
 [function($path)use($id){if($path!=='/v3/avatars/looks') throw new Exception('wrong path');return ['ok'=>true,'data'=>['data'=>['looks'=>[['id'=>$id]]]]];},true],
 [function($path){return ['ok'=>true,'data'=>['data'=>['looks'=>[['id'=>'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']]]]];},false]
];
foreach($cases as $case){$r=tvs_heygen_digital_twin_probe($case[0],$id);if(!$r['ok']||$r['avatar_visible']!==$case[1])exit(1);}
$error=tvs_heygen_digital_twin_probe(function($p){return ['ok'=>false,'http'=>403,'data'=>['secret'=>'must-not-echo']];},$id);
if($error['ok']||isset($error['data']))exit(2);
$page=tvs_heygen_digital_twin_probe(function($p){return ['ok'=>true,'data'=>['data'=>['looks'=>[]],'has_more'=>true,'next_token'=>'opaque']];},$id);
if($page['ok']||$page['reason']!=='catalog_pagination_incomplete')exit(3);
$invalid=tvs_heygen_digital_twin_probe(function($p){throw new Exception('should not call');},'bad');
if($invalid['ok']||$invalid['reason']!=='invalid_avatar_id')exit(4);
$unknown=tvs_heygen_digital_twin_probe(function($p){return ['ok'=>true,'data'=>['data'=>['unexpected'=>[]]]];},$id);
if($unknown['ok']||$unknown['reason']!=='catalog_schema_unrecognized')exit(5);
$empty=tvs_heygen_digital_twin_probe(function($p){return ['ok'=>true,'data'=>['data'=>['looks'=>[]]]];},$id);
if(!$empty['ok']||$empty['avatar_visible']!==false)exit(6);
$malformed=tvs_heygen_digital_twin_probe(function($p){return ['ok'=>true,'data'=>['data'=>['looks'=>[['unexpected'=>'value']]]]];},$id);
if($malformed['ok']||$malformed['reason']!=='catalog_item_schema_unrecognized')exit(7);
echo "Digital Twin probe mock tests passed\n";
