<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use TVSumare\Api\Auth;
use TVSumare\Api\JsonResponse;
use TVSumare\Storage\JsonStore;

Auth::requireToken();
$store = new JsonStore(__DIR__ . '/../../../data');

JsonResponse::send([
    'ok' => true,
    'app' => 'TVSUMARE_ENTERPRISE',
    'version' => '3.0',
    'powered_by' => 'Vitrine IA Pro',
    'time' => date(DATE_ATOM),
    'checks' => [
        'php' => PHP_VERSION,
        'data_writable' => is_writable(__DIR__ . '/../../../data'),
        'news_imported' => count($store->read('enterprise_news_imported.json', [])),
        'automation_logs' => count($store->read('automation_logs.json', [])),
    ],
]);
