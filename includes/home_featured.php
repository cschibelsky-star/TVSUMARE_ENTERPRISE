<?php
require_once __DIR__.'/home_feature_ads.php';
$homeFeature = tvs_json('data/home_destaques.json');
$homeFeature = is_array($homeFeature) ? $homeFeature : [];
$requestedFeatureMode = $homeFeature['mode'] ?? 'eleicoes';
$featureMode = in_array($requestedFeatureMode, ['eleicoes','noticia','personalizado'], true)
    ? $requestedFeatureMode : 'eleicoes';
$featuredAds = tvs_premium_ads();
$manualTitle = trim((string)($homeFeature['title']??''));
$manualDescription = trim((string)($homeFeature['description']??''));
$manualLink = tvs_premium_link($homeFeature['url']??'') ?: 'noticias.php';
?>
<section class="tvs-premium-feature" aria-label="Destaques e publicidade">
  <article class="tvs-premium-editorial" aria-label="Destaque editorial">
    <?php if ($featureMode === 'eleicoes'): ?>
      <span class="tvs-premium-kicker">ELEIÇÕES 2026</span>
      <h2>Acompanhe a apuração oficial</h2>
      <p>Presidente — Brasil • Governador, Senador e deputados por estado — Fonte: TSE</p>
      <div id="president-home" role="status" class="tvs-premium-election-results">Consultando a apuração nacional…</div>
      <a class="tvs-premium-cta" href="/eleicoes-2026">Ver resultados e horário de atualização</a>
    <?php elseif ($featureMode === 'personalizado' && $manualTitle !== ''): ?>
      <span class="tvs-premium-kicker">DESTAQUE ESPECIAL</span>
      <h2><?=tvs_h($manualTitle)?></h2>
      <p><?=tvs_h($manualDescription)?></p>
      <a class="tvs-premium-cta" href="<?=tvs_h($manualLink)?>">Saiba mais</a>
    <?php elseif (!empty($hero)): ?>
      <span class="tvs-premium-kicker">MATÉRIA EM DESTAQUE</span>
      <h2><?=tvs_h(tvs_title($hero['title']??'Notícias de Sumaré',105))?></h2>
      <p><?=tvs_h(tvs_excerpt_clean($hero['subtitle']??$hero['summary']??'',180))?></p>
      <a class="tvs-premium-cta" href="<?=tvs_h(tvs_news_url($hero))?>">Ler matéria</a>
    <?php else: ?>
      <span class="tvs-premium-kicker">DESTAQUES</span>
      <h2>Notícias de Sumaré e região</h2>
      <p>Acompanhe a cobertura regional da TV Sumaré.</p>
      <a class="tvs-premium-cta" href="noticias.php">Ver notícias</a>
    <?php endif; ?>
  </article>
  <aside class="tvs-premium-ad" aria-label="Espaço publicitário">
    <div class="tvs-premium-ad-heading">
      <span>PUBLICIDADE</span>
      <button id="tvs-ad-toggle" class="tvs-ad-toggle" type="button" hidden>Pausar alternância</button>
    </div>
    <div id="tvs-premium-ad-media" class="tvs-premium-ad-media" aria-label="Anúncio em destaque">
      <div class="tvs-premium-ad-empty"><strong>Sua marca em destaque</strong><span>Banners e vídeos comerciais de até 50 segundos</span></div>
    </div>
    <div class="tvs-premium-ad-footer">
      <strong id="tvs-premium-ad-title">Anuncie na TV Sumaré</strong>
      <a id="tvs-premium-ad-link" href="anuncie.php">Conheça os planos</a>
    </div>
  </aside>
</section>
<script type="application/json" id="tvs-premium-ad-data"><?=json_encode($featuredAds, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
<script defer src="assets/home-featured.js?v=20261016-50s"></script>
<link rel="stylesheet" href="assets/home-featured.css?v=20261016-1">
