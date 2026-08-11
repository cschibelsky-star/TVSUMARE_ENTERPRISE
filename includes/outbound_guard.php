<?php
declare(strict_types=1);

function tvs_outbound_allowed_hosts(): array {
    $defaults = [
        'api.openai.com',
        'generativelanguage.googleapis.com',
        'api.heygen.com',
        'api.anthropic.com',
        'news.google.com',
        'agenciabrasil.ebc.com.br',
        'www.saopaulo.sp.gov.br',
        'portaldesumare.com.br',
        'sumare.sp.gov.br',
        'g1.globo.com',
        'www.bing.com',
        'search.yahoo.com',
        'www.youtube.com',
        'i.ytimg.com',
        'accounts.google.com',
        'oauth2.googleapis.com',
        'www.googleapis.com',
    ];
    $extra = array_filter(array_map(
        static fn($host) => strtolower(trim((string) $host)),
        explode(',', (string) (getenv('TVSUMARE_OUTBOUND_HOSTS') ?: ''))
    ));
    return array_values(array_unique(array_merge($defaults, $extra)));
}

function tvs_outbound_resolve_public_ipv4(string $host): ?string {
    $ips = gethostbynamel($host);
    if (!is_array($ips) || $ips === []) {
        return null;
    }
    foreach ($ips as $ip) {
        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            return $ip;
        }
    }
    return null;
}

function tvs_outbound_url_details($url): ?array {
    $url = trim((string) $url);
    if ($url === '' || strlen($url) > 4096) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
        return null;
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    $port = (int) ($parts['port'] ?? 443);
    if ($port !== 443) {
        return null;
    }
    $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
    if ($host === '' || !preg_match('/^[a-z0-9.-]+$/D', $host)) {
        return null;
    }
    if (!in_array($host, tvs_outbound_allowed_hosts(), true)) {
        return null;
    }
    $ip = tvs_outbound_resolve_public_ipv4($host);
    if ($ip === null) {
        return null;
    }
    return ['url' => $url, 'host' => $host, 'ip' => $ip];
}

function tvs_outbound_url_is_allowed($url): bool {
    return tvs_outbound_url_details($url) !== null;
}

function tvs_outbound_curl_options($url, int $timeout = 10): ?array {
    $details = tvs_outbound_url_details($url);
    if ($details === null) {
        return null;
    }
    // Uploads resumíveis de vídeo podem durar vários minutos. Chamadas normais
    // continuam usando os timeouts curtos definidos por cada consumidor.
    $timeout = max(2, min($timeout, 900));
    return [
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_RESOLVE => [
            $details['host'] . ':443:' . $details['ip'],
        ],
    ];
}
