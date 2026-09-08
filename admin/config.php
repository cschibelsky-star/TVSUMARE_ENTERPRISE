<?php
declare(strict_types=1);

$admin_user = (string) (getenv('TV_DIGITAL_ADMIN_USER') ?: getenv('TVSUMARE_ADMIN_USER') ?: '');
$admin_pass = '';
$admin_pass_hash = (string) (getenv('TV_DIGITAL_ADMIN_PASS_HASH') ?: getenv('TVSUMARE_ADMIN_PASS_HASH') ?: '');
$site_nome = (string) (getenv('TV_DIGITAL_SITE_NAME') ?: getenv('TVSUMARE_SITE_NAME') ?: 'TV Digital');
$site_url = rtrim((string) (getenv('TV_DIGITAL_SITE_URL') ?: getenv('TVSUMARE_SITE_URL') ?: 'http://127.0.0.1:8088'), '/');
$site_city = (string) (getenv('TV_DIGITAL_CITY') ?: 'Região');
$site_brand_hashtag = (string) (getenv('TV_DIGITAL_BRAND_HASHTAG') ?: '#TVDigital');

// Core / Centro de IA is the preferred shared AI provider for the scalable product.
$centro_ia_url = rtrim((string) (getenv('CENTRO_IA_URL') ?: 'http://vitrine_core_web_hml'), '/');
$centro_ia_internal_token = (string) (getenv('CENTRO_IA_INTERNAL_TOKEN') ?: '');
$centro_ia_project_id = (string) (getenv('CENTRO_IA_PROJECT_ID') ?: 'tvsumare-enterprise');
$centro_ia_capability = (string) (getenv('CENTRO_IA_CAPABILITY') ?: 'editorial_generation');

// Direct Gemini remains only as a temporary compatibility fallback during decoupling.
$gemini_api_key = (string) (getenv('GEMINI_API_KEY') ?: '');
$gemini_model = (string) (getenv('GEMINI_MODEL') ?: 'gemini-2.0-flash');
$gemini_fallback_models = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) (getenv('GEMINI_FALLBACK_MODELS') ?: ''))
)));

// Video generation is optional in Enterprise and disabled by default.
// The dedicated TV Sumare HeyGen account/configuration is not inherited here.
$enterprise_video_ai_enabled = filter_var((string) (getenv('TV_DIGITAL_VIDEO_AI_ENABLED') ?: '0'), FILTER_VALIDATE_BOOL);
