<?php
require_once __DIR__.'/../includes/elections_2026.php';
$spec=tvs_election_specs()[0];
$base=json_decode('{"ele":"6257","t":"1","f":"o","tpabr":"br","cdabr":"br","dv":"s","and":"p","dg":"04/10/2026","hg":"17:12:00","s":{"pst":"12,34"},"carg":[{"cd":"1","agr":[{"par":[{"sg":"TEST","cand":[{"n":"99","nmu":"Teste","vap":"120","pvap":"52,30"}]}]}]}]}',true,512,JSON_THROW_ON_ERROR);
function check($condition){if(!$condition)throw new RuntimeException('Teste eleitoral falhou.');}
$parsed=tvs_election_parse($base,$spec);
check($parsed['sections']===12.34 && $parsed['candidates'][0]['percent']===52.3 && $parsed['candidates'][0]['votes']===120.0);
foreach (['f'=>'s','ele'=>'619','t'=>'2','cdabr'=>'sp','dv'=>'n','and'=>'n','dg'=>'03/10/2026'] as $key=>$value) {
    $copy=$base;$copy[$key]=$value;$rejected=false;
    try {tvs_election_parse($copy,$spec);}catch(Throwable $e){$rejected=true;}check($rejected);
}
$copy=$base;$copy['s']['pst']='101';$rejected=false;
try{tvs_election_parse($copy,$spec);}catch(Throwable $e){$rejected=true;}check($rejected);
echo "Elections 2026: parser, decimals, environment, election, scope, publication and freshness checks passed.\n";

foreach ([6,7] as $code) {
    $spec=tvs_election_specs()[$code===6?3:4];
    $copy=$base; $copy['ele']='6259'; $copy['tpabr']='uf'; $copy['cdabr']='sp'; $copy['carg'][0]['cd']=(string)$code;
    $parsed=tvs_election_parse($copy,$spec); check(count($parsed['candidates'])===1);
    $copy['carg'][0]['cd']='5'; $rejected=false;
    try {tvs_election_parse($copy,$spec);}catch(Throwable $e){$rejected=true;} check($rejected);
}
echo "Deputados SP: federal, estadual e rejeição de cargo incorreto passaram.\n";

check(count(tvs_election_states())===27);
foreach(array_keys(tvs_election_states()) as $uf) {
    $specs=tvs_election_specs($uf); check($specs[0]['uf']==='br'); check($specs[1]['uf']===$uf);
    check($specs[4]['cargo']===($uf==='df'?8:7));
}
$rejected=false; try { tvs_election_specs('../sp'); }catch(InvalidArgumentException $e){$rejected=true;}check($rejected);
echo "UFs: 27 unidades, presidente nacional, cargo distrital e UF inválida passaram.\n";
