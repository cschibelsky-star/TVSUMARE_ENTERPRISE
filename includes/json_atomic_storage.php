<?php
declare(strict_types=1);

/**
 * Safe JSON list mutation. Every writer of a given file MUST use this API
 * for read-modify-write operations to obtain cross-process serialization.
 * Existing legacy writers are intentionally unchanged in this first PR.
 */
function tvs_json_update_list_atomic(string $path, callable $mutate): array
{
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('JSON storage directory unavailable');
    }
    $lock = fopen($path . '.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Unable to open JSON lock');
    }
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock JSON storage');
        }
        $current = [];
        if (is_file($path)) {
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new RuntimeException('Unable to read JSON storage');
            }
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !array_is_list($decoded)) {
                throw new RuntimeException('JSON storage must contain a list');
            }
            $current = $decoded;
        }
        $next = $mutate($current);
        if (!is_array($next) || !array_is_list($next)) {
            throw new InvalidArgumentException('JSON mutation must return a list');
        }
        $encoded = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = tempnam($dir, '.tvs-json-');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create JSON temporary file');
        }
        try {
            $permissions = is_file($path) ? (fileperms($path) & 0777) : 0664;
            if (file_put_contents($tmp, $encoded) !== strlen($encoded)) {
                throw new RuntimeException('Incomplete JSON temporary write');
            }
            if (!chmod($tmp, $permissions)) {
                throw new RuntimeException('Unable to set JSON permissions');
            }
            if (!rename($tmp, $path)) {
                throw new RuntimeException('Unable to replace JSON atomically');
            }
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
        return $next;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
