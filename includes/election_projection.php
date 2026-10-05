<?php
declare(strict_types=1);

// Modelo determinístico do retrato atual, não previsão dos votos ainda não apurados.
// Res. TSE 23.677/2021, arts. 8–12-A, texto compilado em 2026.
function tvs_election_projection(array $raw, array $cargo): array {
    $unavailable=static fn(string $reason):array=>['available'=>false,'reason'=>$reason,'candidates'=>[]];
    $integer=static function($value):int{
        if(!is_scalar($value)||!preg_match('/^\d+$/D',(string)$value))throw new RuntimeException('Dados numéricos incompletos.');
        return (int)$value;
    };
    try {
        $seats=$integer($cargo['nv']??null);
        $validVotes=$integer($raw['v']['vv']??null);
        if($seats<1||$seats>1000||$validVotes<1)return $unavailable('Dados insuficientes.');
        $qe=intdiv($validVotes,$seats)+((($validVotes%$seats)*2>$seats)?1:0);
        if($qe<1)return $unavailable('Quociente ainda insuficiente.');
        if(isset($cargo['qe'])&&$integer($cargo['qe'])!==$qe)return $unavailable('Quociente divergente do arquivo oficial.');
        $groups=[];$total=0;$result=[];$numbers=[];
        foreach(($cargo['agr']??[]) as $index=>$group){
            $id=(string)($group['n']??$index);
            if(isset($groups[$id]))return $unavailable('Grupo duplicado.');
            $votes=0;$list=[];
            foreach(($group['par']??[]) as $party){
                $legendValidity=(string)($party['dvt']??'');
                if($legendValidity==='Válido (legenda)')$votes+=$integer($party['tval']??null);
                elseif(!str_starts_with($legendValidity,'Anulado')&&!str_starts_with($legendValidity,'Nulo'))return $unavailable('Destinação de legenda não reconhecida.');
                foreach(($party['cand']??[]) as $c){
                    $n=(string)($c['n']??'');
                    if($n===''||isset($numbers[$n]))return $unavailable('Candidatura incompleta ou duplicada.');
                    $numbers[$n]=true;
                    $validity=(string)($c['dvt']??'');
                    if($validity!=='Válido'){
                        if(!str_starts_with($validity,'Anulado')&&!str_starts_with($validity,'Nulo'))return $unavailable('Destinação de votos não reconhecida.');
                        $result[$n]=['status'=>'undefined','reason'=>'Votos não classificados como válidos pelo TSE.'];
                        continue;
                    }
                    $v=$integer($c['vap']??null);$votes+=$v;
                    $birth=DateTimeImmutable::createFromFormat('!d/m/Y',(string)($c['dt']??''));
                    $list[]=['number'=>$n,'votes'=>$v,'birth'=>$birth&&$birth->format('d/m/Y')===($c['dt']??'')?$birth->format('Y-m-d'):null];
                    $result[$n]=['status'=>'outside','reason'=>'Fora das vagas no retrato atual.'];
                }
            }
            usort($list,static fn($a,$b)=>($b['votes']<=>$a['votes'])?:strcmp($a['birth']??'9999',$b['birth']??'9999')?:strcmp($a['number'],$b['number']));
            $groups[$id]=['votes'=>$votes,'qp'=>intdiv($votes,$qe),'extra'=>0,'filled'=>0,'list'=>$list];
            $total+=$votes;
        }
        if($total!==$validVotes)return $unavailable('Totais de legenda e candidaturas não fecham com os votos válidos.');
        $pick=static function(array $g,int $minimum) {
            $eligible=array_values(array_filter($g['list'],static fn($c)=>$c['votes']*100>=$minimum));
            if(!$eligible)return null;
            $first=$eligible[0];
            foreach(array_slice($eligible,1) as $other){
                if($other['votes']!==$first['votes'])break;
                if(!$first['birth']||!$other['birth']||$first['birth']===$other['birth'])throw new RuntimeException('Empate sem desempate suficiente.');
            }
            return $first;
        };
        $assign=static function(array &$g,array $c,string $phase)use(&$result):void{
            $result[$c['number']]=['status'=>'inside','phase'=>$phase];
            $g['filled']++;
            $g['list']=array_values(array_filter($g['list'],static fn($x)=>$x['number']!==$c['number']));
        };
        $filled=0;
        foreach($groups as &$g){
            for($i=0;$i<$g['qp'];$i++){
                $candidate=$pick($g,10*$qe);
                if(!$candidate)break;
                $assign($g,$candidate,'quociente_partidario');$filled++;
            }
        }
        unset($g);
        if($filled>$seats)return $unavailable('Distribuição inicial excede as vagas.');
        foreach([true,false] as $restricted){
            while($filled<$seats){
                $best=null;$bestCandidate=null;
                foreach($groups as $id=>$g){
                    if($restricted&&$g['votes']*100<80*$qe)continue;
                    $candidate=$pick($g,$restricted?20*$qe:0);
                    if(!$candidate)continue;
                    if($best===null){$best=$id;$bestCandidate=$candidate;continue;}
                    $a=$g['votes']*($groups[$best]['qp']+$groups[$best]['extra']+1);
                    $b=$groups[$best]['votes']*($g['qp']+$g['extra']+1);
                    $comparison=($a<=>$b)?:($g['votes']<=>$groups[$best]['votes'])?:($candidate['votes']<=>$bestCandidate['votes']);
                    if($comparison===0)return $unavailable('Empate de médias sem desempate suficiente.');
                    if($comparison>0){$best=$id;$bestCandidate=$candidate;}
                }
                if($best===null)break;
                $assign($groups[$best],$bestCandidate,$restricted?'sobras_80_20':'sobras_finais');
                $groups[$best]['extra']++;$filled++;
            }
        }
        if($filled!==$seats)return $unavailable('Candidaturas válidas insuficientes para preencher todas as vagas.');
        $groupSeats=[];foreach($groups as $id=>$g)$groupSeats[$id]=$g['filled'];
        return ['available'=>true,'method'=>'tvs-proporcional-2026-v1','electoral_quotient'=>$qe,'valid_votes'=>$validVotes,'seats'=>$seats,'group_seats'=>$groupSeats,'candidates'=>$result];
    }catch(Throwable $error){
        return $unavailable($error->getMessage()==='Empate sem desempate suficiente.'?$error->getMessage():'Dados insuficientes para projeção.');
    }
}
