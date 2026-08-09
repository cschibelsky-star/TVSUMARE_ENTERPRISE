<?php
function patch_once($path,$old,$new,$label){
  $code=file_get_contents($path);
  if($code===false){fwrite(STDERR,"{$label}: arquivo não encontrado\n");exit(1);}
  $count=substr_count($code,$old);
  if($count!==1){fwrite(STDERR,"{$label}: trecho esperado count={$count}; abortando\n");exit(2);}
  $code=str_replace($old,$new,$code);
  if(file_put_contents($path,$code)===false){fwrite(STDERR,"{$label}: falha ao gravar\n");exit(3);}
}

$patches=[
['/var/www/html/admin/editor-ia.php',<<<'OLD'
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  $action=$_POST['action']??'search_generate';
OLD,<<<'NEW'
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $action=$_POST['action']??'search_generate';
NEW,'Editor IA CSRF'],
['/var/www/html/admin/tvplay.php',<<<'OLD'
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
OLD,<<<'NEW'
if($_SERVER['REQUEST_METHOD']==='POST'){
  tvs_verify_csrf();
  $action=$_POST['action']??'';
NEW,'TV Play IA CSRF'],
['/var/www/html/admin/fontes.php',"if(\$_SERVER['REQUEST_METHOD']==='POST'){ \$action=\$_POST['action']??'';","if(\$_SERVER['REQUEST_METHOD']==='POST'){ tvs_verify_csrf(); \$action=\$_POST['action']??'';",'Fontes CSRF'],
['/var/www/html/admin/aovivo.php',<<<'OLD'
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  $settings['live_title']
OLD,<<<'NEW'
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $settings['live_title']
NEW,'Ao Vivo CSRF'],
['/var/www/html/admin/guia-comercial.php',"if(\$_SERVER['REQUEST_METHOD']==='POST'){ \$action=\$_POST['action']??'';","if(\$_SERVER['REQUEST_METHOD']==='POST'){ tvs_verify_csrf(); \$action=\$_POST['action']??'';",'Guia Comercial CSRF'],
['/var/www/html/admin/area-comercial.php',"if(\$_SERVER['REQUEST_METHOD']==='POST'){ \$id=\$_POST['id']??'';","if(\$_SERVER['REQUEST_METHOD']==='POST'){ tvs_verify_csrf(); \$id=\$_POST['id']??'';",'Area Comercial CSRF'],
['/var/www/html/admin/monetizacao.php',<<<'OLD'
if($_SERVER['REQUEST_METHOD']==='POST'){
  foreach($defaults
OLD,<<<'NEW'
if($_SERVER['REQUEST_METHOD']==='POST'){
  tvs_verify_csrf();
  foreach($defaults
NEW,'Monetizacao CSRF']
];
foreach($patches as $p) patch_once($p[0],$p[1],$p[2],$p[3]);
echo "ADMIN_MODULE_HARDENING_APPLIED=SIM\n";
