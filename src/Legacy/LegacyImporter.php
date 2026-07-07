<?php

declare(strict_types=1);

namespace TVSumare\Legacy;

use TVSumare\Editorial\EditorialPipeline;
use TVSumare\Storage\JsonStore;

final class LegacyImporter
{
    public function __construct(
        private JsonStore $store,
        private LegacyNewsMapper $mapper,
        private EditorialPipeline $pipeline
    ) {
    }

    /**
     * @return array{total:int, approved:int, rejected:int, items:array<int,array<string,mixed>>}
     */
    public function import(string $legacyFile = 'noticias.json'): array
    {
        $legacy = $this->store->read($legacyFile, []);
        $items = $this->extractItems($legacy);

        $processed = [];
        $approved = 0;
        $rejected = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $news = $this->mapper->map($item);
            $result = $this->pipeline->process($news);
            $processed[] = $result;

            if (($result['editorial_status'] ?? '') === 'approved') {
                $approved++;
            } else {
                $rejected++;
            }
        }

        $this->store->write('enterprise_news_imported.json', $processed);

        return [
            'total' => count($processed),
            'approved' => $approved,
            'rejected' => $rejected,
            'items' => $processed,
        ];
    }

    /** @return array<int,mixed> */
    private function extractItems(array $legacy): array
    {
        if (array_is_list($legacy)) {
            return $legacy;
        }

        foreach (['noticias', 'items', 'data', 'materias'] as $key) {
            if (isset($legacy[$key]) && is_array($legacy[$key])) {
                return $legacy[$key];
            }
        }

        return [];
    }
}
