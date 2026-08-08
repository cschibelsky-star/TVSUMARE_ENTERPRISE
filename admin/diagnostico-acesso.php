<?php
header('Content-Type: text/html; charset=utf-8');
$checks = [
  'admin_dir' => is_dir(__DIR__),
  'login_php' => file_exists(__DIR__ . '/login.php'),
  'index_php' => file_exists(__DIR__ . '/index.php'),
  'auth_php' => file_exists(__DIR__ . '/auth.php'),
  'monitor_php' => file_exists(__DIR__ . '/monitor.php'),
  'config_root' => file_exists(dirname(__DIR__) . '/config.php'),
  'config_admin' => file_exists(__DIR__ . '/config.php'),
  'dir_readable' => is_readable(__DIR__),
  'login_readable' => is_readable(__DIR__ . '/login.php'),
];
?><!doctype html><html><head><meta charset="utf-8"><title>Diagnóstico Admin TV Sumaré</title><style>body{font-family:Arial;margin:30px}.ok{color:#087a2b}.bad{color:#b00020}code{background:#f2f2f2;padding:2px 5px}</style></head><body><h1>Diagnóstico Admin TV Sumaré</h1><p>Se esta página abriu, a pasta <code>/admin</code> não está mais bloqueada por 403.</p><ul><?php foreach($checks as $k=>$v): ?><li><b><?=htmlspecialchars($k)?></b>: <span class="<?=$v?'ok':'bad'?>"><?=$v?'OK':'FALHA'?></span></li><?php endforeach; ?></ul><p><a href="login.php">Ir para o login</a></p></body></html>
