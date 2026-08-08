<?php
require_once 'config.php';
$news=file_exists('data/noticias.json')?json_decode(file_get_contents('data/noticias.json'),true):[]; if(!is_array($news)) $news=[];
header('Content-Type: application/rss+xml; charset=UTF-8');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?><rss version="2.0"><channel><title>TV Sumaré</title><link><?=htmlspecialchars($site_url)?></link><description>Notícias de Sumaré e região</description><language>pt-BR</language><?php foreach(array_reverse($news) as $n): $link=rtrim($site_url,'/').'/noticia.php?id='.urlencode($n['id']??''); ?><item><title><?=htmlspecialchars($n['title']??'Notícia')?></title><link><?=htmlspecialchars($link)?></link><guid><?=htmlspecialchars($link)?></guid><description><?=htmlspecialchars($n['subtitle']??'')?></description><pubDate><?=date(DATE_RSS, strtotime($n['published_at']??$n['created_at']??'now'))?></pubDate></item><?php endforeach; ?></channel></rss>
