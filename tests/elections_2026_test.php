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

$copy=$base;$copy['ele']='6259';$copy['tpabr']='uf';$copy['cdabr']='sp';
$copy['carg']=[['cd'=>'6','qe'=>'242666','nv'=>'70','agr'=>[
 ['n'=>'fed1','com'=>'PT/PC do B/PV','vag'=>'2','par'=>[
 ['sg'=>'PT','cand'=>[['n'=>'1300','nmu'=>'Primeiro','vap'=>'100','pvap'=>'1'],['n'=>'1301','nmu'=>'Empate','vap'=>'90','pvap'=>'1']]],
 ['sg'=>'PV','cand'=>[['n'=>'4300','nmu'=>'Segundo','vap'=>'90','pvap'=>'1']]]
 ]],
 ['n'=>'outro','com'=>'OUTRO','vag'=>'0','par'=>[['sg'=>'O','cand'=>[['n'=>'9900','nmu'=>'Outro','vap'=>'500','pvap'=>'1']]]]]
]]];
$parsed=tvs_election_parse($copy,tvs_election_specs()[3]);
check($parsed['electoral_quotient']===242666.0 && $parsed['seats']===70 && $parsed['proportional']);
$byNumber=array_column($parsed['candidates'],null,'number');
check($byNumber[1300]['group_rank']===1 && $byNumber[1300]['group_seats']===2);
check($byNumber[4300]['group_rank']===2 && $byNumber[4300]['group_tied']);
check($byNumber[9900]['group_rank']===1 && $byNumber[9900]['group_seats']===0);
check($byNumber[1300]['status']==='');
echo "Indicadores: quociente oficial, federação, posição por grupo, empate e ausência de situação passaram.\n";
