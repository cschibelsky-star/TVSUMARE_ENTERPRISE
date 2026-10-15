<?php
// CLI-only integration harness. Starts the built Apache app on localhost with synthetic data.
if(PHP_SAPI!=='cli' || getenv('TVSUMARE_ENV')!=='homologation' || getenv('HML_ISOLATED')!=='1') throw new RuntimeException('isolated_hml_required');
foreach(['OPENAI_API_KEY','GEMINI_API_KEY','ANTHROPIC_API_KEY','HEYGEN_API_KEY','CENTRO_IA_INTERNAL_TOKEN'] as $key) if(getenv($key)) throw new RuntimeException('provider_credentials_forbidden');
$dir=dirname(__DIR__).'/data';
if(getenv('HML_SYNTHETIC_DATA')!=='1') throw new RuntimeException('synthetic_data_required');
$text='Esta é uma pauta fictícia para testar a homologação isolada da TV Sumaré. O encontro fictício ocorre no local de teste e não representa qualquer evento real da cidade.';
$news=[['id'=>'hml-news','title'=>'Pauta fictícia','body'=>$text,'summary'=>$text,'city'=>'Sumaré','category'=>'Cidades','source'=>'Fixture HML','status'=>'published','published_at'=>date('c')]];
$original=[['id'=>'hml-approved','title'=>'Roteiro aprovado preservado','script'=>$text,'status'=>'roteiro_aprovado','city'=>'Sumaré'],['id'=>'hml-revision','title'=>'Roteiro em revisão','script'=>$text,'body'=>$text,'status'=>'roteiro_revisao','city'=>'Sumaré']];
if(!is_dir($dir))mkdir($dir,0775,true);
foreach(['noticias.json'=>$news,'videos_ia.json'=>$original] as $file=>$rows){file_put_contents($dir.'/'.$file,json_encode($rows,JSON_UNESCAPED_UNICODE));chown($dir.'/'.$file,33);chgrp($dir.'/'.$file,33);chmod($dir.'/'.$file,0664);}
chown($dir,33);chgrp($dir,33);chmod($dir,0775);
$password=bin2hex(random_bytes(24));putenv('TVSUMARE_ADMIN_USER=hml_script_test');putenv('TVSUMARE_ADMIN_PASS_HASH='.password_hash($password,PASSWORD_DEFAULT));
$server=proc_open(['apache2-foreground'],[['pipe','r'],['file','/tmp/tvs-script-apache.log','a'],['file','/tmp/tvs-script-apache.log','a']],$pipes);
if(!is_resource($server))throw new RuntimeException('apache_start_failed');
$cookies=[];$checks=0;
function sh_expect($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function sh_request($path,$post=null){
 global $cookies;
 $headers="Connection: close\r\n";if($cookies)$headers.='Cookie: '.implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($cookies),$cookies))."\r\n";
 $opts=['method'=>$post===null?'GET':'POST','header'=>$headers,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15];
 if($post!==null){$opts['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$opts['content']=http_build_query($post);}
 $body=@file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>$opts]));$response=$http_response_header??[];$location='';
 foreach($response as $h){if(preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i',$h,$m))$cookies[$m[1]]=$m[2];if(stripos($h,'Location:')===0)$location=trim(substr($h,9));}
 preg_match('/\s(\d{3})\s/',$response[0]??'',$m);return ['status'=>(int)($m[1]??0),'body'=>(string)$body,'location'=>$location];
}
function sh_token($body){if(!preg_match('/name="_csrf"\s+value="([^"]+)"/',$body,$m))throw new RuntimeException('csrf_missing');return html_entity_decode($m[1],ENT_QUOTES);}
function sh_rows(){return json_decode(file_get_contents(dirname(__DIR__).'/data/videos_ia.json'),true);}
function sh_post($page,$data){$r=sh_request($page);sh_expect($r['status']===200,'page_get_'.$page);return sh_request($page,['_csrf'=>sh_token($r['body'])]+$data);}
try {
 for($i=0;$i<30;$i++){ $login=sh_request('/admin/login.php');if($login['status']===200)break;usleep(100000); }
 sh_expect($login['status']===200,'login');
 $r=sh_request('/admin/auth.php',['_csrf'=>sh_token($login['body']),'user'=>'hml_script_test','pass'=>$password]);sh_expect($r['status']===302,'authenticated');
 $before=file_get_contents($dir.'/videos_ia.json');
 $r=sh_post('/admin/reporter-ia.php',['action'=>'generate_script','news_id'=>'hml-news']);sh_expect($r['status']===200 && str_contains($r['body'],'Referência:'),'reporter failure visible');sh_expect(file_get_contents($dir.'/videos_ia.json')===$before,'reporter no job on failure');
 $r=sh_post('/admin/boletim-ia.php',['create_boletim'=>'1','news_id'=>'hml-news','quantity'=>'1','format'=>'noticia_1_minuto']);sh_expect($r['status']===200 && str_contains($r['body'],'Referência:'),'boletim failure visible');sh_expect(file_get_contents($dir.'/videos_ia.json')===$before,'boletim no fabricated success');
 $r=sh_post('/admin/tvplay.php',['action'=>'generate_script','job_id'=>'hml-revision']);sh_expect($r['status']===302 && str_contains($r['location'],'err='),'tvplay failure redirect');sh_expect(file_get_contents($dir.'/videos_ia.json')===$before,'tvplay existing script preserved');
 $r=sh_request($r['location']);sh_expect($r['status']===200 && str_contains($r['body'],'Referência:'),'tvplay operator error');
 foreach(['/admin/reporter-ia.php'=>'update_script','/admin/tvplay.php'=>'save_script'] as $page=>$action){
   $r=sh_post($page,['action'=>$action,'job_id'=>'hml-revision','script'=>'<p></p>']);sh_expect(($r['status']===200 && str_contains($r['body'],'Referência:')) || ($r['status']===302 && str_contains($r['location'],'err=')),'invalid approval rejected');sh_expect(file_get_contents($dir.'/videos_ia.json')===$before,'invalid approval unchanged');
 }
 $r=sh_post('/admin/tvplay.php',['action'=>'generate_script','job_id'=>'hml-approved']);sh_expect($r['status']===302 && str_contains($r['location'],'err='),'approved regeneration blocked');sh_expect(sh_rows()[0]===$original[0],'approved preserved');
 $r=sh_post('/admin/reporter-ia.php',['action'=>'update_script','job_id'=>'hml-revision','script'=>$text]);sh_expect($r['status']===200 && str_contains($r['body'],'Roteiro revisado e aprovado'),'valid reporter approval');sh_expect(sh_rows()[1]['status']==='roteiro_aprovado','reporter approval persisted');
 $r=sh_post('/admin/tvplay.php',['action'=>'save_script','job_id'=>'hml-revision','script'=>$text]);sh_expect($r['status']===302 && str_contains($r['location'],'msg='),'valid tvplay approval');sh_expect(sh_rows()[1]['status']==='roteiro_aprovado','tvplay approval persisted');sh_expect(sh_rows()[0]===$original[0],'unrelated approved preserved');
 file_put_contents($dir.'/videos_ia.json','{broken');$r=sh_post('/admin/reporter-ia.php',['action'=>'generate_script','news_id'=>'hml-news']);sh_expect(str_contains($r['body'],'Referência:'),'failure with corrupt queue visible');sh_expect(file_get_contents($dir.'/videos_ia.json')==='{broken','corrupt queue preserved');
 echo 'SCRIPT_HTTP_HML=PASS checks='.$checks."\n";
} finally {proc_terminate($server);proc_close($server);}
