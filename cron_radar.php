<?php
// TV Sumaré Enterprise — Wrapper de compatibilidade.
// O cron canônico oficial do Radar é sempre admin/cron_radar.php.
// Este arquivo redireciona para a versão canônica e evita split-brain de regras.

if(!defined('TVS_RADAR_CRON')) define('TVS_RADAR_CRON', true);
require_once __DIR__ . '/admin/cron_radar.php';
