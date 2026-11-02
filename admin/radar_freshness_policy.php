<?php
/**
 * Política conservadora de atualidade: apenas classifica pautas.
 * Não publica, apaga ou altera dados persistidos.
 */
function tvs_radar_freshness_classify(array $item, ?int $now=null): array {
    $now=$now??time();
    $deadline=trim((string)($item['deadline_at']??$item['registration_end_at']??$item['event_end_at']??''));
    if($deadline!==''){
        $expiry=strtotime($deadline);
        if($expiry===false) return ['eligible'=>false,'reason'=>'invalid_deadline'];
        if($expiry<$now) return ['eligible'=>false,'reason'=>'expired_deadline'];
    }
    $date=trim((string)($item['source_date']??$item['published_at']??''));
    if($date==='') return ['eligible'=>false,'reason'=>'missing_source_date'];
    $published=strtotime($date);
    if($published===false || $published>$now+300) return ['eligible'=>false,'reason'=>'invalid_source_date'];
    $age=$now-$published;
    if($age>172800 && empty($item['verified_new_fact'])) return ['eligible'=>false,'reason'=>'older_than_48h'];
    if($age>86400 && empty($item['current_relevance_verified'])) return ['eligible'=>false,'reason'=>'relevance_review_required'];
    return ['eligible'=>true,'reason'=>$age<=86400?'recent_24h':'verified_current'];
}
