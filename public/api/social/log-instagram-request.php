<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use TVSumare\Api\Auth;
use TVSumare\Api\JsonResponse;
use TVSumare\Automation\AutomationLog;
use TVSumare\Storage\JsonStore;

Auth::requireToken();
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$payload = is_array($input) ? $input : [];

$log = new AutomationLog(new JsonStore(__DIR__ . '/../../../data'));
$log->add('instagram', $payload, 'prepared');

JsonResponse::send(['ok' => true, 'message' => 'Solicitação de Instagram registrada.']);
