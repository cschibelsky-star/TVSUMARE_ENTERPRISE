<?php
// TV Sumaré Enterprise — Wrapper de compatibilidade.
// O cron canônico oficial do Monitor é sempre admin/cron_monitor.php.
// Este arquivo redireciona para a versão canônica e evita split-brain de regras.

require_once __DIR__ . '/admin/cron_monitor.php';
