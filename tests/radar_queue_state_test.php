<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/radar_queue_state.php';

$item = [
    'id' => 'q1',
    'title' => 'Nova Policlínica de Sumaré terá 3 mil metros quadrados',
    'city' => 'Sumaré',
    'source_url' => 'https://example.com/noticia/policlinica',
    'status' => 'aguardando',
];

assert(tvs_radar_queue_state($item) === 'ready_for_approval', 'Legacy aguardando não foi normalizado');
assert(tvs_radar_queue_transition_allowed('ready_for_approval', 'approved') === true, 'Transição de aprovação deveria ser válida');
assert(tvs_radar_queue_transition_allowed('ready_for_approval', 'published') === true, 'Fluxo legado de publicação direta deveria continuar válido');
assert(tvs_radar_queue_transition_allowed('published', 'approved') === false, 'Estado publicado não pode voltar a aprovado');

$transitioned = tvs_radar_queue_transition($item, 'approved', 'Editor aprovou a pauta');
assert($transitioned['workflow_state'] === 'approved', 'Estado aprovado não persistido');
assert($transitioned['workflow_previous_state'] === 'ready_for_approval', 'Estado anterior incorreto');

$key1 = tvs_radar_queue_idempotency_key($item);
$key2 = tvs_radar_queue_idempotency_key($item);
assert($key1 === $key2, 'Chave de idempotência não é determinística');

$leased = tvs_radar_queue_lease($item, 'worker-test', 60);
assert(is_array($leased) && !empty($leased['lease_id']), 'Lease não foi criado');
assert(tvs_radar_queue_lease($leased, 'worker-other', 60) === null, 'Segundo worker recebeu lease indevidamente');
assert(tvs_radar_queue_lease_valid($leased, $leased['lease_id']) === true, 'Lease válido foi rejeitado');

echo "RADAR_QUEUE_STATE_TEST=PASS\n";
