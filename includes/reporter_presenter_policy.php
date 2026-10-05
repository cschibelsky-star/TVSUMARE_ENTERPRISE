<?php
/**
 * Resolve presenter-related HeyGen IDs according to TV Sumare editorial policy.
 *
 * Primary Reporter IA news always uses the fixed Reporter configuration.
 * Boletim jobs may override those IDs with their catalog/job selection.
 */
function tvs_reporter_presenter_payload(array $job, array $cfg): array {
    $isBoletim = !empty($job['boletim']);
    $payload = [];

    foreach ([
        'avatar_id' => 'heygen_avatar_id',
        'voice_id' => 'heygen_voice_id',
        'style_id' => 'heygen_style_id',
        'brand_kit_id' => 'heygen_brand_kit_id',
    ] as $api => $local) {
        $jobValue = trim((string)($job[$local] ?? ''));
        $cfgValue = trim((string)($cfg[$local] ?? ''));
        $value = $isBoletim && $jobValue !== '' ? $jobValue : $cfgValue;

        if ($value !== '') {
            $payload[$api] = $value;
        }
    }

    return $payload;
}
