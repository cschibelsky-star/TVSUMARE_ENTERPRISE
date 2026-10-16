<?php
declare(strict_types=1);
// HML-only single-use handoff from the authenticated Cockpit. No password sharing.
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
if (!preg_match('/^tv-hml\\.vitrineiapro\\.com\\.br$/D', (string)($_SERVER['HTTP_HOST'] ?? ''))) {
    http_response_code(404); exit;
}
if (getenv('TVSUMARE_ENV') !== 'homologation') { http_response_code(404); exit; }
require_once __DIR__ . '/auth.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); exit; }
$token = (string)($_GET['token'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{64}$/D', $token)) { http_response_code(400); exit; }
$url = 'https://hml.vitrineiapro.com.br/cockpit/sso/consume?token='.rawurlencode($token);
$context = stream_context_create(['http' => [
    'timeout' => 8, 'follow_location' => 0, 'ignore_errors' => true,
    'header' => "Accept: application/json\r\n",
], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
$body = @file_get_contents($url, false, $context);
$statusLine = $http_response_header[0] ?? '';
if (!preg_match('/^HTTP\\/\\S+ 200(?: |$)/', $statusLine) || !is_string($body)) {
    http_response_code(403); exit('SSO indisponivel ou expirado.');
}
$data = json_decode($body, true);
if (!is_array($data)
    || ($data['target'] ?? '') !== 'tv-digital-enterprise'
    || ($data['role'] ?? '') !== 'admin'
    || !filter_var(($data['email'] ?? null), FILTER_VALIDATE_EMAIL)
    || abs(time() - (int)($data['issued_at'] ?? 0)) > 60) {
    http_response_code(403); exit('Identidade SSO invalida.');
}
session_regenerate_id(true);
$_SESSION['tvs_admin_logged'] = true;
$_SESSION['tvs_admin_user'] = 'cockpit:'.(string)$data['email'];
$_SESSION['tvs_sso_issued_at'] = time();
$_SESSION['tvs_login_attempts'] = [];
$_SESSION['tvs_csrf_token'] = bin2hex(random_bytes(32));
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('Location: monitor.php', true, 303);
exit;
