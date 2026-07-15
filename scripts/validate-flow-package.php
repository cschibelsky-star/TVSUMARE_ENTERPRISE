<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$warnings = [];

$requiredDirs = [
    'n8n/workflows',
    'config',
    'docs',
    'public/api',
    'src',
    'infra/n8n',
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($root . '/' . $dir)) {
        $errors[] = "Diretório obrigatório ausente: {$dir}";
    }
}

$workflowFiles = glob($root . '/n8n/workflows/*.json') ?: [];
if (count($workflowFiles) < 35) {
    $warnings[] = 'Foram encontrados menos de 35 workflows JSON.';
}

$webhookPaths = [];
foreach ($workflowFiles as $file) {
    $json = file_get_contents($file);
    $data = json_decode($json ?: '', true);
    if (!is_array($data)) {
        $errors[] = 'JSON inválido: ' . str_replace($root . '/', '', $file);
        continue;
    }
    if (empty($data['name']) || !isset($data['nodes']) || !is_array($data['nodes'])) {
        $errors[] = 'Workflow sem name/nodes válidos: ' . str_replace($root . '/', '', $file);
    }
    foreach ($data['nodes'] as $node) {
        if (($node['type'] ?? '') === 'n8n-nodes-base.webhook') {
            $path = $node['parameters']['path'] ?? null;
            if (is_string($path) && $path !== '') {
                if (isset($webhookPaths[$path])) {
                    $errors[] = "Webhook duplicado '{$path}' em {$webhookPaths[$path]} e " . basename($file);
                }
                $webhookPaths[$path] = basename($file);
            }
        }
    }
}

$phpFiles = array_merge(
    glob($root . '/src/**/*.php') ?: [],
    glob($root . '/public/**/*.php') ?: []
);
foreach ($phpFiles as $file) {
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $errors[] = implode("\n", $output);
    }
}

$configFiles = glob($root . '/config/*.json') ?: [];
foreach ($configFiles as $file) {
    json_decode(file_get_contents($file) ?: '', true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Config JSON inválido: ' . basename($file) . ' — ' . json_last_error_msg();
    }
}

$requiredApiFiles = [
    'public/api/health/status.php',
    'public/api/automation/monitoring-log.php',
    'public/api/social/log-instagram-request.php',
    'public/api/social/video-distribution-log.php',
    'public/api/video/scripts/save-options.php',
    'public/api/tvplay/publish-video.php',
];
foreach ($requiredApiFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = "Endpoint obrigatório ausente: {$file}";
    }
}

echo "Vitrine IA Flow — Validação Estática\n";
echo "Workflows encontrados: " . count($workflowFiles) . "\n";
echo "Webhooks únicos: " . count($webhookPaths) . "\n";
echo "Configs JSON: " . count($configFiles) . "\n";
echo "Arquivos PHP verificados: " . count($phpFiles) . "\n\n";

foreach ($warnings as $warning) {
    echo "[AVISO] {$warning}\n";
}
foreach ($errors as $error) {
    echo "[ERRO] {$error}\n";
}

if ($errors !== []) {
    exit(1);
}

echo "[OK] Pacote validado sem erros estruturais.\n";
