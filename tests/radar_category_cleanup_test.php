<?php
declare(strict_types=1);

require_once __DIR__ . '/../monitor_lib.php';
require_once __DIR__ . '/../includes/radar_category_rules.php';

$fail = [];
function check_true(bool $ok, string $label): void { global $fail; if (!$ok) $fail[] = $label; }

$cases = [
    ['title'=>'PSB e PDT formam Federação política esquerda light', 'description'=>'PSB e PDT oficializaram a federação partidária', 'expected'=>'Política'],
    ['title'=>'Vereador propõe totem turístico no portal de Americana', 'description'=>'Iniciativa fortalece o turismo local', 'expected'=>'Política'],
    ['title'=>'Americana Shopping apoia ação do Novembro Azul', 'description'=>'Campanha de prevenção e saúde masculina', 'expected'=>'Saúde'],
    ['title'=>'Escolas recebem selo Educação Que Transforma', 'description'=>'Cinco escolas da rede municipal receberam o selo', 'expected'=>'Educação'],
    ['title'=>'Sumaré recebe pré-estreia de documentário', 'description'=>'Mostra de filmes locais com entrada gratuita', 'expected'=>'Cultura'],
];
foreach ($cases as $case) {
    check_true(
        tvs_radar_category_from_text($case['title'].' '.$case['description']) === $case['expected'],
        $case['title'].' => '.$case['expected']
    );
}

$title = 'Prefeitura realiza migração de bancos de dados';
$body = $title . ' A Prefeitura de Americana realizará uma migração programada durante o feriado.';
$clean = tvs_normalize_article_body($body, $title);
check_true(stripos($clean, $title) !== 0, 'Título duplicado permaneceu no início do corpo');
check_true(stripos($clean, 'migração programada') !== false, 'Fato principal foi removido junto com o título');

if ($fail) {
    foreach ($fail as $message) fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}
echo "RADAR_CATEGORY_CLEANUP_TEST=PASS\n";
