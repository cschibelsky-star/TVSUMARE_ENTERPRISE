<?php

declare(strict_types=1);

namespace TVSumare\Media;

final class ImageEngine
{
    private const DEFAULT_IMAGE = '/assets/img/tvsumare-default-news.jpg';

    /**
     * @param array<string, mixed> $news
     */
    public function resolveForNews(array $news): string
    {
        $candidates = [
            $news['og_image'] ?? null,
            $news['rss_image'] ?? null,
            $news['source_image'] ?? null,
            $news['body_image'] ?? null,
            $news['category_image'] ?? null,
            self::DEFAULT_IMAGE,
        ];

        foreach ($candidates as $candidate) {
            $image = trim((string)$candidate);
            if ($this->isUsable($image)) {
                return $image;
            }
        }

        return self::DEFAULT_IMAGE;
    }

    public function resolveVideoThumbnail(array $video): string
    {
        $thumb = trim((string)($video['thumbnail'] ?? ''));
        if ($this->isUsable($thumb)) {
            return $thumb;
        }

        return '/assets/img/tvsumare-play-default.jpg';
    }

    private function isUsable(string $image): bool
    {
        if ($image === '') {
            return false;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return true;
        }

        return false;
    }
}
