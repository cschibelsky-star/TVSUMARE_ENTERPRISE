<?php
declare(strict_types=1);

/** Pure projection; caller must authenticate and authorize access. No HTTP route. */
final class IndoorCatalogExport
{
    public static function preview(array $items): array
    {
        $out = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $id = $item['source_id'] ?? null;
            $type = $item['type'] ?? null;
            $approval = $item['indoor_approval'] ?? [];
            $rights = $item['rights'] ?? [];
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $id)) continue;
            if (!in_array($type, ['news', 'ad', 'video', 'podcast'], true)) continue;
            if (!in_array($item['destination'] ?? null, ['indoor_only', 'both'], true)) continue;
            if (!in_array($item['editorial_status'] ?? null, ['approved', 'approved_for_indoor'], true)) continue;
            if (!is_array($approval) || ($approval['authorized'] ?? false) !== true) continue;
            if (!is_array($rights) || ($rights['indoor_allowed'] ?? false) !== true) continue;
            $expiry = $rights['valid_until'] ?? null;
            if ($expiry !== null && (!is_string($expiry) || strtotime($expiry) === false || strtotime($expiry) <= time())) continue;
            $media = $item['media_path'] ?? '';
            if (!is_string($media) || !preg_match('~^uploads/(?:anuncios|videos|noticias|indoor)/[a-zA-Z0-9_-]+[.](?:jpg|jpeg|png|webp|mp4)$~iD', $media)) continue;
            if (!is_string($item['title'] ?? null) || trim($item['title']) === '') continue;
            $key = $type . ':' . $id;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = [
                'source' => 'tvsumare',
                'source_id' => $id,
                'type' => $type,
                'title' => trim($item['title']),
                'destination' => $item['destination'],
                'media_path' => $media,
                'slot' => $type === 'ad' ? 'lateral' : 'central',
                'indoor_consent' => true,
                'editorial_status' => 'approved',
                'rights' => ['indoor_allowed' => true, 'valid_until' => $expiry],
                'import_state' => 'pending_indoor_review',
            ];
        }
        return $out;
    }
}
