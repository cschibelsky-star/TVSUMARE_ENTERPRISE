<?php
$path='/var/www/html/admin/reporter-ia.php';
$code=file_get_contents($path);
if($code===false){fwrite(STDERR,"Reporter IA não encontrado\n");exit(1);}
$old="if(\$action==='test_heygen'){ \$styles=rpia_heygen_list_styles(\$cfg); if(!\$styles['ok']) \$err=rpia_friendly_error('test',\$styles['error']??''); else \$msg='Conexão com o provedor de vídeo validada. Estilos disponíveis: '.count(\$styles['data']??[]).'.'; }";
$new="if(\$action==='test_heygen'){ \$styles=rpia_heygen_list_styles(\$cfg); if(!\$styles['ok']) \$err=rpia_friendly_error('test',\$styles['error']??''); else { if(rpia_provider_blocked(\$cfg)){ \$cfg['heygen_send_blocked']='0'; \$cfg['heygen_send_blocked_reason']=''; \$cfg['heygen_send_blocked_released_at']=date('c'); rpia_config_save(\$cfg); } \$msg='Conexão com o provedor de vídeo validada. Estilos disponíveis: '.count(\$styles['data']??[]).'. Bloqueios antigos de crédito foram removidos.'; } }";
$count=substr_count($code,$old);
if($count===0 && strpos($code,'function rpia_heygen_validate_resources(')!==false && strpos($code,"heygen_send_blocked_released_at")!==false){
  echo "HeyGen recovery: validação moderna e liberação segura já presentes; patch legado ignorado.\n";
} elseif($count===1){
  $code=str_replace($old,$new,$code);
} else {
  fwrite(STDERR,"HeyGen recovery: trecho esperado count={$count}; abortando\n");exit(2);
}
if(file_put_contents($path,$code)===false){fwrite(STDERR,"Falha ao gravar Reporter IA\n");exit(3);} echo "HEYGEN_RECOVERY_APPLIED=SIM\n";