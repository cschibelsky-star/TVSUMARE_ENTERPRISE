<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/json_atomic_storage.php';

$dir = sys_get_temp_dir() . '/tvs-json-test-' . bin2hex(random_bytes(6));
if (!mkdir($dir, 0700)) { exit(1); }
$path = $dir . '/list.json';
try {
    $first = tvs_json_update_list_atomic($path, static function (array $items): array {
        $items[] = ['id' => 1];
        return $items;
    });
    if (count($first) !== 1) { throw new RuntimeException('First write failed'); }
    $second = tvs_json_update_list_atomic($path, static function (array $items): array {
        $items[] = ['id' => 2];
        return $items;
    });
    if (count($second) !== 2) { throw new RuntimeException('Update lost'); }
    file_put_contents($path, '{broken');
    $rejected = false;
    try {
        tvs_json_update_list_atomic($path, static fn (array $items): array => []);
    } catch (JsonException $e) {
        $rejected = true;
    }
    if (!$rejected || file_get_contents($path) !== '{broken') {
        throw new RuntimeException('Corrupt JSON must not be overwritten');
    }
    echo "JSON_ATOMIC_STORAGE_TEST=PASS\n";
} finally {
    foreach (glob($dir . '/*') ?: [] as $file) { unlink($file); }
    rmdir($dir);
}
