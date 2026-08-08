<?php
header('Content-Type: text/plain; charset=utf-8');
$root = __DIR__;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $path => $info) {
    $base = basename($path);
    if ($base === '.htaccess') @chmod($path, 0644);
    elseif ($info->isDir()) @chmod($path, 0755);
    else @chmod($path, 0644);
}
@chmod($root, 0755);
echo "Permissões ajustadas. Pastas 755, arquivos 644. APAGUE este arquivo após usar.\n";
