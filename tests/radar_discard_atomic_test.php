<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/json_atomic_storage.php';

$source = (string)file_get_contents(__DIR__.'/../admin/monitor_lib.php');
if (
    !str_contains($source, 'tvs_json_append_bounded_atomic($file,$entry,300)') ||
    !str_contains($source, "require_once dirname(__DIR__).'/includes/json_atomic_storage.php';")
) {
    throw new RuntimeException('Radar discard writer must use atomic bounded append');
}
$dir = sys_get_temp_dir().'/tvs-discard-'.bin2hex(random_bytes(6));
if (!mkdir($dir,0700)) { throw new RuntimeException('Cannot create test directory'); }
$file = $dir.'/pautas_descartadas.json';
try {
    for ($i=0; $i<305; $i++) {
        tvs_json_append_bounded_atomic($file,['id'=>$i],300);
    }
    $data = json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    if (count($data)!==300 || $data[0]['id']!==5 || $data[299]['id']!==304) {
        throw new RuntimeException('Discard retention or ordering regression');
    }
    file_put_contents($file, '{invalid');
    try {
        tvs_json_append_bounded_atomic($file,['id'=>999],300);
        throw new RuntimeException('Corrupt JSON accepted unexpectedly');
    } catch (JsonException $expected) {
        if (file_get_contents($file)!=='{invalid') {
            throw new RuntimeException('Corrupt JSON was overwritten');
        }
    }
    echo "RADAR_DISCARD_ATOMIC_TEST=PASS\n";
} finally {
    foreach (glob($dir.'/*') ?: [] as $entry) unlink($entry);
    rmdir($dir);
}
