<?php
declare(strict_types=1);

function tvs_election_specs(): array {
    return [
        ['key'=>'presidente','title'=>'Presidente — Brasil','uf'=>'br','cargo'=>1,'election'=>6257],
        ['key'=>'governador','title'=>'Governador — São Paulo','uf'=>'sp','cargo'=>3,'election'=>6259],
        ['key'=>'senador','title'=>'Senador — São Paulo','uf'=>'sp','cargo'=>5,'election'=>6259],
    ];
}
function tvs_election_number($value): float {
    if (!is_scalar($value) || !preg_match('/^\d+(?:[.,]\d+)?$/D', (string)$value)) {
        throw new RuntimeException('Número inválido no arquivo oficial.');
    }
    return (float)str_replace(',', '.', (string)$value);
}
function tvs_election_parse(array $raw, array $spec): array {
    if (($raw['f']??'') !== 'o' || (int)($raw['ele']??0) !== $spec['election']
        || (string)($raw['t']??'') !== '1' || ($raw['tpabr']??'') !== ($spec['uf']==='br'?'br':'uf')
        || strtolower((string)($raw['cdabr']??'')) !== $spec['uf']) {
        throw new RuntimeException('Arquivo de outra eleição, abrangência ou ambiente.');
    }
    if (($raw['and']??'n') === 'n') throw new RuntimeException('Totalização ainda não iniciada.');
    if (($raw['dv']??'') !== 's') throw new RuntimeException('Divulgação ainda indisponível.');
    $cargo = null;
    foreach (($raw['carg']??[]) as $item) if ((int)($item['cd']??0)===$spec['cargo']) $cargo=$item;
    if (!$cargo || !isset($raw['s']['pst'])) throw new RuntimeException('Estrutura oficial incompleta.');
    $sections=tvs_election_number($raw['s']['pst']);
    if ($sections>100) throw new RuntimeException('Percentual de seções inválido.');
    $timestamp=DateTimeImmutable::createFromFormat('!d/m/Y H:i:s', ($raw['dg']??'').' '.($raw['hg']??''), new DateTimeZone('America/Sao_Paulo'));
    if (!$timestamp || $timestamp->format('d/m/Y')!=='04/10/2026') throw new RuntimeException('Data oficial inesperada.');
    $candidates=[];
    foreach (($cargo['agr']??[]) as $group) foreach (($group['par']??[]) as $party) foreach (($party['cand']??[]) as $candidate) {
        if (!isset($candidate['nmu'],$candidate['n'],$candidate['vap'],$candidate['pvap'])) throw new RuntimeException('Candidatura incompleta.');
        $percent=tvs_election_number($candidate['pvap']);
        if ($percent>100) throw new RuntimeException('Percentual de candidatura inválido.');
        $candidates[]=['name'=>(string)$candidate['nmu'],'number'=>(string)$candidate['n'],'party'=>(string)($party['sg']??''),'votes'=>tvs_election_number($candidate['vap']),'percent'=>$percent,'status'=>(string)($candidate['st']??'')];
    }
    if (!$candidates) throw new RuntimeException('Candidaturas ainda indisponíveis.');
    usort($candidates,fn($a,$b)=>($b['votes']<=>$a['votes']) ?: strcmp($a['number'],$b['number']));
    return ['title'=>$spec['title'],'candidates'=>$candidates,'sections'=>$sections,'updated_at'=>$timestamp->format(DATE_ATOM),'final'=>($raw['tf']??'')==='s','source'=>'Tribunal Superior Eleitoral'];
}
function tvs_election_fetch(string $url): array {
    if (!str_starts_with($url,'https://resultados.tse.jus.br/oficial/')) throw new RuntimeException('Fonte não permitida.');
    $context=stream_context_create(['http'=>['timeout'=>5,'follow_location'=>0,'ignore_errors'=>true,'header'=>"Accept: application/json\r\nUser-Agent: TVSumare-Eleicoes/1.0\r\n"],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $body=@file_get_contents($url,false,$context,0,2097153);
    if (!is_string($body) || strlen($body)>2097152 || !preg_match('/^HTTP\/\S+ 200\b/', $http_response_header[0]??'')) throw new RuntimeException('Fonte oficial temporariamente indisponível.');
    $decoded=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Resposta oficial inválida.');
    return $decoded;
}
function tvs_election_snapshot(): array {
    $directory=__DIR__.'/../data/elections-2026';
    if (!is_dir($directory) && !@mkdir($directory,0750,true)) throw new RuntimeException('Cache indisponível.');
    $results=[];
    foreach (tvs_election_specs() as $spec) {
        $path=$directory.'/'.$spec['key'].'.json';
        $read=static function() use ($path) { $raw=@file_get_contents($path); return is_string($raw)?(json_decode($raw,true)?:[]):[]; };
        $cache=$read();
        $lock=@fopen($directory.'/'.$spec['key'].'.lock','c');
        if ($lock && flock($lock,LOCK_EX|LOCK_NB)) {
            $cache=$read();
            if (time()-(int)($cache['checked_at']??0)>=30) {
                $cache['checked_at']=time();
                try {
                    $url=sprintf('https://resultados.tse.jus.br/oficial/ele2026/%d/dados/%s/%s-c%04d-e%06d-u.json',$spec['election'],$spec['uf'],$spec['uf'],$spec['cargo'],$spec['election']);
                    $parsed=tvs_election_parse(tvs_election_fetch($url),$spec);
                    if (isset($cache['data']['updated_at']) && strtotime($parsed['updated_at'])<strtotime($cache['data']['updated_at'])) throw new RuntimeException('Arquivo anterior ao cache.');
                    $cache['data']=$parsed; $cache['fetched_at']=time(); $cache['error']=false;
                } catch (Throwable $error) { $cache['error']=true; }
                $temporary=tempnam($directory,'cache-');
                if ($temporary!==false) {
                    if (file_put_contents($temporary,json_encode($cache,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))!==false) @rename($temporary,$path);
                    if (is_file($temporary)) @unlink($temporary);
                }
            }
            flock($lock,LOCK_UN);
        }
        if ($lock) fclose($lock);
        $entry=$cache['data']??['title'=>$spec['title'],'candidates'=>[],'sections'=>null,'updated_at'=>null,'final'=>false];
        $entry['unavailable']=empty($cache['data']);
        $entry['stale']=!empty($cache['error']) || time()-(int)($cache['fetched_at']??0)>120
            || (!empty($entry['updated_at']) && !$entry['final'] && time()-strtotime($entry['updated_at'])>180);
        $results[]=$entry;
    }
    return ['races'=>$results,'refresh_seconds'=>30];
}
