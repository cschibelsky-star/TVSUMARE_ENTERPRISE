<?php
declare(strict_types=1);

// Contract regression for the social-pauta workflow; no live data is loaded.
$source = file_get_contents(__DIR__.'/../admin/redes.php');
if ($source === false) { fwrite(STDERR, "Unable to read admin/redes.php\n"); exit(1); }
$checks = [
    'no GET mutation' => !str_contains($source, "\$_GET['draft']"),
    'POST draft action' => str_contains($source, "isset(\$_POST['draft'])"),
    'CSRF token in draft form' => str_contains($source, 'name="draft"') && str_contains($source, 'tvs_csrf_field()'),
    'dedupe by social pauta id' => str_contains($source, 'social_pauta_id'),
    'HTTP(S) scheme allowlist' => str_contains($source, "['https','http']"),
    'external tab isolation' => str_contains($source, 'rel="noopener noreferrer"'),
];
$failed = false;
foreach ($checks as $name => $pass) {
    echo ($pass ? 'PASS: ' : 'FAIL: ').$name."\n";
    $failed = $failed || !$pass;
}
exit($failed ? 1 : 0);
