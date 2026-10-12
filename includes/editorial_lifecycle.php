<?php
/** Publication metadata and crash-safe, idempotent queue-to-news persistence. */
function tvs_editorial_timestamp($value): int
{
    if (!is_string($value) || trim($value) === '') return 0;
    $value = trim($value);
    // Relative dates must never move a story to the top on each request.
    if (!preg_match('/\d{4}|\d{1,2}\s+[A-Za-z]{3}\s+\d{2}/', $value)) return 0;
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('America/Sao_Paulo'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors && ($errors['warning_count'] || $errors['error_count'])) return 0;
        return max(0, $date->getTimestamp());
    } catch (Exception $e) { return 0; }
}

function tvs_editorial_date(array $item, array $fields): int
{
    foreach ($fields as $field) {
        $ts = tvs_editorial_timestamp($item[$field] ?? null);
        if ($ts > 0) return $ts;
    }
    return 0;
}

function tvs_editorial_display_date(int $timestamp): string
{
    if (!$timestamp) return 'Data não informada';
    return (new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y');
}

/** No outbound I/O at render time; a URL alone is not proof of image availability. */
function tvs_editorial_image_candidate(string $url, array $item): bool
{
    if (in_array($url, (array)($item['unavailable_images'] ?? []), true)) return false;
    if (preg_match('~^https?://~i', $url)) {
        $parts = parse_url($url);
        return is_array($parts) && !empty($parts['host']) && empty($parts['user']) && empty($parts['pass']);
    }
    if (preg_match('~^[a-z][a-z0-9+.-]*:|^//~i', $url)) return false;
    $root = realpath(dirname(__DIR__));
    $path = realpath(dirname(__DIR__).'/'.ltrim($url, '/'));
    if (!$path || !$root || !str_starts_with($path, $root.'/') || !is_file($path)) return false;
    return @getimagesize($path) !== false;
}

function tvs_editorial_source_date(array $item): int
{
    return tvs_editorial_date($item, ['source_published_at', 'resolved_published_at', 'published_at', 'pubDate', 'date']);
}

function tvs_editorial_publication_metadata(array $item, string $origin, string $originId, string $now): array
{
    if (empty($item['source_published_at'])) {
        $ts = tvs_editorial_source_date($item);
        if ($ts) $item['source_published_at'] = gmdate(DATE_ATOM, $ts);
    }
    $item['editorial_origin_id'] = $originId;
    $item['editorial_origin_queue'] = $origin;
    $item['old_draft_id'] = $origin === 'drafts' ? $originId : ($item['old_draft_id'] ?? '');
    $item['radar_queue_id'] = $origin === 'radar' ? $originId : ($item['radar_queue_id'] ?? '');
    $item['created_at'] = $item['created_at'] ?? $now;
    $item['published_at'] = $now;
    $item['updated_at'] = $now;
    $item['approved_at'] = $now;
    $item['status'] = 'publicado';
    $item['editorial_state'] = 'published';
    $item['queue_status'] = 'published';
    $item['queue_pending_reasons'] = [];
    return $item;
}

function tvs_editorial_matches_publication(array $item, array $news): bool
{
    foreach ($news as $published) {
        if (!is_array($published)) continue;
        $id = (string)($item['id'] ?? '');
        if ($id !== '' && in_array($id, [(string)($published['old_draft_id'] ?? ''), (string)($published['radar_queue_id'] ?? ''), (string)($published['editorial_origin_id'] ?? '')], true)) return true;
        // Exact original URL only; titles can change during review.
        $url = trim((string)($item['source_url'] ?? $item['url'] ?? ''));
        $publishedUrl = trim((string)($published['source_url'] ?? $published['url'] ?? ''));
        if ($url !== '' && $url === $publishedUrl) return true;
    }
    return false;
}

function tvs_editorial_read_strict(string $path): array
{
    if (!file_exists($path)) return [];
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('editorial_read_failed');
    $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($rows)) throw new RuntimeException('editorial_invalid_dataset');
    return $rows;
}

function tvs_editorial_atomic_write(string $path, array $rows): bool
{
    $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = tempnam(dirname($path), '.editorial-');
    if ($tmp === false) return false;
    $ok = file_put_contents($tmp, $json) === strlen($json);
    if ($ok) $ok = chmod($tmp, is_file($path) ? (fileperms($path) & 0777) : 0664);
    if ($ok) $ok = rename($tmp, $path);
    if (is_file($tmp)) unlink($tmp);
    return $ok;
}

function tvs_editorial_persist_publication(string $newsPath, string $queuePath, string $origin, string $originId, array $record): array
{
    $lock = fopen(dirname($newsPath).'/.editorial-publish.lock', 'c');
    if (!$lock) return ['ok' => false, 'error' => 'publication_lock_failed'];
    try {
        if (!flock($lock, LOCK_EX)) return ['ok' => false, 'error' => 'publication_lock_failed'];
        $news = tvs_editorial_read_strict($newsPath);
        $queue = tvs_editorial_read_strict($queuePath);
        $existing = null;
        foreach ($news as $n) {
            if (!is_array($n)) continue;
            if (($n['editorial_origin_queue'] ?? '') === $origin && (string)($n['editorial_origin_id'] ?? '') === $originId) $existing = $n;
            if ($origin === 'drafts' && (string)($n['old_draft_id'] ?? '') === $originId) $existing = $n;
        }
        $current = null;
        foreach ($queue as $row) if (is_array($row) && (string)($row['id'] ?? '') === $originId) $current = $row;
        if (!$existing) {
            if (!$current) return ['ok' => false, 'error' => 'queue_item_missing'];
            // Refuse to overwrite a review saved after this request read the queue.
            if (isset($record['origin_snapshot_sha']) && !hash_equals($record['origin_snapshot_sha'], hash('sha256', json_encode($current)))) return ['ok' => false, 'error' => 'review_changed_retry'];
            unset($record['origin_snapshot_sha']);
            $news[] = $record;
            if (!tvs_editorial_atomic_write($newsPath, $news)) return ['ok' => false, 'error' => 'news_write_failed'];
        }
        $kept = array_values(array_filter($queue, static fn($row) => !is_array($row) || (string)($row['id'] ?? '') !== $originId));
        // News is committed first. A retry finds its origin ID and only retries queue cleanup.
        $cleaned = tvs_editorial_atomic_write($queuePath, $kept);
        return ['ok' => true, 'existing' => $existing !== null, 'queue_cleaned' => $cleaned];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'editorial_persistence_failed'];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
