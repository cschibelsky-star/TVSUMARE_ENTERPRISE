<?php

declare(strict_types=1);

namespace TVSumare\Home;

use TVSumare\Editorial\FreshnessFilter;
use TVSumare\Media\ImageEngine;

final class HomeComposer
{
    public function __construct(
        private ImageEngine $images,
        private ?FreshnessFilter $freshness = null
    ) {
        $this->freshness ??= new FreshnessFilter(48);
    }

    /**
     * @param array<int, array<string, mixed>> $news
     * @param array<int, array<string, mixed>> $videos
     * @return array<string, mixed>
     */
    public function compose(array $news, array $videos): array
    {
        $freshNews = $this->freshness->onlyFresh($news);
        $qualityNews = array_values(array_filter($freshNews, fn (array $item): bool => ($item['quality_score'] ?? 0) >= 80));
        usort($qualityNews, fn (array $a, array $b): int => strtotime((string)($b['published_at'] ?? '')) <=> strtotime((string)($a['published_at'] ?? '')));

        foreach ($qualityNews as &$item) {
            $item['image'] = $this->images->resolveForNews($item);
        }
        unset($item);

        foreach ($videos as &$video) {
            $video['thumbnail'] = $this->images->resolveVideoThumbnail($video);
        }
        unset($video);

        return [
            'hero' => $qualityNews[0] ?? null,
            'editorials' => $this->groupByCategory(array_slice($qualityNews, 1)),
            'videos' => array_slice($videos, 0, 6),
            'latest' => array_slice($qualityNews, 0, 12),
            'powered_by' => 'Vitrine IA Pro',
            'hidden_old_or_rejected' => max(0, count($news) - count($freshNews)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $news
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByCategory(array $news): array
    {
        $groups = [];
        foreach ($news as $item) {
            $category = (string)($item['category'] ?? 'Geral');
            $groups[$category][] = $item;
        }

        return array_filter($groups, fn (array $items): bool => count($items) > 0);
    }
}
