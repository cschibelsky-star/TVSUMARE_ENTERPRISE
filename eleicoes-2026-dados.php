<?php
declare(strict_types=1);
require_once __DIR__.'/includes/elections_2026.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try { echo json_encode(tvs_election_snapshot(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
catch (Throwable $error) { http_response_code(503); echo '{"error":"Resultados temporariamente indisponíveis."}'; }
