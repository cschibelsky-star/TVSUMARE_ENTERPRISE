<?php

declare(strict_types=1);

namespace TVSumare\Editorial;

use TVSumare\Storage\JsonStore;

final class ProcessedRegistry
{
    private const FILE = 'processed_news.json';

    public function __construct(private JsonStore $store)
    {
    }

    /** @return string[] */
    public function hashes(): array
    {
        $data = $this->store->read(self::FILE, ['hashes' => []]);
        return array_values(array_filter($data['hashes'] ?? [], 'is_string'));
    }

    public function has(string $hash): bool
    {
        return in_array($hash, $this->hashes(), true);
    }

    public function remember(string $hash, string $url = '', string $title = ''): void
    {
        $data = $this->store->read(self::FILE, ['hashes' => [], 'items' => []]);
        $data['hashes'] = array_values(array_unique(array_merge($data['hashes'] ?? [], [$hash])));
        $data['items'][$hash] = [
            'url' => $url,
            'title' => $title,
            'processed_at' => date(DATE_ATOM),
        ];
        $this->store->write(self::FILE, $data);
    }
}
