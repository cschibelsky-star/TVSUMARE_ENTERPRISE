<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use TVSumare\Core\Config;
use TVSumare\Editorial\EditorialPipeline;
use TVSumare\Editorial\NewsQualityGate;
use TVSumare\Editorial\ProcessedRegistry;
use TVSumare\Media\ImageEngine;
use TVSumare\Storage\JsonStore;

$store = new JsonStore(__DIR__ . '/../../data');
$pipeline = new EditorialPipeline(
    new NewsQualityGate(new Config()),
    new ImageEngine(),
    new ProcessedRegistry($store)
);

$sample = [
    'title' => 'Prefeitura de Sumaré anuncia nova etapa de serviços públicos na região central',
    'city' => 'Sumaré',
    'category' => 'Cidade',
    'url' => 'https://tvsumare.com.br/exemplo',
    'published_at' => date(DATE_ATOM),
    'og_image' => '',
    'category_image' => '/assets/img/tvsumare-default-news.jpg',
];

$result = $pipeline->process($sample);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
