<?php
$path='/var/www/html/admin/distribuicao-social.php';
$code=file_get_contents($path);
if($code===false){fwrite(STDERR,"Distribuição Social não encontrada\n");exit(1);}
function ds_patch(&$code,$old,$new,$label){$n=substr_count($code,$old);if($n!==1){fwrite(STDERR,"{$label}: trecho esperado count={$n}; abortando\n");exit(2);} $code=str_replace($old,$new,$code);}
ds_patch($code,
"function ds_clean(\$v){ return trim(preg_replace('/\\s+/u',' ',strip_tags((string)\$v))); }",
"function ds_clean(\$v){ return trim(preg_replace('/\\s+/u',' ',strip_tags((string)\$v))); }\nfunction ds_status_label(\$s){ return ['aguardando_integracao'=>'Na fila','na_fila'=>'Na fila','processando'=>'Publicando','publicado'=>'Publicado','falhou'=>'Falhou','cancelado'=>'Cancelado'][strtolower((string)\$s)]??ucfirst(str_replace('_',' ',(string)\$s)); }\nfunction ds_queue_active(\$q){ return in_array(strtolower((string)(\$q['status']??'')),['aguardando_integracao','na_fila','processando'],true); }",
'Social helpers');
ds_patch($code,
"if(\$selected && ds_ready(\$selected)){\n      \$caption=trim((string)(\$_POST['caption']??'')); \$hashtags=trim((string)(\$_POST['hashtags']??'')); \$engine='manual';",
"if(\$selected && ds_ready(\$selected)){\n      \$caption=trim((string)(\$_POST['caption']??'')); \$hashtags=trim((string)(\$_POST['hashtags']??'')); \$engine=(\$caption!=='' && \$hashtags!=='')?'revisado':'automatico';",
'Social reviewed copy');
ds_patch($code,
"\$duplicate=false; foreach(\$queue as \$q){ if((\$q['video_id']??'')===\$videoId && in_array((\$q['status']??''),['aguardando_integracao','na_fila','processando'],true)){ \$duplicate=true; break; } }",
"\$duplicate=false; foreach(\$queue as \$q){ if((\$q['video_id']??'')===\$videoId && ds_queue_active(\$q)){ \$duplicate=true; break; } }",
'Social idempotency');
ds_patch($code,
'$ready=array_values(array_filter($videos,\'ds_ready\'));',
'$ready=array_values(array_filter($videos,function($v) use ($queue){ if(!ds_ready($v)) return false; foreach($queue as $q){ if(($q[\'video_id\']??\'\')===($v[\'id\']??\'\') && ds_queue_active($q)) return false; } return true; }));',
'Social ready filtering');
ds_patch($code,
"<div class=\"box\" style=\"margin-top:14px\"><h2>Novo envio</h2><?php if(!\$ready): ?><p>Nenhum vídeo do Repórter IA está pronto no momento.</p><?php else: ?><form method=\"post\" class=\"form\"><?=tvs_csrf_field()?>",
"<div class=\"box\" style=\"margin-top:14px\"><h2>Preparar publicação</h2><?php if(!\$ready): ?><p>Nenhum vídeo pronto aguardando preparação. Vídeos já colocados na fila não aparecem novamente aqui.</p><?php else: ?><form method=\"post\" class=\"form\"><?=tvs_csrf_field()?>",
'Social heading');
ds_patch($code,
"<?=ds_h(\$q['status']??'')?>",
"<strong><?=ds_h(ds_status_label(\$q['status']??''))?></strong><?php if(!empty(\$q['network_status'])): ?><br><small><?php foreach(\$q['network_status'] as \$net=>\$st): ?><?=ds_h(ucfirst(\$net))?>: <?=ds_h(ds_status_label(\$st))?> <?php endforeach; ?></small><?php endif; ?>",
'Social status UI');
if(file_put_contents($path,$code)===false){fwrite(STDERR,"Falha ao gravar Distribuição Social\n");exit(3);} echo "SOCIAL_DISTRIBUTION_HARDENING_APPLIED=SIM\n";
