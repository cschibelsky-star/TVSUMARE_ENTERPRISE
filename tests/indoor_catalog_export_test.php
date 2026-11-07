<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/indoor_catalog_export.php';
$base = [
 'source_id'=>'news_1', 'type'=>'news', 'title'=>'Boletim piloto',
 'destination'=>'indoor_only', 'editorial_status'=>'approved_for_indoor',
 'indoor_approval'=>['authorized'=>true],
 'rights'=>['indoor_allowed'=>true,'valid_until'=>'2099-01-01T00:00:00Z'],
 'media_path'=>'uploads/noticias/news_1.jpg',
 'email'=>'should-not-export'
];
$ad=$base;
$ad['source_id']='ad_1'; $ad['type']='ad'; $ad['media_path']='uploads/anuncios/ad_1.mp4';
$out=IndoorCatalogExport::preview([$base,$base,$ad]);
if(count($out)!==2 || $out[0]['import_state']!=='pending_indoor_review' || $out[1]['slot']!=='lateral') exit(1);
if(str_contains(json_encode($out),'should-not-export')) exit(2);
foreach([
 ['destination'=>'tvsumare_only'],
 ['editorial_status'=>'pending'],
 ['indoor_approval'=>['authorized'=>false]],
 ['rights'=>['indoor_allowed'=>false]],
 ['rights'=>['indoor_allowed'=>true,'valid_until'=>'2020-01-01T00:00:00Z']],
 ['media_path'=>'../private/file.mp4'],
] as $change){
 if(IndoorCatalogExport::preview([array_replace($base,$change)])!==[]) exit(3);
}
echo "OK: Indoor private catalog filtering\n";
