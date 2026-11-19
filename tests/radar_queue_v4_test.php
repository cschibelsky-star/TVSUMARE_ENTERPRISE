<?php
require_once __DIR__.'/../admin/radar_queue_rules.php';
function check_v4($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "OK: $message\n";
}
$items = [];
for ($i=0; $i<8; $i++) {
    $items[] = ['title'=>"Sumaré abre novo serviço municipal número $i",
        'city'=>'Sumaré','category'=>'Cidade','source_url'=>"https://example.org/noticia/$i",
        'editorial_score'=>100-$i,'created_at'=>'2026-11-19'];
}
$result = tvs_radar_normalize_queue_by_rules($items);
check_v4(count($result)===8, 'Nenhuma pauta perdida por quota de categoria');
$outside = tvs_radar_normalize_queue_by_rules([[
    'title'=>'Evento em outra cidade','city'=>'Santos','category'=>'Cidade',
    'source_url'=>'https://example.org/santos/1'
]]);
check_v4(!empty($outside[0]['regional_review_required']), 'Fora da regiao sinalizado para revisao');
$duplicate = tvs_radar_normalize_queue_by_rules([$items[0],$items[0]]);
check_v4(count($duplicate)===1, 'URL repetida deduplicada');
echo "RADAR_QUEUE_V4_TEST=PASS\n";
