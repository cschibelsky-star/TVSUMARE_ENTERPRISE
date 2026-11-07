<?php
/**
 * Pure editorial workflow policy. No IO, no publishing, no side effects.
 * Legacy records without editorial_state remain in editing until validated.
 */
function tvs_editorial_text_length($text): int {
    $clean = trim(preg_replace('/\\s+/u', ' ', strip_tags((string)$text)));
    return function_exists('mb_strlen') ? mb_strlen($clean, 'UTF-8') : strlen($clean);
}
function tvs_editorial_source_ready(array $item): bool {
    $source = (string)($item['source_body'] ?? '');
    $title = trim((string)($item['title'] ?? ''));
    $clean = trim(preg_replace('/\\s+/u', ' ', strip_tags($source)));
    if ($title !== '' && $clean === $title) return false;
    return tvs_editorial_text_length($source) >= 350;
}
function tvs_editorial_article_ready(array $item): bool {
    return tvs_editorial_text_length($item['body'] ?? '') >= 350
        && tvs_editorial_source_ready($item)
        && !empty($item['editorial_ai_completed']);
}
function tvs_editorial_required_fields(array $item): array {
    $missing = [];
    foreach (['title', 'body', 'city', 'category', 'source', 'source_url'] as $field) {
        if (trim((string)($item[$field] ?? '')) === '') $missing[] = $field;
    }
    $url = trim((string)($item['source_url'] ?? ''));
    if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) ||
        !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
        $missing[] = 'valid_source_url';
    }
    if (!tvs_editorial_article_ready($item)) $missing[] = 'editorial_integrity';
    return $missing;
}
function tvs_editorial_next_state(string $state, string $action, array $item): ?string {
    $transitions = [
        'captada' => ['triage' => 'triagem'],
        'triagem' => ['edit' => 'em_edicao', 'discard' => 'descartada'],
        'em_edicao' => ['submit' => 'pronta_para_revisao'],
        'devolvida' => ['edit' => 'em_edicao'],
        'pronta_para_revisao' => ['approve' => 'aprovada', 'return' => 'devolvida', 'discard' => 'descartada'],
        'aprovada' => ['publish' => 'publicada'],
    ];
    $next = $transitions[$state][$action] ?? null;
    if ($next === 'pronta_para_revisao' && tvs_editorial_required_fields($item)) return null;
    if ($next === 'aprovada' && tvs_editorial_required_fields($item)) return null;
    return $next;
}
