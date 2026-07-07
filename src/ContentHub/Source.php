<?php

declare(strict_types=1);

namespace TVSumare\ContentHub;

final class Source
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $url,
        public readonly string $credit,
        public readonly bool $requiresAttribution = true
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['name'] ?? ''),
            (string)($data['type'] ?? 'rss'),
            (string)($data['url'] ?? ''),
            (string)($data['credit'] ?? ''),
            (bool)($data['requires_attribution'] ?? true)
        );
    }
}
