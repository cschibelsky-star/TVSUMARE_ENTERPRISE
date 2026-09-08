<?php

declare(strict_types=1);

if (!function_exists('tvd_centro_ia_config')) {
    function tvd_centro_ia_config(): array
    {
        return [
            'url' => rtrim((string) (getenv('CENTRO_IA_URL') ?: 'http://vitrine_core_web_hml'), '/'),
            'token' => (string) (getenv('CENTRO_IA_INTERNAL_TOKEN') ?: ''),
            'project_id' => (string) (getenv('CENTRO_IA_PROJECT_ID') ?: 'tvsumare-enterprise'),
            'capability' => (string) (getenv('CENTRO_IA_CAPABILITY') ?: 'editorial_generation'),
            'timeout' => max(3, min((int) (getenv('CENTRO_IA_TIMEOUT') ?: 30), 120)),
        ];
    }
}

if (!function_exists('tvd_centro_ia_generate_text')) {
    function tvd_centro_ia_generate_text(string $prompt, array $options = []): array
    {
        $cfg = tvd_centro_ia_config();
        if ($cfg['token'] === '' || $cfg['url'] === '') {
            return ['ok' => false, 'error' => 'centro_ia_not_configured'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'curl_unavailable'];
        }

        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.25;
        $payload = json_encode([
            'project_id' => $cfg['project_id'],
            'capability' => $cfg['capability'],
            'input' => [
                'user' => $prompt,
                'response_format' => 'json',
                'temperature' => max(0.0, min($temperature, 2.0)),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {
            return ['ok' => false, 'error' => 'centro_ia_payload_invalid'];
        }

        $ch = curl_init($cfg['url'].'/api/internal/centro-ia/execute');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $cfg['timeout']),
            CURLOPT_TIMEOUT => $cfg['timeout'],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$cfg['token'],
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Vitrine-Project: '.$cfg['project_id'],
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw) || $raw === '') {
            return ['ok' => false, 'error' => 'centro_ia_no_response', 'detail' => $curlError];
        }
        if (strlen($raw) > 2097152) {
            return ['ok' => false, 'error' => 'centro_ia_response_too_large'];
        }

        $decoded = json_decode($raw, true);
        if ($http >= 400 || !is_array($decoded) || empty($decoded['ok'])) {
            return [
                'ok' => false,
                'error' => 'centro_ia_request_failed',
                'http' => $http,
                'detail' => is_array($decoded) ? ($decoded['error'] ?? null) : null,
            ];
        }

        $text = trim((string) ($decoded['output_text'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'error' => 'centro_ia_empty_output'];
        }

        return [
            'ok' => true,
            'text' => $text,
            'model' => $decoded['model'] ?? 'centro-ia',
            'provider' => 'centro-ia',
            'execution_id' => $decoded['execution_id'] ?? null,
            'raw' => $decoded,
        ];
    }
}
