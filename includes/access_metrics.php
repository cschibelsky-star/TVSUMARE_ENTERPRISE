<?php
if (!function_exists('tvs_track_pageview')) {
    function tvs_track_pageview(): void
    {
        if (PHP_SAPI === 'cli') return;
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET','HEAD'], true)) return;

        $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($ua !== '' && preg_match('~bot|crawler|spider|slurp|facebookexternalhit|whatsapp|telegrambot|preview~i', $ua)) return;

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if (str_starts_with($path, '/admin/')) return;
        $page = basename($path);
        if ($page === '' || $page === '/') $page = 'index.php';

        $file = dirname(__DIR__) . '/data/access_metrics.json';
        $dir = dirname($file);
        if (!is_dir($dir) || !is_writable($dir)) return;

        $now = time();
        $today = date('Y-m-d');
        $lastVisit = isset($_COOKIE['tvs_visit_ts']) ? (int)$_COOKIE['tvs_visit_ts'] : 0;
        $newVisit = $lastVisit <= 0 || ($now - $lastVisit) > 1800;

        if (!headers_sent()) {
            setcookie('tvs_visit_ts', (string)$now, [
                'expires' => $now + 1800,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $fp = @fopen($file, 'c+');
        if (!$fp) return;
        try {
            if (!flock($fp, LOCK_EX)) return;
            $raw = stream_get_contents($fp);
            $data = json_decode($raw ?: '{}', true);
            if (!is_array($data)) $data = [];

            $data['pageviews_total'] = (int)($data['pageviews_total'] ?? 0) + 1;
            $data['visits_total'] = (int)($data['visits_total'] ?? 0) + ($newVisit ? 1 : 0);
            $data['last_updated'] = date(DATE_ATOM);

            if (!isset($data['pages']) || !is_array($data['pages'])) $data['pages'] = [];
            if (!isset($data['pages'][$page])) $data['pages'][$page] = ['pageviews' => 0, 'last_seen' => null];
            $data['pages'][$page]['pageviews'] = (int)($data['pages'][$page]['pageviews'] ?? 0) + 1;
            $data['pages'][$page]['last_seen'] = date(DATE_ATOM);

            if (!isset($data['days']) || !is_array($data['days'])) $data['days'] = [];
            if (!isset($data['days'][$today])) $data['days'][$today] = ['pageviews' => 0, 'visits' => 0];
            $data['days'][$today]['pageviews'] = (int)($data['days'][$today]['pageviews'] ?? 0) + 1;
            $data['days'][$today]['visits'] = (int)($data['days'][$today]['visits'] ?? 0) + ($newVisit ? 1 : 0);

            if (count($data['days']) > 400) {
                ksort($data['days']);
                $data['days'] = array_slice($data['days'], -400, null, true);
            }

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }
}
tvs_track_pageview();
