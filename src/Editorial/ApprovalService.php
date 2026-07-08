<?php

declare(strict_types=1);

namespace TVSumare\Editorial;

use TVSumare\Storage\JsonStore;

final class ApprovalService
{
    public function __construct(private JsonStore $store)
    {
    }

    /** @return array{ok:bool,message:string} */
    public function approve(string $hash): array
    {
        return $this->moveByHash($hash, 'enterprise_news_imported.json', 'published_news.json', 'published');
    }

    /** @return array{ok:bool,message:string} */
    public function discard(string $hash): array
    {
        return $this->moveByHash($hash, 'enterprise_news_imported.json', 'discarded_news.json', 'discarded');
    }

    /** @return array{ok:bool,message:string} */
    private function moveByHash(string $hash, string $fromFile, string $toFile, string $status): array
    {
        $hash = trim($hash);
        if ($hash === '') {
            return ['ok' => false, 'message' => 'Hash inválido.'];
        }

        $pending = $this->store->read($fromFile, []);
        $target = $this->store->read($toFile, []);
        $found = null;
        $remaining = [];

        foreach ($pending as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ((string)($item['hash'] ?? '') === $hash) {
                $found = $item;
                continue;
            }

            $remaining[] = $item;
        }

        if ($found === null) {
            return ['ok' => false, 'message' => 'Matéria não encontrada ou já processada.'];
        }

        $found['editorial_status'] = $status;
        $found[$status . '_at'] = date(DATE_ATOM);
        $target[] = $found;

        $this->store->write($fromFile, $remaining);
        $this->store->write($toFile, $target);

        return ['ok' => true, 'message' => 'Matéria ' . $status . ' com sucesso.'];
    }
}
