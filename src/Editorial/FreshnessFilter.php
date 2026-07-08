<?php

declare(strict_types=1);

namespace TVSumare\Editorial;

use DateTimeImmutable;

final class FreshnessFilter
{
    public function __construct(private int $maxAgeHours = 48)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function onlyFresh(array $items): array
    {
        return array_values(array_filter($items, fn (array $item): bool => $this->isFresh($item)));
    }

    /** @param array<string, mixed> $item */
    public function isFresh(array $item): bool
    {
        $status = (string)($item['editorial_status'] ?? '');
        if ($status === 'archived' || $status === 'rejected' || $status === 'discarded') {
            return false;
        }

        $publishedAt = trim((string)($item['published_at'] ?? $item['created_at'] ?? $item['data'] ?? ''));
        if ($publishedAt === '') {
            return false;
        }

        try {
            $published = new DateTimeImmutable($publishedAt);
        } catch (\Throwable) {
            return false;
        }

        return (time() - $published->getTimestamp()) <= ($this->maxAgeHours * 3600);
    }
}
