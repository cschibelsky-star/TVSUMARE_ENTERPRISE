<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use TVSumare\Api\Auth;
use TVSumare\Api\JsonResponse;
use TVSumare\Automation\AutomationLog;
use TVSumare\Storage\JsonStore;
use TVSumare\Video\VideoScriptRepository;

Auth::requireToken();
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$payload = is_array($input) ? ($input['payload'] ?? $input) : [];
if (is_string($payload)) {
    $decoded = json_decode($payload, true);
    $payload = is_array($decoded) ? $decoded : ['raw' => $payload];
}

$store = new JsonStore(__DIR__ . '/../../../../data');
(new VideoScriptRepository($store))->saveOptions($payload);
(new AutomationLog($store))->add('heygen_scripts', $payload, 'awaiting_editor_choice');

JsonResponse::send(['ok' => true, 'message' => 'Opções de roteiro salvas para escolha do editor.']);
