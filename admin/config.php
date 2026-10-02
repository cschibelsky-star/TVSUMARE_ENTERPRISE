<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$admin_user = (string) (getenv('TVSUMARE_ADMIN_USER') ?: '');
$admin_pass = '';
$admin_pass_hash = (string) (getenv('TVSUMARE_ADMIN_PASS_HASH') ?: '');
$site_nome = (string) (getenv('TVSUMARE_SITE_NAME') ?: 'TV Sumare');
$site_url = rtrim((string) (getenv('TVSUMARE_SITE_URL') ?: 'http://127.0.0.1:8088'), '/');

$openai_api_key = (string) (getenv('OPENAI_API_KEY') ?: '');
$openai_model = (string) (getenv('OPENAI_MODEL') ?: 'gpt-5-mini');

$gemini_api_key = (string) (getenv('GEMINI_API_KEY') ?: '');
$gemini_model = (string) (getenv('GEMINI_MODEL') ?: 'gemini-2.0-flash');
$gemini_fallback_models = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) (getenv('GEMINI_FALLBACK_MODELS') ?: ''))
)));

if (!function_exists('tvs_editorial_retention_days')) {
    function tvs_editorial_retention_days(array $n): int
    {
        $parts = [
            (string)($n['category'] ?? ''),
            (string)($n['title'] ?? ''),
            (string)($n['subtitle'] ?? ''),
            (string)($n['summary'] ?? ''),
            (string)($n['body'] ?? ''),
        ];
        $txt = mb_strtolower(trim(preg_replace('/\s+/u', ' ', implode(' ', $parts))), 'UTF-8');

        if (preg_match('~\b(frente\s+fria|chuvas?|temporais?|alertas?|interdi[cç][aã](?:o|ões)|tr[aâ]nsito|plant[aã]o|interrup[cç][aã](?:o|ões)|falta\s+d[ea]\s+[aá]gua|falta\s+d[ea]\s+energia)\b~iu', $txt)) {
            return 7;
        }

        if (preg_match('~\b(empregos?|vagas?|processos?\s+seletivos?|recrutamento|concursos?|editais?|eventos?|shows?|festivais?|agenda|programa[cç][aã](?:o|ões)|inscri[cç](?:[aã]o|ões)|matr[ií]culas?|cursos?|feiras?|campanhas?|prazos?|atendimentos?|vacina[cç][aã](?:o|ões)|mutirões?|mutir[aã]o)\b~iu', $txt)) {
            return 30;
        }

        return 90;
    }
}
