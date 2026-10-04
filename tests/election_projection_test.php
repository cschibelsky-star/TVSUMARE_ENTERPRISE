<?php
require_once dirname(__DIR__).'/includes/election_projection.php';
function projectionCheck($condition,$message){if(!$condition)throw new RuntimeException($message);}
function fixture(array $parties,int $seats):array{
 $agr=[];$sum=0;
 foreach($parties as $id=>$data){
  [$votes,$nominals]=$data;$candidates=[];$nominalSum=0;
  foreach($nominals as $index=>$v){$nominalSum+=$v;$candidates[]=['n'=>$id.'-'.$index,'vap'=>(string)$v,'dvt'=>'Válido','dt'=>sprintf('%02d/01/1970',$index+1)];}
  $agr[]=['n'=>$id,'par'=>[['tval'=>(string)($votes-$nominalSum),'cand'=>$candidates]]];$sum+=$votes;
 }
 return [['v'=>['vv'=>(string)$sum]],['nv'=>(string)$seats,'agr'=>$agr]];
}
[$r,$c]=fixture(['A'=>[400,[100,90,80,70,60]],'B'=>[300,[100,80,70,50]],'C'=>[180,[80,60,40]],'D'=>[70,[50,20]],'E'=>[50,[50]]],10);
$p=tvs_election_projection($r,$c);
projectionCheck($p['available']&&$p['group_seats']['A']===5&&$p['group_seats']['B']===3&&$p['group_seats']['C']===2,'QP e sobras 80/20');
projectionCheck($p['candidates']['C-1']['phase']==='sobras_80_20','Fase da sobra');
[$r,$c]=fixture(['A'=>[250,[230,15,5]],'B'=>[50,[50]]],3);
$p=tvs_election_projection($r,$c);
projectionCheck($p['available']&&$p['candidates']['A-2']['phase']==='sobras_finais','Sobras finais sem mínimo');
[$r,$c]=fixture(['A'=>[290,[285,3,2]],'B'=>[10,[10]]],3);
$p=tvs_election_projection($r,$c);
projectionCheck($p['available']&&$p['group_seats']['A']===3,'QP conta no divisor mesmo com vaga inicialmente não preenchida');
[$r,$c]=fixture(['A'=>[50,[25,25]],'B'=>[50,[50]]],1);
$p=tvs_election_projection($r,$c);
projectionCheck($p['available']&&$p['candidates']['B-0']['status']==='inside','Média e votos empatados: votação nominal desempata');
[$r,$c]=fixture(['A'=>[100,[50,50]]],1);
$p=tvs_election_projection($r,$c);
projectionCheck($p['available']&&$p['candidates']['A-0']['status']==='inside','Desempate pela idade');
unset($c['agr'][0]['par'][0]['cand'][0]['dt']);
projectionCheck(!tvs_election_projection($r,$c)['available'],'Empate sem idade fica indefinido');
[$r,$c]=fixture(['A'=>[100,[90]]],1);
$c['agr'][0]['par'][0]['cand'][]=['n'=>'invalid','vap'=>'500','dvt'=>'Anulado sub judice','dt'=>'01/01/1970'];
$p=tvs_election_projection($r,$c);
projectionCheck($p['available']&&$p['valid_votes']===100&&$p['candidates']['invalid']['status']==='undefined','Votos sub judice excluídos');
$r['v']['vv']='101';projectionCheck(!tvs_election_projection($r,$c)['available'],'Totais divergentes bloqueiam projeção');
[$r,$c]=fixture(['A'=>[1005,array_fill(0,10,100)]],10);
$p=tvs_election_projection($r,$c);projectionCheck($p['available']&&$p['electoral_quotient']===100,'Meio não arredonda para cima');
echo "ELECTION_PROJECTION_TEST=PASS: QP, duas etapas de sobras, divisor, idade, sub judice e inconsistências\n";

[$r,$c]=fixture(['A'=>[1006,array_fill(0,10,100)]],10);
$p=tvs_election_projection($r,$c);projectionCheck($p['available']&&$p['electoral_quotient']===101,'Acima de meio arredonda para cima');
[$r,$c]=fixture(['A'=>[50,[50]],'B'=>[50,[50]]],1);
projectionCheck(!tvs_election_projection($r,$c)['available'],'Empate completo de médias não inventa vencedor');
