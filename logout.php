<?php
require_once __DIR__ . '/auth.php';
$_SESSION = array();
session_destroy();
tvs_redirect('login.php');
?>
