<?php
declare(strict_types=1);

require_once __DIR__ . '/../monitor_lib.php';

$testFile = sys_get_temp_dir() . '/tvs_test_atomic_' . uniqid() . '.json';

// 1. Teste de escrita atômica básica
$initialData = [
    ['id' => '1', 'title' => 'Primeira matéria'],
    ['id' => '2', 'title' => 'Segunda matéria']
];
$ok = tvs_save_json_file($testFile, $initialData);
assert($ok === true, 'Falha ao salvar JSON atomicamente');

$readData = tvs_read_json_file($testFile);
assert(count($readData) === 2, 'Contagem inicial incorreta');
assert($readData[0]['id'] === '1', 'Item 1 incorreto');

// 2. Teste de mutação sob tvs_with_json_lock
$res = tvs_with_json_lock($testFile, function($current) {
    $current[] = ['id' => '3', 'title' => 'Terceira matéria sob lock'];
    return $current;
});
assert($res === true, 'Falha no tvs_with_json_lock');

$updatedData = tvs_read_json_file($testFile);
assert(count($updatedData) === 3, 'Contagem após lock incorreta');
assert($updatedData[2]['id'] === '3', 'Item 3 incorreto');

// 3. Teste de não-corrupção e integridade
assert(!file_exists($testFile . '.tmp*'), 'Arquivo temporário não foi limpo');

// Limpeza
@unlink($testFile);
@unlink($testFile . '.lock');

echo "JSON_ATOMIC_PERSISTENCE_TEST=PASS\n";
