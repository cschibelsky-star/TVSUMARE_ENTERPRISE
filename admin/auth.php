<?php
if (session_status() === PHP_SESSION_NONE) {
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config.php';
}
if (!file_exists($configPath)) {
    http_response_code(500);
    exit('Configuration unavailable.');
}
require_once $configPath;

function tvs_redirect($url) {
    header('Location: ' . $url);
    exit;
}

function tvs_is_logged() {
    return !empty($_SESSION['tvs_admin_logged']);
}

function require_login() {
    if (!tvs_is_logged()) {
        tvs_redirect('login.php');
    }
}

function tvs_csrf_token() {
    if (empty($_SESSION['tvs_csrf_token']) || !is_string($_SESSION['tvs_csrf_token'])) {
        $_SESSION['tvs_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['tvs_csrf_token'];
}

function tvs_csrf_field() {
    return '<input type="hidden" name="_csrf" value="' .
        htmlspecialchars(tvs_csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function tvs_csrf_is_valid() {
    $expected = (string) ($_SESSION['tvs_csrf_token'] ?? '');
    $given = (string) ($_POST['_csrf'] ?? '');
    return $expected !== '' && $given !== '' && hash_equals($expected, $given);
}

function tvs_csrf_reject() {
    http_response_code(419);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Invalid or expired request token.');
}

function tvs_verify_csrf() {
    if (!tvs_csrf_is_valid()) {
        tvs_csrf_reject();
    }
}

function tvs_login_error_redirect($reason) {
    if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'auth.php') {
        header('Location: login.php?erro=' . rawurlencode((string) $reason));
        exit;
    }
}

function tvs_admin_password_ok($password) {
    global $admin_pass_hash, $admin_pass;
    $password = (string) $password;
    if (!empty($admin_pass_hash) && password_verify($password, (string) $admin_pass_hash)) {
        return true;
    }
    if (!empty($admin_pass) && hash_equals((string) $admin_pass, $password)) {
        return true;
    }
    return false;
}

function tvs_login_throttled() {
    $now = time();
    $_SESSION['tvs_login_attempts'] = $_SESSION['tvs_login_attempts'] ?? [];
    $_SESSION['tvs_login_attempts'] = array_values(array_filter(
        $_SESSION['tvs_login_attempts'],
        function ($attempt) use ($now) {
            return ($now - (int) $attempt) < 900;
        }
    ));
    return count($_SESSION['tvs_login_attempts']) >= 8;
}

function tvs_register_failed_login() {
    $_SESSION['tvs_login_attempts'] = $_SESSION['tvs_login_attempts'] ?? [];
    $_SESSION['tvs_login_attempts'][] = time();
}

tvs_csrf_token();

$erro = '';
$isPost = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
$isLoginPost = $isPost && isset($_POST['user'], $_POST['pass']);

if ($isLoginPost) {
    if (!tvs_csrf_is_valid()) {
        $erro = 'Sessao expirada. Recarregue a pagina e tente novamente.';
        tvs_login_error_redirect('csrf');
    } elseif (tvs_login_throttled()) {
        $erro = 'Muitas tentativas invalidas. Aguarde alguns minutos e tente novamente.';
        tvs_login_error_redirect('throttled');
    } else {
        $user = trim((string) $_POST['user']);
        $pass = (string) $_POST['pass'];
        if ($admin_user !== '' && hash_equals((string) $admin_user, $user) && tvs_admin_password_ok($pass)) {
            session_regenerate_id(true);
            $_SESSION['tvs_admin_logged'] = true;
            $_SESSION['tvs_admin_user'] = $user;
            $_SESSION['tvs_login_attempts'] = [];
            $_SESSION['tvs_csrf_token'] = bin2hex(random_bytes(32));
            tvs_redirect('monitor.php');
        }
        tvs_register_failed_login();
        $erro = 'Usuario ou senha invalidos.';
        tvs_login_error_redirect('credentials');
    }
} elseif ($isPost && tvs_is_logged() && !tvs_csrf_is_valid()) {
    tvs_csrf_reject();
}
?>
