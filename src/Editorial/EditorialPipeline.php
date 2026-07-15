<?php

declare(strict_types=1);

namespace TVSumare\Editorial;

use TVSumare\Media\ImageEngine;

final class EditorialPipeline
{
    public function __construct(
        private NewsQualityGate $qualityGate,
        private ImageEngine $imageEngine,
        private ProcessedRegistry $registry
    ) {
    }

    /**
     * @param array<string, mixed> $news
     * @return array<string, mixed>
     */
    public function process(array $news): array
    {
        $news['image'] = $this->imageEngine->resolveForNews($news);

        $hash = $this->qualityGate->hash((string)($news['title'] ?? ''), (string)($news['url'] ?? ''));
        $known = $this->registry->hashes();
        $evaluation = $this->qualityGate->evaluate($news, $known);

        $news['hash'] = $hash;
        $news['quality_score'] = $evaluation['score'];
        $news['quality_reasons'] = $evaluation['reasons'];
        $news['editorial_status'] = $evaluation['approved'] ? 'approved' : 'rejected';

        if (!$evaluation['approved']) {
            $this->registry->remember($hash, (string)($news['url'] ?? ''), (string)($news['title'] ?? ''));
        }

        return $news;
    }
}
