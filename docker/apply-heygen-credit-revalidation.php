<?php
$path='/var/www/html/admin/reporter-ia.php';
$code=file_get_contents($path);
if($code===false){fwrite(STDERR,"Reporter IA não encontrado\n");exit(1);}
function hcr_replace(&$code,$old,$new,$label){$n=substr_count($code,$old);if($n!==1){fwrite(STDERR,"{$label}: trecho esperado count={$n}; abortando\n");exit(2);} $code=str_replace($old,$new,$code);}

$marker="function rpia_local_script(\$news){";
$helper=<<<'PHP'
function rpia_heygen_credit_status($cfg){
  $key=rpia_heygen_key($cfg); if($key==='') return ['ok'=>false,'error'=>'Chave HeyGen não configurada.'];
  $r=rpia_heygen_request('GET','/v3/users/me',$key,null,20);
  if(empty($r['ok'])) return ['ok'=>false,'error'=>$r['error']??'Falha ao consultar créditos HeyGen.','http'=>$r['http']??0];
  $data=$r['data']['data']??($r['data']??[]);
  $included=$data['included_credits']??null; $remaining=$data['remaining_credits']??null;
  return ['ok'=>true,'included_credits'=>$included,'remaining_credits'=>$remaining,'has_credit'=>(is_numeric($remaining)?((float)$remaining>0):null)];
}

PHP;
if(strpos($code,'function rpia_heygen_credit_status(')===false){$pos=strpos($code,$marker);if($pos===false){fwrite(STDERR,"HeyGen credit helper marker ausente\n");exit(3);} $code=substr($code,0,$pos).$helper.substr($code,$pos);}

$old="if(\$action==='test_heygen'){ \$styles=rpia_heygen_list_styles(\$cfg); if(!\$styles['ok']) \$err=rpia_friendly_error('test',\$styles['error']??''); else { if(rpia_provider_blocked(\$cfg)){ \$cfg['heygen_send_blocked']='0'; \$cfg['heygen_send_blocked_reason']=''; \$cfg['heygen_send_blocked_released_at']=date('c'); rpia_config_save(\$cfg); } \$msg='Conexão com o provedor de vídeo validada. Estilos disponíveis: '.count(\$styles['data']??[]).'. Bloqueios antigos de crédito foram removidos.'; } }";
$new="if(\$action==='test_heygen'){ \$styles=rpia_heygen_list_styles(\$cfg); \$credits=rpia_heygen_credit_status(\$cfg); if(!\$styles['ok']) \$err=rpia_friendly_error('test',\$styles['error']??''); elseif(!\$credits['ok']) \$err='Conexão HeyGen validada, mas não foi possível confirmar o saldo da API. O bloqueio de envio foi mantido por segurança.'; elseif(\$credits['has_credit']===false){ \$cfg['heygen_send_blocked']='1'; \$cfg['heygen_send_blocked_reason']='credits'; \$cfg['heygen_credit_checked_at']=date('c'); rpia_config_save(\$cfg); \$err=rpia_friendly_error('credits','remaining_credits=0'); } else { if(rpia_provider_blocked(\$cfg)){ \$cfg['heygen_send_blocked']='0'; \$cfg['heygen_send_blocked_reason']=''; \$cfg['heygen_send_blocked_released_at']=date('c'); } \$cfg['heygen_credit_checked_at']=date('c'); if(is_numeric(\$credits['remaining_credits'])) \$cfg['heygen_remaining_credits']=(float)\$credits['remaining_credits']; rpia_config_save(\$cfg); \$creditText=is_numeric(\$credits['remaining_credits'])?' Créditos restantes: '.number_format((float)\$credits['remaining_credits'],2,',','.').'.':''; \$msg='Conexão com o provedor de vídeo validada. Estilos disponíveis: '.count(\$styles['data']??[]).'.'.\$creditText.' Envio liberado.'; } }";
hcr_replace($code,$old,$new,'HeyGen credit-aware revalidation');

$code=str_replace('>Testar conexão HeyGen<','>Revalidar saldo e conexão HeyGen<',$code);
$code=str_replace('<strong>Repórter IA indisponível temporariamente — franquia/API sem saldo.</strong><br>Nenhum novo vídeo será enviado até a regularização. Os roteiros e vídeos existentes permanecem preservados.','<strong>Envio HeyGen bloqueado por um erro anterior de crédito.</strong><br>Os roteiros e vídeos existentes permanecem preservados. Use “Revalidar saldo e conexão HeyGen” acima para consultar o saldo atual e remover o bloqueio automaticamente quando houver crédito.',$code);

if(file_put_contents($path,$code)===false){fwrite(STDERR,"Falha ao gravar Reporter IA\n");exit(4);} echo "HEYGEN_CREDIT_REVALIDATION_APPLIED=SIM\n";
