<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/json_atomic_storage.php';

// Test worker invoked by the parent test. Uses only a disposable directory.
if (($argv[1] ?? '') === '--worker') {
    $path = $argv[2] ?? '';
    $count = (int)($argv[3] ?? 0);
    if ($path === '' || $count < 1) { exit(2); }
    for ($i = 0; $i < $count; $i++) {
        tvs_json_update_list_atomic($path, static function (array $items): array {
            $items[] = ['pid' => getmypid()];
            return $items;
        });
    }
    exit(0);
}
if (!function_exists('proc_open')) {
    fwrite(STDERR, "SKIP: proc_open unavailable\n");
    exit(2);
}
$dir = sys_get_temp_dir() . '/tvs-json-race-' . bin2hex(random_bytes(6));
if (!mkdir($dir, 0700)) { exit(1); }
$path = $dir . '/queue.json';
$workers = 5;
$writes = 40;
$processes = [];
try {
    for ($i = 0; $i < $workers; $i++) {
        $cmd = [PHP_BINARY, __FILE__, '--worker', $path, (string)$writes];
        $proc = proc_open($cmd, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($proc)) { throw new RuntimeException('Worker start failed'); }
        fclose($pipes[0]);
        $processes[] = [$proc, $pipes[1], $pipes[2]];
    }
    foreach ($processes as [$proc, $out, $err]) {
        stream_get_contents($out);
        $error = stream_get_contents($err);
        fclose($out);
        fclose($err);
        if (proc_close($proc) !== 0) { throw new RuntimeException('Worker failed: '.$error); }
    }
    $processes = [];
    $items = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($items) || count($items) !== $workers * $writes) {
        throw new RuntimeException('Concurrent updates lost: '.count((array)$items));
    }
    echo "JSON_ATOMIC_CONCURRENCY_TEST=PASS\n";
} finally {
    foreach ($processes as [$proc, $out, $err]) {
        if (is_resource($out)) fclose($out);
        if (is_resource($err)) fclose($err);
        if (is_resource($proc)) proc_terminate($proc);
    }
    foreach (glob($dir . '/*') ?: [] as $file) { unlink($file); }
    rmdir($dir);
}
