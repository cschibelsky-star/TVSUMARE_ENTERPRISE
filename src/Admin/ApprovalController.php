<?php

declare(strict_types=1);

namespace TVSumare\Admin;

use TVSumare\Storage\JsonStore;

final class ApprovalController
{
    public function __construct(private JsonStore $store)
    {
    }

    public function approve(string $hash): void
    {
        $items = $this->store->read('enterprise_news_imported.json', []);
        $published = $this->store->read('published_news.json', []);

        foreach ($items as $index => $item) {
            if (($item['hash'] ?? '') === $hash) {
                $item['editorial_status'] = 'published';
                $item['published_on_site_at'] = date(DATE_ATOM);
                $published[] = $item;
                unset($items[$index]);
                break;
            }
        }

        $this->store->write('enterprise_news_imported.json', array_values($items));
        $this->store->write('published_news.json', array_values($published));
    }

    public function discard(string $hash): void
    {
        $items = $this->store->read('enterprise_news_imported.json', []);
        $discarded = $this->store->read('discarded_news.json', []);

        foreach ($items as $index => $item) {
            if (($item['hash'] ?? '') === $hash) {
                $item['editorial_status'] = 'discarded';
                $item['discarded_at'] = date(DATE_ATOM);
                $discarded[] = $item;
                unset($items[$index]);
                break;
            }
        }

        $this->store->write('enterprise_news_imported.json', array_values($items));
        $this->store->write('discarded_news.json', array_values($discarded));
    }
}
