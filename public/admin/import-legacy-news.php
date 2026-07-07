<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use TVSumare\Core\Config;
use TVSumare\Editorial\EditorialPipeline;
use TVSumare\Editorial\NewsQualityGate;
use TVSumare\Editorial\ProcessedRegistry;
use TVSumare\Legacy\LegacyImporter;
use TVSumare\Legacy\LegacyNewsMapper;
use TVSumare\Media\ImageEngine;
use TVSumare\Storage\JsonStore;

$store = new JsonStore(__DIR__ . '/../../data');
$importer = new LegacyImporter(
    $store,
    new LegacyNewsMapper(),
    new EditorialPipeline(
        new NewsQualityGate(new Config()),
        new ImageEngine(),
        new ProcessedRegistry($store)
    )
);

$result = $importer->import($_GET['file'] ?? 'noticias.json');
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Importação Legada | TV Sumaré Enterprise</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<main class="workspace" style="margin-left:0;width:100%">
  <header class="workspace__header">
    <div>
      <span class="eyebrow">Enterprise News Engine</span>
      <h1>Importação de Notícias Legadas</h1>
    </div>
    <div class="powered">Powered by <strong>Vitrine IA Pro</strong></div>
  </header>
  <section class="kpi-grid">
    <article class="kpi-card"><span>Total</span><strong><?= (int)$result['total'] ?></strong></article>
    <article class="kpi-card"><span>Aprovadas</span><strong><?= (int)$result['approved'] ?></strong></article>
    <article class="kpi-card"><span>Rejeitadas</span><strong><?= (int)$result['rejected'] ?></strong></article>
  </section>
  <section class="panel" style="margin-top:24px">
    <h2>Resultado</h2>
    <p>Arquivo gerado: <code>data/enterprise_news_imported.json</code></p>
    <p>Use este arquivo como fonte inicial da Home 3.0.</p>
  </section>
</main>
</body>
</html>
