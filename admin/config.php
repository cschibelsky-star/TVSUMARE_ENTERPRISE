<?php
declare(strict_types=1);

$admin_user = (string) (getenv('TVSUMARE_ADMIN_USER') ?: '');
$admin_pass = '';
$admin_pass_hash = (string) (getenv('TVSUMARE_ADMIN_PASS_HASH') ?: '');
$site_nome = (string) (getenv('TVSUMARE_SITE_NAME') ?: 'TV Sumare');
$site_url = rtrim((string) (getenv('TVSUMARE_SITE_URL') ?: 'http://127.0.0.1:8088'), '/');

$gemini_api_key = (string) (getenv('GEMINI_API_KEY') ?: '');
$gemini_model = (string) (getenv('GEMINI_MODEL') ?: 'gemini-2.0-flash');
$gemini_fallback_models = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) (getenv('GEMINI_FALLBACK_MODELS') ?: ''))
)));
