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

// An otherwise publishable item must not bypass regional review.
$regional = $items[0];
$regional['city'] = 'Santos';
$regional['body'] = str_repeat('Conteúdo jornalístico verificado. ', 10);
$regional['image'] = 'https://example.org/images/reportagem.jpg';
$regional['published_at'] = '2026-11-19';
$regional['regional_review_required'] = true;
$regional['queue_pending_reasons'] = ['Localidade fora da cobertura regional ou não confirmada'];
$split = tvs_radar_split_queue_by_readiness([$regional]);
check_v4(count($split['ready']) === 0 && count($split['processing']) === 1, 'Revisão regional bloqueia fila pronta');
check_v4(in_array('Localidade fora da cobertura regional ou não confirmada', $split['processing'][0]['queue_pending_reasons'], true), 'Motivo regional preservado');
$noTitle = $items[0];
$noTitle['title'] = '';
$noTitle['source_url'] = 'https://example.org/duplicada';
check_v4(count(tvs_radar_normalize_queue_by_rules([$noTitle, $noTitle])) === 1, 'URL duplicada sem título deduplicada');
