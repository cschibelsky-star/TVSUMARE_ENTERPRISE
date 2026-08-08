<?php
require_once 'config.php';
header('Content-Type: application/xml; charset=UTF-8');
$pages=['index.php','noticias.php','guia.php','videos.php','aovivo.php','anuncie.php'];
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";
foreach($pages as $p){ echo '<url><loc>'.htmlspecialchars(rtrim($site_url,'/').'/'.$p).'</loc></url>'; }
$news=file_exists('data/noticias.json')?json_decode(file_get_contents('data/noticias.json'),true):[]; if(is_array($news)) foreach($news as $n){ echo '<url><loc>'.htmlspecialchars(rtrim($site_url,'/').'/noticia.php?id='.urlencode($n['id']??'')).'</loc></url>'; }
echo '</urlset>';
