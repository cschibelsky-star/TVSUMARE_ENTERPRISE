<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TVSumare\Home\HomeComposer;
use TVSumare\Media\ImageEngine;

$news = [
    [
        'title' => 'TV Sumaré Enterprise 3.0 entra em desenvolvimento',
        'city' => 'Sumaré',
        'category' => 'Cidade',
        'summary' => 'Nova geração da plataforma editorial será a base oficial da TV Digital Enterprise.',
        'url' => '#',
        'quality_score' => 95,
        'published_at' => date(DATE_ATOM),
        'category_image' => '/assets/img/tvsumare-default-news.jpg',
    ],
];

$videos = [
    [
        'title' => 'TV Sumaré Play 2.0',
        'city' => 'Sumaré',
        'category' => 'TV Play',
        'duration' => '02:30',
        'thumbnail' => '/assets/img/tvsumare-play-default.jpg',
    ],
];

$home = (new HomeComposer(new ImageEngine()))->compose($news, $videos);
$hero = $home['hero'];
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TV Sumaré Enterprise 3.0</title>
    <link rel="stylesheet" href="/assets/css/enterprise.css">
</head>
<body>
<header class="topbar">
    <div class="brand">
        <strong>TV Sumaré Enterprise</strong>
        <span>Powered by Vitrine IA Pro</span>
    </div>
    <nav>
        <a href="/">Início</a>
        <a href="#play">TV Play</a>
        <a href="#aovivo">Ao Vivo</a>
        <a href="/admin/">Admin</a>
    </nav>
</header>

<main class="page">
    <?php if ($hero): ?>
    <section class="hero" style="background-image:url('<?= htmlspecialchars((string)$hero['image']) ?>')">
        <div class="hero__overlay">
            <span class="badge"><?= htmlspecialchars((string)$hero['category']) ?> • <?= htmlspecialchars((string)$hero['city']) ?></span>
            <h1><?= htmlspecialchars((string)$hero['title']) ?></h1>
            <p><?= htmlspecialchars((string)$hero['summary']) ?></p>
        </div>
    </section>
    <?php endif; ?>

    <section class="stamp">
        <span>Enterprise News Platform</span>
        <strong>Vitrine IA Pro</strong>
    </section>

    <section class="grid-section">
        <h2>Destaques editoriais</h2>
        <div class="editorial-grid">
            <?php foreach ($home['editorials'] as $category => $items): ?>
                <article class="topic-card">
                    <h3><?= htmlspecialchars((string)$category) ?></h3>
                    <?php foreach (array_slice($items, 0, 3) as $item): ?>
                        <a href="<?= htmlspecialchars((string)$item['url']) ?>"><?= htmlspecialchars((string)$item['title']) ?></a>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="play" class="grid-section dark">
        <h2>TV Sumaré Play</h2>
        <div class="video-grid">
            <?php foreach ($home['videos'] as $video): ?>
                <article class="video-card">
                    <img src="<?= htmlspecialchars((string)$video['thumbnail']) ?>" alt="">
                    <div>
                        <span><?= htmlspecialchars((string)$video['category']) ?> • <?= htmlspecialchars((string)$video['duration']) ?></span>
                        <h3><?= htmlspecialchars((string)$video['title']) ?></h3>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
</body>
</html>
