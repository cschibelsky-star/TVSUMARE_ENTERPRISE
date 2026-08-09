<?php
function patch_once($path,$old,$new,$label){
  $code=file_get_contents($path);
  if($code===false){fwrite(STDERR,"{$label}: arquivo não encontrado\n");exit(1);}
  $count=substr_count($code,$old);
  if($count!==1){fwrite(STDERR,"{$label}: trecho esperado count={$count}; abortando\n");exit(2);}
  $code=str_replace($old,$new,$code);
  if(file_put_contents($path,$code)===false){fwrite(STDERR,"{$label}: falha ao gravar\n");exit(3);}
}

patch_once(
  '/var/www/html/admin/editor-ia.php',
  "if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){\n  $action=$_POST['action']??'search_generate';",
  "if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){\n  tvs_verify_csrf();\n  $action=$_POST['action']??'search_generate';",
  'Editor IA CSRF'
);

patch_once(
  '/var/www/html/admin/tvplay.php',
  "if($_SERVER['REQUEST_METHOD']==='POST'){\n  $action=$_POST['action']??'';",
  "if($_SERVER['REQUEST_METHOD']==='POST'){\n  tvs_verify_csrf();\n  $action=$_POST['action']??'';",
  'TV Play IA CSRF'
);

echo "ADMIN_MODULE_HARDENING_APPLIED=SIM\n";
