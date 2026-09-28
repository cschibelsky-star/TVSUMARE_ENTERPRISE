<?php
declare(strict_types=1);

if (!function_exists('tvs_track_pageview')) {
    function tvs_track_pageview(): void
    {
        if (PHP_SAPI === 'cli') return;
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET','HEAD'], true)) return;

        $uaRaw = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ua = strtolower($uaRaw);
        if ($ua !== '' && preg_match('~bot|crawler|spider|slurp|facebookexternalhit|whatsapp|telegrambot|preview|headless|uptime|monitor~i', $ua)) return;

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
        if (str_starts_with($path, '/admin/') || str_starts_with($path, '/api/')) return;
        $page = basename($path);
        if ($page === '' || $page === '/') $page = 'index.php';

        $now = time();
        $today = date('Y-m-d');
        $hour = date('H');

        $visitorId = (string)($_COOKIE['tvs_vid'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $visitorId)) {
            try { $visitorId = bin2hex(random_bytes(16)); }
            catch (Throwable $e) { $visitorId = md5(uniqid('', true)); }
        }

        $lastVisit = isset($_COOKIE['tvs_visit_ts']) ? (int)$_COOKIE['tvs_visit_ts'] : 0;
        $newVisit = $lastVisit <= 0 || ($now - $lastVisit) > 1800;

        if (!headers_sent()) {
            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            setcookie('tvs_vid', $visitorId, ['expires'=>$now+31536000,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
            setcookie('tvs_visit_ts', (string)$now, ['expires'=>$now+1800,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        }

        $visitorHash = hash('sha256', $today . '|' . $visitorId);

        $device = 'Desktop';
        if (preg_match('~tablet|ipad~i', $uaRaw)) $device = 'Tablet';
        elseif (preg_match('~mobile|iphone|android~i', $uaRaw)) $device = 'Mobile';

        $ref = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        $source = 'Direto';
        if ($ref !== '') {
            $host = strtolower((string)(parse_url($ref, PHP_URL_HOST) ?: ''));
            $ownHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            if ($host !== '' && $host !== $ownHost && !str_ends_with($host, '.' . $ownHost)) {
                if (str_contains($host, 'google.')) $source = 'Google';
                elseif (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) $source = 'Facebook';
                elseif (str_contains($host, 'instagram.com')) $source = 'Instagram';
                elseif (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) $source = 'YouTube';
                elseif (str_contains($host, 'bing.com')) $source = 'Bing';
                else $source = 'Referência externa';
            }
        }

        $utm = trim((string)($_GET['utm_source'] ?? ''));
        if ($utm !== '') {
            $utmNorm = strtolower($utm);
            $source = match (true) {
                str_contains($utmNorm, 'facebook'), str_contains($utmNorm, 'meta') => 'Facebook',
                str_contains($utmNorm, 'instagram') => 'Instagram',
                str_contains($utmNorm, 'google') => 'Google',
                str_contains($utmNorm, 'youtube') => 'YouTube',
                str_contains($utmNorm, 'whatsapp') => 'WhatsApp',
                default => 'Campanha: ' . substr($utm, 0, 40),
            };
        }

        $file = dirname(__DIR__) . '/data/access_metrics.json';
        $dir = dirname($file);
        if (!is_dir($dir) || !is_writable($dir)) return;

        $fp = @fopen($file, 'c+');
        if (!$fp) return;
        try {
            if (!flock($fp, LOCK_EX)) return;
            rewind($fp);
            $raw = stream_get_contents($fp);
            $data = json_decode($raw ?: '{}', true);
            if (!is_array($data)) $data = [];

            $data['pageviews_total'] = (int)($data['pageviews_total'] ?? 0) + 1;
            $data['visits_total'] = (int)($data['visits_total'] ?? 0) + ($newVisit ? 1 : 0);
            $data['last_updated'] = date(DATE_ATOM);

            $data['pages'] = is_array($data['pages'] ?? null) ? $data['pages'] : [];
            $data['pages'][$page] = is_array($data['pages'][$page] ?? null) ? $data['pages'][$page] : ['pageviews'=>0];
            $data['pages'][$page]['pageviews'] = (int)($data['pages'][$page]['pageviews'] ?? 0) + 1;
            $data['pages'][$page]['last_seen'] = date(DATE_ATOM);

            $data['days'] = is_array($data['days'] ?? null) ? $data['days'] : [];
            $data['days'][$today] = is_array($data['days'][$today] ?? null) ? $data['days'][$today] : ['pageviews'=>0,'visits'=>0,'unique_visitors'=>0,'hours'=>[],'sources'=>[],'devices'=>[]];
            $day =& $data['days'][$today];
            $day['pageviews'] = (int)($day['pageviews'] ?? 0) + 1;
            $day['visits'] = (int)($day['visits'] ?? 0) + ($newVisit ? 1 : 0);
            $day['hours'][$hour] = (int)($day['hours'][$hour] ?? 0) + 1;
            $day['sources'][$source] = (int)($day['sources'][$source] ?? 0) + 1;
            $day['devices'][$device] = (int)($day['devices'][$device] ?? 0) + 1;

            $data['unique_days'] = is_array($data['unique_days'] ?? null) ? $data['unique_days'] : [];
            $data['unique_days'][$today] = is_array($data['unique_days'][$today] ?? null) ? $data['unique_days'][$today] : [];
            if (!isset($data['unique_days'][$today][$visitorHash])) {
                $data['unique_days'][$today][$visitorHash] = $now;
                $day['unique_visitors'] = (int)($day['unique_visitors'] ?? 0) + 1;
            } else {
                $data['unique_days'][$today][$visitorHash] = $now;
            }

            $data['online'] = is_array($data['online'] ?? null) ? $data['online'] : [];
            foreach ($data['online'] as $hash=>$seenAt) if (($now-(int)$seenAt)>600) unset($data['online'][$hash]);
            $data['online'][$visitorHash] = $now;

            if (count($data['days']) > 400) { ksort($data['days']); $data['days'] = array_slice($data['days'], -400, null, true); }
            if (count($data['unique_days']) > 31) { ksort($data['unique_days']); $data['unique_days'] = array_slice($data['unique_days'], -31, null, true); }

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
