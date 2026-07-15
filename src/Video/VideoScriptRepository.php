<?php

declare(strict_types=1);

namespace TVSumare\Video;

use TVSumare\Storage\JsonStore;

final class VideoScriptRepository
{
    private const FILE = 'video_script_options.json';

    public function __construct(private JsonStore $store)
    {
    }

    /** @param array<string, mixed> $payload */
    public function saveOptions(array $payload): void
    {
        $items = $this->store->read(self::FILE, []);
        $newsId = (string)($payload['id'] ?? $payload['news_id'] ?? $payload['hash'] ?? sha1(json_encode($payload)));
        $items[$newsId] = [
            'news_id' => $newsId,
            'title' => $payload['titulo'] ?? $payload['title'] ?? '',
            'scripts' => $payload['scripts'] ?? [],
            'status' => 'awaiting_editor_choice',
            'payload' => $payload,
            'created_at' => date(DATE_ATOM),
        ];
        $this->store->write(self::FILE, $items);
    }

    /** @return array<int|string, mixed> */
    public function all(): array
    {
        return $this->store->read(self::FILE, []);
    }
}
