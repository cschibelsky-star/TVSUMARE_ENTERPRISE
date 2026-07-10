<?php

declare(strict_types=1);

namespace TVSumare\Automation;

use TVSumare\Storage\JsonStore;

final class AutomationLog
{
    private const FILE = 'automation_logs.json';

    public function __construct(private JsonStore $store)
    {
    }

    /** @param array<string, mixed> $payload */
    public function add(string $type, array $payload, string $status = 'received'): void
    {
        $logs = $this->store->read(self::FILE, []);
        $logs[] = [
            'type' => $type,
            'status' => $status,
            'payload' => $payload,
            'created_at' => date(DATE_ATOM),
        ];
        $this->store->write(self::FILE, array_slice($logs, -500));
    }

    /** @return array<int, mixed> */
    public function all(): array
    {
        return $this->store->read(self::FILE, []);
    }
}
