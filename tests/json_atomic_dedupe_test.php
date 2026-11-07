<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/json_atomic_storage.php';

// Simulates two independent requests submitting the same social pauta ID.
if (($argv[1] ?? '') === '--worker') {
    $path = $argv[2];
    tvs_json_update_list_atomic($path, static function (array $drafts): array {
        foreach ($drafts as $draft) {
            if (($draft['social_pauta_id'] ?? null) === 'social-42') {
                return $drafts;
            }
        }
        $drafts[] = ['id' => 'draft-'.getmypid(), 'social_pauta_id' => 'social-42'];
        return $drafts;
    });
    exit(0);
}
$dir = sys_get_temp_dir().'/tvs-json-dedupe-'.bin2hex(random_bytes(6));
if (!mkdir($dir, 0700)) { exit(1); }
$path = $dir.'/drafts.json';
$processes = [];
try {
    for ($i=0; $i<6; $i++) {
        $proc = proc_open([PHP_BINARY, __FILE__, '--worker', $path], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($proc)) { throw new RuntimeException('Cannot start worker'); }
        fclose($pipes[0]);
        $processes[] = [$proc, $pipes[1], $pipes[2]];
    }
    foreach ($processes as [$proc, $out, $err]) {
        stream_get_contents($out);
        $error = stream_get_contents($err);
        fclose($out); fclose($err);
        if (proc_close($proc) !== 0) { throw new RuntimeException($error); }
    }
    $processes = [];
    $drafts = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (count($drafts) !== 1 || $drafts[0]['social_pauta_id'] !== 'social-42') {
        throw new RuntimeException('Concurrent duplicate drafts created');
    }
    echo "JSON_ATOMIC_DEDUPE_TEST=PASS\n";
} finally {
    foreach ($processes as [$proc,$out,$err]) {
        if (is_resource($out)) fclose($out);
        if (is_resource($err)) fclose($err);
        if (is_resource($proc)) proc_terminate($proc);
    }
    foreach (glob($dir.'/*') ?: [] as $file) unlink($file);
    rmdir($dir);
}
