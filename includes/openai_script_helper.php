<?php

declare(strict_types=1);

if (!function_exists('tvs_openai_script_config')) {
    function tvs_openai_script_config(array $overrides = []): array
    {
        $config = [
            'api_key' => trim((string)(getenv('OPENAI_API_KEY') ?: '')),
            'model' => trim((string)(getenv('OPENAI_SCRIPT_MODEL') ?: 'gpt-5')),
            'timeout' => 45,
            'max_words' => 210,
        ];
        foreach ($overrides as $key => $value) {
            if ($value !== null && $value !== '') {
                $config[$key] = $value;
            }
        }
        return $config;
    }
}

if (!function_exists('tvs_openai_script_news_is_approved')) {
    function tvs_openai_script_news_is_approved(array $news): bool
    {
        $status = mb_strtolower(trim((string)($news['status'] ?? '')), 'UTF-8');
        $allowed = ['approved', 'aprovado', 'aprovada', 'published', 'publicado', 'publicada', 'active'];
        if ($status !== '' && in_array($status, $allowed, true)) {
            return true;
        }
        return !empty($news['published_at']) || !empty($news['approved_at']);
    }
}

if (!function_exists('tvs_openai_script_word_count')) {
    function tvs_openai_script_word_count(string $text): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    }
}

if (!function_exists('tvs_openai_script_estimated_seconds')) {
    function tvs_openai_script_estimated_seconds(string $text): int
    {
        return (int)max(1, min(90, round(tvs_openai_script_word_count($text) / 2.33)));
    }
}

if (!function_exists('tvs_openai_script_extract_output_text')) {
    function tvs_openai_script_extract_output_text(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return trim($response['output_text']);
        }
        $parts = [];
        foreach (($response['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                    $parts[] = (string)$content['text'];
                }
            }
        }
        return trim(implode("\n", $parts));
    }
}

if (!function_exists('tvs_openai_script_request')) {
    function tvs_openai_script_request(array $payload, array $config = []): array
    {
        $cfg = tvs_openai_script_config($config);
        $key = trim((string)($cfg['api_key'] ?? ''));
        if ($key === '') {
            return ['ok' => false, 'error' => 'OPENAI_API_KEY_NAO_CONFIGURADA'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'CURL_NAO_DISPONIVEL'];
        }
        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => (int)($cfg['timeout'] ?? 45),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$key,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $body === '') {
            return ['ok' => false, 'http_status' => $http, 'error' => 'OPENAI_SEM_RESPOSTA', 'message' => $error];
        }
        $json = json_decode((string)$body, true);
        if (!is_array($json)) {
            return ['ok' => false, 'http_status' => $http, 'error' => 'OPENAI_JSON_INVALIDO'];
        }
        if ($http >= 400) {
            return [
                'ok' => false,
                'http_status' => $http,
                'error' => (string)($json['error']['code'] ?? 'OPENAI_HTTP_ERROR'),
                'message' => (string)($json['error']['message'] ?? 'Falha na OpenAI API.'),
            ];
        }
        return ['ok' => true, 'http_status' => $http, 'data' => $json];
    }
}

if (!function_exists('tvs_openai_generate_three_scripts')) {
    function tvs_openai_generate_three_scripts(array $news, array $config = []): array
    {
        if (!tvs_openai_script_news_is_approved($news)) {
            return ['ok' => false, 'error' => 'MATERIA_NAO_APROVADA', 'message' => 'Somente matérias aprovadas ou publicadas podem gerar roteiro.'];
        }
        $body = trim((string)($news['body'] ?? $news['content'] ?? $news['summary'] ?? ''));
        if (mb_strlen($body, 'UTF-8') < 180) {
            return ['ok' => false, 'error' => 'MATERIA_APROVADA_INCOMPLETA', 'message' => 'A matéria aprovada não possui conteúdo suficiente para três roteiros fiéis.'];
        }
        $cfg = tvs_openai_script_config($config);
        $source = [
            'id' => (string)($news['id'] ?? ''),
            'title' => (string)($news['title'] ?? ''),
            'city' => (string)($news['city'] ?? 'Região'),
            'category' => (string)($news['category'] ?? 'Notícia'),
            'subtitle' => (string)($news['subtitle'] ?? ''),
            'summary' => (string)($news['summary'] ?? ''),
            'body' => mb_substr($body, 0, 7000, 'UTF-8'),
            'source_name' => (string)($news['source'] ?? 'Fonte consultada'),
            'source_url' => (string)($news['source_url'] ?? $news['url'] ?? ''),
            'published_at' => (string)($news['published_at'] ?? $news['created_at'] ?? ''),
        ];
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['recommended_option_id', 'recommendation_reason', 'script_options'],
            'properties' => [
                'recommended_option_id' => ['type' => 'string', 'enum' => ['direta', 'jornalistica', 'dinamica']],
                'recommendation_reason' => ['type' => 'string'],
                'script_options' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['id', 'label', 'script'],
                        'properties' => [
                            'id' => ['type' => 'string', 'enum' => ['direta', 'jornalistica', 'dinamica']],
                            'label' => ['type' => 'string'],
                            'script' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
        $instructions = implode("\n", [
            'Você é o roteirista jornalístico da TV Sumaré.',
            'Use exclusivamente os fatos da matéria aprovada fornecida.',
            'Produza exatamente três roteiros em português brasileiro:',
            '1) direta: objetiva e rápida;',
            '2) jornalistica: contextualizada, equilibrada e útil;',
            '3) dinamica: abertura forte e ritmo de vídeo, sem sensacionalismo.',
            'Cada roteiro deve ter no máximo 210 palavras e no máximo 90 segundos de locução.',
            'Não invente nomes, números, falas, datas, locais, causas ou consequências.',
            'Não mencione IA, ChatGPT, prompt ou HeyGen.',
            'Não inclua markdown, instruções de câmera ou marcações técnicas dentro do texto falado.',
            'Comece de forma natural e termine convidando o público a acompanhar a TV Sumaré.',
        ]);
        $payload = [
            'model' => (string)$cfg['model'],
            'store' => false,
            'instructions' => $instructions,
            'input' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => "MATÉRIA APROVADA\n".json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'tv_sumare_script_options',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];
        $result = tvs_openai_script_request($payload, $cfg);
        if (empty($result['ok'])) {
            return $result;
        }
        $outputText = tvs_openai_script_extract_output_text($result['data']);
        $decoded = json_decode($outputText, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'OPENAI_SAIDA_INVALIDA', 'message' => 'A resposta não retornou JSON estruturado.'];
        }
        $expectedIds = ['direta', 'jornalistica', 'dinamica'];
        $options = [];
        foreach (($decoded['script_options'] ?? []) as $option) {
            $id = (string)($option['id'] ?? '');
            $script = trim((string)($option['script'] ?? ''));
            if (!in_array($id, $expectedIds, true) || $script === '') {
                continue;
            }
            $words = tvs_openai_script_word_count($script);
            if ($words > (int)$cfg['max_words']) {
                return ['ok' => false, 'error' => 'ROTEIRO_EXCEDE_90_SEGUNDOS', 'message' => 'Uma das opções excedeu o limite editorial.'];
            }
            $options[$id] = [
                'id' => $id,
                'label' => (string)($option['label'] ?? ucfirst($id)),
                'estimated_seconds' => tvs_openai_script_estimated_seconds($script),
                'word_count' => $words,
                'script' => $script,
            ];
        }
        $ordered = [];
        foreach ($expectedIds as $id) {
            if (isset($options[$id])) {
                $ordered[$id] = $options[$id];
            }
        }
        $options = $ordered;
        if (count($options) !== 3) {
            return ['ok' => false, 'error' => 'ROTEIROS_INCOMPLETOS', 'message' => 'A OpenAI não retornou as três opções obrigatórias.'];
        }
        $recommended = (string)($decoded['recommended_option_id'] ?? 'jornalistica');
        if (!isset($options[$recommended])) {
            $recommended = 'jornalistica';
        }
        return [
            'ok' => true,
            'source_news_id' => $source['id'],
            'source_title' => $source['title'],
            'city' => $source['city'],
            'category' => $source['category'],
            'source_name' => $source['source_name'],
            'source_url' => $source['source_url'],
            'recommended_option_id' => $recommended,
            'recommendation_reason' => trim((string)($decoded['recommendation_reason'] ?? 'Melhor equilíbrio editorial para a pauta.')),
            'script_options' => array_values($options),
        ];
    }
}
