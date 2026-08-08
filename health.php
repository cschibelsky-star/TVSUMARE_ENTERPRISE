<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$dataPath = __DIR__ . '/data';
$healthy = is_dir($dataPath) && is_readable($dataPath);

http_response_code($healthy ? 200 : 503);
echo json_encode([
    'ok' => $healthy,
    'service' => 'tvsumare',
    'environment' => getenv('TVSUMARE_ENV') ?: 'unknown',
    'data_readable' => $healthy,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
