<?php
declare(strict_types=1);
require_once __DIR__.'/includes/elections_2026.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$uf=is_string($_GET['uf']??null)?strtolower($_GET['uf']):'sp';
if (!isset(tvs_election_states()[$uf])) { http_response_code(400); echo '{"error":"UF inválida."}'; exit; }
$presidentOnly=($_GET['visao']??'')==='presidente';
try { echo json_encode(tvs_election_snapshot($uf,$presidentOnly),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
catch (Throwable $error) { http_response_code(503); echo '{"error":"Resultados temporariamente indisponíveis."}'; }
