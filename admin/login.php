<?php
require_once __DIR__ . '/auth.php';

if (tvs_is_logged()) {
    header('Location: monitor.php');
    exit;
}

$loginError = (string) ($_GET['erro'] ?? '');
if ($loginError === 'csrf') {
    $erro = 'Sessao expirada. Recarregue a pagina e tente novamente.';
} elseif ($loginError === 'throttled') {
    $erro = 'Muitas tentativas invalidas. Aguarde alguns minutos e tente novamente.';
} elseif ($loginError !== '') {
    $erro = 'Usuario ou senha invalidos.';
} elseif (!isset($erro)) {
    $erro = '';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login Admin | TV Sumaré</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Arial,Helvetica,sans-serif;background:linear-gradient(135deg,#071438,#102a65);display:flex;align-items:center;justify-content:center;color:#0f172a}.card{width:min(390px,92vw);background:#fff;border-radius:22px;padding:34px;box-shadow:0 24px 80px rgba(0,0,0,.28)}.brand{text-align:center;margin-bottom:20px}.brand img{max-width:92px;max-height:64px;object-fit:contain}.brand h1{font-size:26px;margin:12px 0 5px;color:#1e2f55}.brand p{margin:0;color:#64748b;font-size:14px}.alert{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:12px;padding:11px 13px;margin:14px 0;font-size:14px}label{display:block;margin:15px 0 6px;font-weight:700;color:#334155}input{width:100%;padding:13px 14px;border:1px solid #d6deea;border-radius:12px;font-size:15px;outline:none}input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}button{width:100%;margin-top:18px;padding:14px;border:0;border-radius:13px;background:#1d4ed8;color:white;font-weight:800;font-size:15px;cursor:pointer}small{display:block;text-align:center;margin-top:16px;color:#64748b;line-height:1.4}.version{margin-top:10px;text-align:center;font-size:12px;color:#94a3b8}
</style>
</head>
<body>
<main class="card">
  <div class="brand">
    <img src="../assets/logo-tv-sumare.jpeg" alt="TV Sumaré" onerror="this.style.display='none'">
    <h1>Painel TV Sumaré</h1>
    <p>TVSUMARE_ENTERPRISE_1.0_MASTER</p>
  </div>
  <?php if ($erro): ?><div class="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="post" action="auth.php" autocomplete="off"><?=tvs_csrf_field()?>
    <label for="user">Usuário</label>
    <input id="user" name="user" type="text" required autofocus>
    <label for="pass">Senha</label>
    <input id="pass" name="pass" type="password" required>
    <button type="submit">Entrar no painel</button>
  </form>
  <small>Acesso restrito à redação, administração e operação comercial.</small>
  <div class="version">Build 1.0.1 — Login corrigido</div>
</main>
</body>
</html>
