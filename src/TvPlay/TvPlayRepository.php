<?php

declare(strict_types=1);

namespace TVSumare\TvPlay;

use TVSumare\Storage\JsonStore;

final class TvPlayRepository
{
    private const FILE = 'videos.json';

    public function __construct(private JsonStore $store)
    {
    }

    /** @param array<string, mixed> $payload */
    public function publish(array $payload): void
    {
        $data = $this->store->read(self::FILE, []);
        $videos = isset($data['videos']) && is_array($data['videos']) ? $data['videos'] : $data;
        $videos[] = [
            'title' => $payload['title'] ?? $payload['titulo'] ?? 'Vídeo TV Sumaré',
            'url' => $payload['video_url'] ?? $payload['url'] ?? '',
            'thumbnail' => $payload['thumbnail'] ?? '',
            'city' => $payload['city'] ?? $payload['cidade'] ?? 'Sumaré',
            'category' => $payload['category'] ?? $payload['categoria'] ?? 'TV Play',
            'duration' => $payload['duration'] ?? $payload['duracao'] ?? '',
            'source' => $payload['source'] ?? 'HeyGen',
            'published_at' => date(DATE_ATOM),
        ];
        $this->store->write(self::FILE, ['videos' => $videos]);
    }
}
