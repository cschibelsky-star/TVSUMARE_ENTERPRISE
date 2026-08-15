<?php
$path='/var/www/html/admin/distribuicao-social.php';
$code=file_get_contents($path);
if($code===false){fwrite(STDERR,"Distribuição Social não encontrada\n");exit(1);}

function ds_patch(&$code,$old,$new,$label,$modernMarker=''){
  $n=substr_count($code,$old);
  if($n===0 && $modernMarker!=='' && strpos($code,$modernMarker)!==false){
    echo "{$label}: fluxo moderno já endure