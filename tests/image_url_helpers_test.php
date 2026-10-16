<?php
require_once dirname(__DIR__).'/includes/image_url_helpers.php';
$original='https://bucket.portaldacidade.com/sumare.portaldacidade.com/img/news/2026-09/foto.jpg';
$wrapped='https://image.portaldacidade.com/unsafe/1200x800/'.$original;
if(tvs_normalize_source_image_url($wrapped)!==$original)throw new RuntimeException('Original não recuperado');
foreach([$original,'uploads/foto.jpg','https://outro.com/foto.jpg','https://image.portaldacidade.com/unsafe/1200x800/https://evil.com/foto.jpg','https://image.portaldacidade.com.evil.com/unsafe/1200x800/'.$original] as $url){
 if(tvs_normalize_source_image_url($url)!==$url)throw new RuntimeException('URL não suportada alterada');
}
if(tvs_normalize_source_image_url(tvs_normalize_source_image_url($wrapped))!==$original)throw new RuntimeException('Não idempotente');
echo "IMAGE_URL_HELPERS_TEST=PASS\n";
