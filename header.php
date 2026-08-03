<?php $active = $active ?? 'home'; ?>
<div class="top-strip"><div class="container top-strip__inner"><div>Hoje • Sumaré e Região</div><div class="quick-links"><a href="noticias.php">Últimas notícias</a><a class="subscribe-link" href="anuncie.php">Assine / Anuncie</a></div></div></div>
<?php
$uhFile = __DIR__ . '/data/ultimahora.json';
$uhItems = file_exists($uhFile) ? json_decode(file_get_contents($uhFile), true) : [];
if (!is_array($uhItems) || !$uhItems) {
  $uhItems = [
    ['title'=>'Monitor regional da TV Sumaré acompanha fontes oficiais e portais da região'],
    ['title'=>'Guia Comercial recebe novos anunciantes'],
    ['title'=>'Transmissão ao vivo disponível no portal'],
    ['title'=>'Notícias de Sumaré, Paulínia, Nova Odessa, Hortolândia, Campinas e Americana']
  ];
}
?>
<div class="urgent-bar"><strong>ÚLTIMA HORA</strong><div class="ticker"><div class="ticker__track"><?php $tickerItems=array_slice($uhItems,0,8); foreach(array_merge($tickerItems,$tickerItems) as $uh): ?><span><?=htmlspecialchars($uh['title']??'Atualização regional')?></span><?php endforeach; ?></div></div></div>
<header class="site-header"><div class="container header-main"><a class="brand" href="index.php"><img src="assets/logo-tv-sumare.jpeg" alt="TV Sumaré"><div><strong>TV Sumaré</strong><span>Notícia regional com credibilidade</span></div></a><button class="mobile-toggle" type="button" aria-label="Abrir menu" data-menu-toggle>☰</button><nav class="main-nav" data-main-nav><a class="<?= $active=='home'?'active':'' ?>" href="index.php">Início</a><a class="<?= $active=='noticias'?'active':'' ?>" href="noticias.php">Notícias</a><a class="<?= $active=='guia'?'active':'' ?>" href="guia.php">Guia Comercial</a><a class="<?= $active=='videos'?'active':'' ?>" href="videos.php">Vídeos</a><a class="<?= $active=='colunas'?'active':'' ?>" href="colunas.php">Colunas</a><a class="live-link <?= $active=='aovivo'?'active':'' ?>" href="aovivo.php">● Ao Vivo</a><a class="<?= $active=='anuncie'?'active':'' ?>" href="anuncie.php">Anuncie Aqui</a></nav></div></header>
