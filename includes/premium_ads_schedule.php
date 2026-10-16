<?php
/**
 * Advertisement scheduling uses Sao Paulo wall-clock time, consistent with
 * datetime-local inputs in the commercial admin and the PHP application.
 */
function tvs_ad_schedule_parse(string $raw): ?DateTimeImmutable {
    if ($raw === '') return null;
    $timezone = new DateTimeZone('America/Sao_Paulo');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, $timezone);
    if (!$date || $date->format('Y-m-d\TH:i') !== $raw) return null;
    return $date;
}
function tvs_ad_schedule_validate(string $start, string $end): string {
    $startDate = tvs_ad_schedule_parse($start);
    $endDate = tvs_ad_schedule_parse($end);
    if (($start !== '' && $startDate === null) || ($end !== '' && $endDate === null)) {
        return 'Informe datas e horários válidos.';
    }
    if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
        return 'O término deve ser posterior ou igual ao início da campanha.';
    }
    return '';
}
function tvs_ad_display_status(array $ad): string {
    $status = (string)($ad['status'] ?? 'recebido');
    if ($status !== 'publicado') return $status;
    $now = new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo'));
    $start = tvs_ad_schedule_parse((string)($ad['start_at']??''));
    $end = tvs_ad_schedule_parse((string)($ad['end_at']??''));
    $video = tvs_premium_local_media($ad['video']??'', 'video');
    $image = tvs_premium_local_media($ad['banner']??'', 'image');
    $validVideo = $video !== '' && (float)($ad['video_duration']??0)>0 && (float)($ad['video_duration']??0)<=10;
    if (!$validVideo && $image === '') return 'Mídia indisponível — reenviar arquivo';
    if ($start && $start > $now) return 'Agendado — inicia em '.$start->format('d/m/Y H:i');
    if ($end && $end < $now) return 'Encerrado — período finalizado';
    return $validVideo ? 'Em exibição — vídeo' : 'Em exibição — banner';
}
function tvs_ad_schedule_datetime_input(string $raw): string {
    $date = tvs_ad_schedule_parse($raw);
    return $date ? $date->format('Y-m-d\TH:i') : '';
}
