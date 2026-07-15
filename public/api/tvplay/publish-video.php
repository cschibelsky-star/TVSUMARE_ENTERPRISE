<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use TVSumare\Api\Auth;
use TVSumare\Api\JsonResponse;
use TVSumare\Automation\AutomationLog;
use TVSumare\Storage\JsonStore;
use TVSumare\TvPlay\TvPlayRepository;

Auth::requireToken();
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$payload = is_array($input) ? ($input['payload'] ?? $input) : [];
if (is_string($payload)) {
    $decoded = json_decode($payload, true);
    $payload = is_array($decoded) ? $decoded : ['raw' => $payload];
}

$store = new JsonStore(__DIR__ . '/../../../data');
(new TvPlayRepository($store))->publish($payload);
(new AutomationLog($store))->add('tvplay_publish', $payload, 'published');

JsonResponse::send(['ok' => true, 'message' => 'Vídeo publicado no TV Play.']);
