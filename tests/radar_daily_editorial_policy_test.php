<?php
require_once dirname(__DIR__).'/admin/radar_daily_editorial_policy.php';
$rows=[];
for($i=1;$i<=7;$i++) $rows[]=['id'=>'s'.$i,'city'=>'Sumaré','sf_score'=>$i*10,'eligible'=>true];
for($i=1;$i<=4;$i++) $rows[]=['id'=>'h'.$i,'city'=>'Hortolândia','sf_score'=>$i*10,'eligible'=>true];
$rows[]=['id'=>'a1','city'=>'Americana','sf_score'=>90,'eligible'=>false];
$selected=tvs_radar_editorial_daily_shortlist($rows);
$expected=['s7','s6','s5','s4','s3','h4','h3'];
if($selected!==$expected){fwrite(STDERR,'FAIL selection '.json_encode($selected)."\n");exit(1);}
$selected=tvs_radar_editorial_daily_shortlist($rows,['Sumaré'=>4,'Hortolândia'=>1]);
if($selected!==['s7','h4']){fwrite(STDERR,'FAIL existing counts '.json_encode($selected)."\n");exit(1);}
echo "PASS: daily city caps, score order and no forced fill\n";
