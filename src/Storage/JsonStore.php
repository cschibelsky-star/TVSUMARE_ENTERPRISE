<?php

declare(strict_types=1);

namespace TVSumare\Storage;

final class JsonStore
{
    public function __construct(private string $basePath)
    {
    }

    /** @return array<int|string, mixed> */
    public function read(string $file, array $default = []): array
    {
        $path = $this->path($file);
        if (!is_file($path)) {
            return $default;
        }

        $json = file_get_contents($path);
        if ($json === false || trim($json) === '') {
            return $default;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : $default;
    }

    /** @param array<int|string, mixed> $data */
    public function write(string $file, array $data): void
    {
        $path = $this->path($file);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function path(string $file): string
    {
        return rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . ltrim($file, '/\\');
    }
}
