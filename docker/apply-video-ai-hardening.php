<?php
function patch_one($path,$old,$new,$label){
  $code=file_get_contents($path);
  if($code===false){fwrite(STDERR,"{$label}: arquivo não encontrado\n");exit(1);}
  if(strpos($label,'Boletim ')===0 && strpos($code,'function bia_create_job(')!==false){
    echo "{$label}: fluxo moderno do Boletim já presente; patch legado ignorado.\n";
    return;
  }
  $count=substr_count($code,$old);
  if($count===0 && $label==='Reporter send' && strpos($code,'elseif(rpia_provider_blocked($cfg))')!==false){
    echo "{$label}: fluxo moderno já endurecido; patch legado ignorado.\n";
    return;
  }
  if($count!==1){fwrite(STDERR,"{$label}: trecho esperado count={$count}; abortando\n");exit(2);}
  $code=str_replace($old,$new,$code);
  if(file_put_contents($path,$code)===false){fwrite(STDERR,"{$label}: falha ao gravar\n");exit(3);}
}

$reporter='/var/www/html/admin/reporter-ia.php';
patch_one($reporter,
"function rpia_new_token(){ return bin2hex(random_bytes(20)); }\n",
"function rpia_new_token(){ return bin2hex(random_bytes(20)); }\nfunction rpia_log_technical_error(\$context,\$detail){ \$p=dirname(__DIR__).'/logs/reporter-ia-errors.log'; if(!is_dir(dirname(\$p))) @mkdir(dirname(\$p),0775,true); \$line='['.date('c').'] '.preg_replace('/[^a-z0-9_.-]+/i','_', (string)\$context).' '.str_replace([\"\\r\",\"\\n\"],' ',(string)\$detail).PHP_EOL; @file_put_contents(\$p,\$line,FILE_APPEND|LOCK_EX); }\nfunction rpia_friendly_error(\$context,\$detail=''){ rpia_log_technical_error(\$context,\$detail); \$map=['test'=>'Não foi possível validar a conexão com o provedor de vídeo agora. Tente novamente em alguns instantes.','send'=>'Não foi possível enviar este vídeo para processamento agora. O roteiro foi preservado e pode ser reenviado.','check'=>'Não foi possível atualizar o processamento agora. O estado anterior foi preservado.','failed'=>'O provedor não conseguiu concluir este vídeo. Os detalhes técnicos foram registrados para diagnóstico.']; return \$map[\$context]??'Não foi possível concluir esta operação agora. Os detalhes técnicos foram registrados.'; }\nfunction rpia_status_label(\$status){ \$s=(string)\$status; \$map=['roteiro_pronto'=>'Aguardando envio','heygen_agente_processando'=>'Processando','heygen_falhou'=>'Falhou','video_pronto'=>'Pronto','publicado'=>'Publicado']; return \$map[\$s]??(\$s!==''?ucfirst(str_replace('_',' ',\$s)):'Aguardando'); }\n",
'Reporter helpers');
patch_one($reporter,
"if(\$action==='test_heygen'){ \$styles=rpia_heygen_list_styles(\$cfg); if(!\$styles['ok']) \$err=\$styles['error']; else \$msg='Conexão HeyGen OK. Estilos disponíveis: '.count(\$styles['data']??[]).'.'; }",
"if(\$action==='test_heygen'){ \$styles=rpia_heygen_list_styles(\$cfg); if(!\$styles['ok']) \$err=rpia_friendly_error('test',\$styles['error']??''); else \$msg='Conexão com o provedor de vídeo validada. Estilos disponíveis: '.count(\$styles['data']??[]).'.'; }",
'Reporter test');
patch_one($reporter,
"if(\$action==='send_heygen'){ \$idx=null; [\$job,\$jobs]=rpia_find_videojob(\$_POST['job_id']??'',\$idx); if(!\$job) \$err='Roteiro não encontrado.'; else { \$r=rpia_heygen_create(\$job,\$cfg); if(!\$r['ok']) \$err=\$r['error']; else {",
"if(\$action==='send_heygen'){ \$idx=null; [\$job,\$jobs]=rpia_find_videojob(\$_POST['job_id']??'',\$idx); if(!\$job) \$err='Roteiro não encontrado.'; else { \$r=rpia_heygen_create(\$job,\$cfg); if(!\$r['ok']) { \$jobs[\$idx]['status']='heygen_falhou'; \$jobs[\$idx]['technical_error']=substr((string)(\$r['error']??''),0,1200); \$jobs[\$idx]['updated_at']=date('c'); rpia_write('videos_ia.json',\$jobs); \$err=rpia_friendly_error('send',\$r['error']??''); } else {",
'Reporter send');
patch_one($reporter,
"if(\$action==='check_heygen'){ \$idx=null; [\$job,\$jobs]=rpia_find_videojob(\$_POST['job_id']??'',\$idx); if(!\$job) \$err='Vídeo não encontrado.'; else { \$r=rpia_heygen_status(\$job,\$cfg); if(!\$r['ok']) \$err=\$r['error']; else {",
"if(\$action==='check_heygen'){ \$idx=null; [\$job,\$jobs]=rpia_find_videojob(\$_POST['job_id']??'',\$idx); if(!\$job) \$err='Vídeo não encontrado.'; else { \$r=rpia_heygen_status(\$job,\$cfg); if(!\$r['ok']) { \$jobs[\$idx]['technical_error']=substr((string)(\$r['error']??''),0,1200); \$jobs[\$idx]['updated_at']=date('c'); rpia_write('videos_ia.json',\$jobs); \$err=rpia_friendly_error('check',\$r['error']??''); } else {",
'Reporter check');
patch_one($reporter,
"} elseif((\$r['video_status']??'')==='failed'){ \$jobs[\$idx]['status']='heygen_falhou'; \$jobs[\$idx]['heygen_failure']=\$r['failure_message']??(\$r['failure_code']??'Falha na geração.'); } else \$jobs[\$idx]['status']='heygen_agente_processando';",
"} elseif((\$r['video_status']??'')==='failed'){ \$jobs[\$idx]['status']='heygen_falhou'; \$jobs[\$idx]['heygen_failure']=rpia_friendly_error('failed',\$r['failure_message']??(\$r['failure_code']??'Falha na geração.')); \$jobs[\$idx]['technical_error']=substr((string)(\$r['failure_message']??(\$r['failure_code']??'')),0,1200); } else \$jobs[\$idx]['status']='heygen_agente_processando';",
'Reporter failed');
patch_one($reporter,
"<?=rpia_h(\$j['status']??'roteiro')?>",
"<?=rpia_h(rpia_status_label(\$j['status']??'roteiro_pronto'))?>",
'Reporter status label');

$boletim='/var/www/html/admin/boletim-ia.php';
patch_one($boletim,
"function bia_find_news(\$rows,\$id){ foreach(\$rows as \$r){ if((string)(\$r['id']??'')===(string)\$id) return \$r; } return null; }\n",
"function bia_find_news(\$rows,\$id){ foreach(\$rows as \$r){ if((string)(\$r['id']??'')===(string)\$id) return \$r; } return null; }\nfunction bia_recent_news_ids(\$jobs,\$limit=20){ \$ids=[]; foreach(\$jobs as \$j){ if(empty(\$j['boletim'])) continue; \$id=(string)(\$j['news_id']??''); if(\$id!=='' && !in_array(\$id,\$ids,true)) \$ids[]=\$id; if(count(\$ids)>=\$limit) break; } return \$ids; }\nfunction bia_pick_distinct(\$news,\$jobs,\$quantity,\$preferredId=''){ \$quantity=max(1,min(10,(int)\$quantity)); \$recent=bia_recent_news_ids(\$jobs,30); \$picked=[]; if(\$preferredId!==''){ \$p=bia_find_news(\$news,\$preferredId); if(\$p) \$picked[]=\$p; } foreach(\$news as \$n){ if(count(\$picked)>=\$quantity) break; \$id=(string)(\$n['id']??''); if(\$id==='' || in_array(\$id,array_map(fn(\$x)=>(string)(\$x['id']??''),\$picked),true)) continue; if(in_array(\$id,\$recent,true) && count(\$news)>\$quantity) continue; \$picked[]=\$n; } if(count(\$picked)<\$quantity){ foreach(\$news as \$n){ if(count(\$picked)>=\$quantity) break; \$id=(string)(\$n['id']??''); if(\$id!=='' && !in_array(\$id,array_map(fn(\$x)=>(string)(\$x['id']??''),\$picked),true)) \$picked[]=\$n; } } return \$picked; }\n",
'Boletim helpers');
patch_one($boletim,
"\$newsId=trim((string)(\$_POST['news_id']??'')); \$format=trim((string)(\$_POST['format']??'noticia_1_minuto')); \$selected=bia_find_news(\$news,\$newsId);\n  if(!\$selected){ \$err='Selecione uma matéria publicada.'; }\n  else {\n    \$meta=bia_format_meta(\$format); \$editorial=bia_openai_editorial(\$selected,\$format,\$cfg); \$presenter=\$editorial['presenter'];",
"\$newsId=trim((string)(\$_POST['news_id']??'')); \$format=trim((string)(\$_POST['format']??'noticia_1_minuto')); \$quantity=max(1,min(10,(int)(\$_POST['quantity']??1))); \$selectedList=bia_pick_distinct(\$news,\$jobs,\$quantity,\$newsId);\n  if(!\$selectedList){ \$err='Não há matérias publicadas disponíveis para o boletim.'; }\n  else {\n    \$created=0; \$themes=[]; foreach(\$selectedList as \$selected){\n    \$meta=bia_format_meta(\$format); \$editorial=bia_openai_editorial(\$selected,\$format,\$cfg); \$presenter=\$editorial['presenter'];",
'Boletim start');
patch_one($boletim,
"    array_unshift(\$jobs,\$job); bia_write('videos_ia.json',\$jobs);\n    \$msg=!empty(\$editorial['ok']) ? 'OpenAI criou o roteiro e escolheu “'.\$presenter['theme_label'].'”. Motivo: '.\$presenter['selection_reason'] : 'Boletim criado em fallback local. Erro editorial: '.(\$editorial['error']??'não identificado');\n  }\n}",
"    array_unshift(\$jobs,\$job); \$created++; \$themes[]=\$presenter['theme_label']; } bia_write('videos_ia.json',\$jobs);\n    \$msg=\$created.' boletim(ns) criado(s) com pautas distintas. Apresentadores/linhas editoriais: '.implode(', ',array_values(array_unique(\$themes))).'.';\n  }\n}",
'Boletim finish');
patch_one($boletim,
"<div class=\"grid2\"><div><label>Formato do boletim</label><select name=\"format\">",
"<div class=\"grid2\"><div><label>Quantidade de boletins</label><input type=\"number\" name=\"quantity\" min=\"1\" max=\"10\" value=\"1\"><small>Ao pedir mais de um, o sistema prioriza pautas diferentes.</small></div><div><label>Formato do boletim</label><select name=\"format\">",
'Boletim quantity UI');

echo "VIDEO_AI_HARDENING_APPLIED=SIM\n";
