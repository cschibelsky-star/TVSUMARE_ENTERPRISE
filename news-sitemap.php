<?php
require_once 'config.php';
$news=file_exists('data/noticias.json')?json_decode(file_get_contents('data/noticias.json'),true):[]; if(!is_array($news)) $news=[];
header('Content-Type: application/xml; charset=UTF-8');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"><?php foreach(array_reverse($news) as $n): ?><url><loc><?=htmlspecialchars(rtrim($site_url,'/').'/noticia.php?id='.urlencode($n['id']??''))?></loc><news:news><news:publication><news:name>TV Sumaré</news:name><news:language>pt</news:language></news:publication><news:publication_date><?=htmlspecialchars(date('c', strtotime($n['published_at']??$n['created_at']??'now')))?></news:publication_date><news:title><?=htmlspecialchars($n['title']??'Notícia')?></news:title></news:news></url><?php endforeach; ?></urlset>
