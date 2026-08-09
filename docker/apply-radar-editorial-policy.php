<?php
$path='/var/www/html/admin/radar-regional.php';
$code=file_get_contents($path);
if($code===false){fwrite(STDERR,"Radar não encontrado\n");exit(1);}

$replacements=[];
$replacements[]=[
<<<'OLD'
function tvs_radar_status_from_score($score,$sensitive=false){
  if($sensitive) return ['review_level'=>'revisao_obrigatoria','editorial_status'=>'Revisão obrigatória'];
  if($score>=85) return ['review_level'=>'normal','editorial_status'=>'Prioridade máxima'];
  if