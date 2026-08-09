<?php
$root='/var/www/html';
$fail=[];$ok=[];
function check($cond,$label){global $fail,$ok; if($cond)$ok[]=$label; else $fail[]=$label;}
function text($p){$v=@file_get_contents($p);return is_string($v)?$v:'';}

$radar=text($root.'/admin/radar-regional.php');
$log=text($root.'/admin/log-editorial.php');
$valid=text($root.'/admin/content-validity.php');
$boletim=text($root.'/admin/boletim-ia.php');
$reporter=text($root.'/admin/reporter-ia.php');
$social=text($root.'/admin/distribuicao-social.php');
$