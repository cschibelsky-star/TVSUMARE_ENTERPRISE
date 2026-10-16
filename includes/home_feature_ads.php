<?php
/**
 * Only explicitly published campaigns with a local, validated media file are eligible.
 * Customer details and receipts never leave the administrative data store.
 */
function tvs_premium_local_media($path, $kind): string {
    $path = trim((string)$path);
    $extension = $kind === 'video' ? 'mp4' : '(?:jpg|jpeg|png|webp)';
    if (!preg_match('~^uploads/anuncios/[A-Za-z0-9_-]+\.'.$extension.'$~i', $path)) return '';
    $real = realpath(dirname(__DIR__).'/'.$path);
    $base = realpath(dirname(__DIR__).'/uploads/anuncios');
    if ($real === false || $base === false || dirname($real) !== $base || !is_file($real)) return '';
    return $path;
}
function tvs_premium_link($url): string {
    $url = trim((string)$url);
    if (preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL)) return $url;
    if (preg_match('~^/(?!/)[A-Za-z0-9/_?=&%.-]*$~', $url)) return $url;
    return '';
}
function tvs_premium_ads(): array {
    $file = dirname(__DIR__).'/data/anuncios_pedidos.json';
    $rows = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
    if (!is_array($rows)) return [];
    $now = time();
    $ads = [];
    foreach ($rows as $row) {
        if (!is_array($row) || ($row['status']??'') !== 'publicado') continue;
        if (!in_array((string)($row['placement']??''), ['Home', 'Home Premium'], true)) continue;
        $begin = trim((string)($row['start_at']??''));
        $end = trim((string)($row['end_at']??''));
        if ($begin !== '' && (($ts = strtotime($begin)) === false || $ts > $now)) continue;
        if ($end !== '' && (($ts = strtotime($end)) === false || $ts < $now)) continue;
        $video = tvs_premium_local_media($row['video']??'', 'video');
        $duration = (float)($row['video_duration']??0);
        $image = tvs_premium_local_media($row['banner']??'', 'image');
        if ($video !== '' && ($duration <= 0 || $duration > 10)) $video = '';
        if ($video === '' && $image === '') continue;
        $advertiser = trim((string)($row['company']??'Publicidade regional'));
        if (preg_match('/^.{0,90}/us', $advertiser, $parts)) $advertiser = $parts[0];
        $ads[] = [
            'id' => substr((string)($row['id']??''),0,80),
            'kind' => $video !== '' ? 'video' : 'image',
            'src' => $video !== '' ? $video : $image,
            'title' => $advertiser,
            'href' => tvs_premium_link($row['link']??'') ?: 'anuncie.php',
        ];
        if (count($ads) >= 40) break;
    }
    return $ads;
}
