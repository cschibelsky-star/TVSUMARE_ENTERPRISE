<?php
$path='/var/www/html/admin/reporter-ia.php';
$code=file_get_contents($path);
if($code===false){fwrite(STDERR,"Reporter IA não encontrado\n");exit(1);}
$old='$orientation=rpia_job_orientation($job,$cfg); $payload=[\'prompt\'=>rpia_video_agent_prompt($job,$cfg),\'mode\'=>\'generate\',\'incognito_mode\'=>rpia_bool($cfg[\'heygen_incognito_mode\']??\'0\'),\'orientation\'=>$orientation];';
$new='$orientation=rpia_job_orientation($job,$cfg); $payload=[\'prompt\'=>rpia_video_agent_prompt($job,$cfg),\'mode\'=>\'generate\',\'incognito_mode\'=>rpia_bool($cfg[\'heygen_incognito_mode\']??\'0\')];';
$n=substr_count($code,$old);
if($n===0 && strpos($code,"'incognito_mode'=>rpia_bool(\$cfg['heygen_incognito_mode']??'0')];")!==false){ echo "HEYGEN_V3_SCHEMA_FIX_ALREADY_APPLIED=SIM\n"; exit(0); }
if($n!==1){fwrite(STDERR,"HEYGEN_V3_SCHEMA_FIX: trecho esperado count={$n}; abortando\n");exit(2);}
$code=str_replace($old,$new,$code);
if(file_put_contents($path,$code)===false){fwrite(STDERR,"Falha ao gravar Reporter IA\n");exit(3);}
echo "HEYGEN_V3_SCHEMA_FIX_APPLIED=SIM\n";
