<?php
declare(strict_types=1);

/**
 * Estados persistidos da fila editorial. O campo legado status é mantido
 * durante a migração; workflow_state é a fonte explícita para novos itens.
 */
function tvs_radar_queue_states(): array
{
    return [
        'detected',
        'source_pending',
        'enriching',
        'editorial_review',
        'image_review',
        'ready_for_approval',
        'approved',
        'video_script_pending',
        'video_script_ready',
        'video_generation_pending',
        'video_processing',
        'video_ready',
        'published',
        'failed',
        'discarded',
    ];
}

function tvs_radar_queue_legacy_state(array $item): string
{
    if (!empty($item['published_at']) || ($item['status'] ?? '') === 'publicado') {
        return 'published';
    }
    if (($item['status'] ?? '') === 'descartado') {
        return 'discarded';
    }
    if (!empty($item['image_review_required'])) {
        return 'image_review';
    }
    if (($item['review_level'] ?? '') === 'revisao_obrigatoria' || !empty($item['sensitive_review_required'])) {
        return 'editorial_review';
    }
    if (($item['status'] ?? 'aguardando') === 'aguardando') {
        return 'ready_for_approval';
    }
    return 'detected';
}

function tvs_radar_queue_state(array $item): string
{
    $state = trim((string)($item['workflow_state'] ?? ''));
    return in_array($state, tvs_radar_queue_states(), true)
        ? $state
        : tvs_radar_queue_legacy_state($item);
}

function tvs_radar_queue_transition_allowed(string $from, string $to): bool
{
    if ($from === $to) {
        return true;
    }
    $transitions = [
        'detected' => ['source_pending', 'enriching', 'discarded', 'failed'],
        'source_pending' => ['enriching', 'failed', 'discarded'],
        'enriching' => ['editorial_review', 'image_review', 'ready_for_approval', 'failed', 'discarded'],
        'editorial_review' => ['image_review', 'ready_for_approval', 'approved', 'failed', 'discarded'],
        'image_review' => ['editorial_review', 'ready_for_approval', 'approved', 'failed', 'discarded'],
        // O fluxo legado publica diretamente após aprovação humana; essa transição
        // continua válida enquanto a etapa intermediária approved é formalizada.
        'ready_for_approval' => ['editorial_review', 'approved', 'published', 'discarded'],
        'approved' => ['video_script_pending', 'published', 'failed'],
        'video_script_pending' => ['video_script_ready', 'failed'],
        'video_script_ready' => ['video_generation_pending', 'failed'],
        'video_generation_pending' => ['video_processing', 'failed'],
        'video_processing' => ['video_ready', 'failed'],
        'video_ready' => ['published', 'failed'],
        'failed' => ['source_pending', 'enriching', 'video_script_pending', 'video_generation_pending', 'discarded'],
        'published' => [],
        'discarded' => ['source_pending', 'enriching'],
    ];
    return in_array($to, $transitions[$from] ?? [], true);
}

function tvs_radar_queue_transition(array $item, string $to, string $reason = ''): array
{
    $from = tvs_radar_queue_state($item);
    if (!tvs_radar_queue_transition_allowed($from, $to)) {
        throw new InvalidArgumentException("Transição de fila inválida: {$from} -> {$to}");
    }
    $now = date('c');
    $item['workflow_state'] = $to;
    $item['workflow_previous_state'] = $from;
    $item['workflow_updated_at'] = $now;
    if ($reason !== '') {
        $item['workflow_transition_reason'] = $reason;
    }
    if ($to === 'failed') {
        $item['attempts'] = (int)($item['attempts'] ?? 0) + 1;
        $item['last_error'] = $reason;
    }
    if (in_array($to, ['published', 'discarded'], true)) {
        $item['completed_at'] = $now;
        unset($item['lease_id'], $item['lease_until'], $item['leased_at']);
    }
    return $item;
}

function tvs_radar_queue_idempotency_key(array $item): string
{
    $source = trim((string)($item['source_url'] ?? $item['url'] ?? ''));
    if ($source !== '') {
        return 'url:' . hash('sha256', strtolower($source));
    }
    $title = preg_replace('/\s+/u', ' ', trim((string)($item['title'] ?? '')));
    $city = trim((string)($item['city'] ?? ''));
    return 'title:' . hash('sha256', strtolower($city . '|' . $title));
}

function tvs_radar_queue_lease(array $item, string $worker, int $seconds = 300): ?array
{
    $now = time();
    $until = (int)($item['lease_until'] ?? 0);
    if ($until > $now && ($item['lease_id'] ?? '') !== '') {
        return null;
    }
    $item['lease_id'] = bin2hex(random_bytes(12));
    $item['leased_by'] = $worker;
    $item['leased_at'] = date('c');
    $item['lease_until'] = $now + max(30, $seconds);
    return $item;
}

function tvs_radar_queue_lease_valid(array $item, string $leaseId): bool
{
    return $leaseId !== ''
        && hash_equals((string)($item['lease_id'] ?? ''), $leaseId)
        && (int)($item['lease_until'] ?? 0) > time();
}
