<?php
/**
 * Limites da seleção editorial diária. Não altera coleta, cron nem publicação.
 * O mínimo regional é uma meta, não uma obrigação de preencher vagas.
 */
function tvs_radar_editorial_city_limits(): array {
    return ['Sumaré'=>5,'Hortolândia'=>2,'Paulínia'=>2,'Nova Odessa'=>2,'Americana'=>2,'Campinas'=>2];
}
function tvs_radar_editorial_daily_shortlist(array $candidates, array $alreadySelected=[]): array {
    $limits=tvs_radar_editorial_city_limits();
    $groups=[];
    foreach($candidates as $item){
        if(!is_array($item) || empty($item['eligible'])) continue;
        $city=(string)($item['city']??'');
        $id=(string)($item['id']??'');
        if($id==='' || !isset($limits[$city])) continue;
        $groups[$city][$id]=$item;
    }
    $result=[];
    foreach($limits as $city=>$cap){
        $available=max(0,$cap-max(0,(int)($alreadySelected[$city]??0)));
        $items=array_values($groups[$city]??[]);
        usort($items,static function($a,$b){
            $score=(int)($b['sf_score']??0)<=>(int)($a['sf_score']??0);
            if($score!==0) return $score;
            return strcmp((string)($b['source_date']??''),(string)($a['source_date']??''));
        });
        foreach(array_slice($items,0,$available) as $item) $result[]=(string)$item['id'];
    }
    return $result;
}
