<?php
if(getenv('TVSUMARE_ENV')!=='homologation'||getenv('HML_ISOLATED')!=='1') throw new RuntimeException('isolated_hml_required');
$dir='/hml-data';
$run=bin2hex(random_bytes(5));
$draftId='hml_draft_'.$run;
$radarId='hml_radar_'.$run;
$title='Sumaré realiza verificação editorial em ambiente de homologação '.$run;
$body=str_repeat('Esta pauta fictícia de homologação verifica a persistência da revisão editorial em Sumaré. Nenhum fato real está sendo divulgado e o conteúdo permanece em ambiente interno isolado. ',6);
$sourceDate=gmdate(DATE_ATOM,time()-3600);
$common=['title'=>$title,'body'=>$body,'summary'=>'Teste interno de homologação','city'=>'Sumaré','category'=>'Cidade','source'=>'Fixture HML','ai_editor_processed'=>1,'created_at'=>gmdate(DATE_ATOM,time()-1800),'source_published_at'=>$sourceDate,'published_at'=>$sourceDate,'image'=>'','status'=>'aguardando','review_level'=>'normal','editorial_status'=>'Publicável','hml_fixture'=>1];
function rows($file){ $r=is_file($file)?json_decode(file_get_contents($file),true):[]; return is_array($r)?$r:[]; }
function appendRow($file,$row){$r=rows($file);$r[]=$row;file_put_contents($file,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));chown($file,33);chgrp($file,33);chmod($file,0664);}
$draft=$common+['id'=>$draftId,'source_url'=>'https://example.com/noticias/sumare-realiza-verificacao-editorial-em-ambiente-de-homologacao-'.$run];
$radar=$common+['id'=>$radarId,'source_url'=>'https://example.com/noticias/sumare-realiza-verificacao-editorial-em-ambiente-de-homologacao-radar-'.$run];
appendRow($dir.'/rascunhos.json',$draft);
appendRow($dir.'/materias_aprovacao.json',$radar);
appendRow($dir.'/radar_discovery_queue.json',$radar+['pipeline_stage'=>'aguardando_enriquecimento','pipeline_reason'=>'Pendente fixture']);
$cookies=[];
$checks=0;
function expect($ok,$message){global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function request($path,$post=null){
 global $cookies;
 $headers="Connection: close\r\n";
 if($cookies)$headers.='Cookie: '.implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($cookies),$cookies))."\r\n";
 $options=['method'=>$post===null?'GET':'POST','header'=>$headers,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30];
 if($post!==null){$options['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$options['content']=http_build_query($post);}
 $body=file_get_contents('http://web'.$path,false,stream_context_create(['http'=>$options]));
 $response=$http_response_header??[];
 foreach($response as $header)if(preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i',$header,$m))$cookies[$m[1]]=$m[2];
 $status=0;preg_match('/\s(\d{3})\s/',$response[0]??'',$m);$status=(int)($m[1]??0);
 $location='';foreach($response as $header)if(stripos($header,'Location:')===0)$location=trim(substr($header,9));
 return ['status'=>$status,'body'=>(string)$body,'location'=>$location];
}
function token($html){if(!preg_match('/name="_csrf"\s+value="([^"]+)"/',$html,$m))throw new RuntimeException('csrf_field_missing');return html_entity_decode($m[1],ENT_QUOTES);}
$login=request('/admin/login.php');
expect($login['status']===200,'login_get');
$result=request('/admin/auth.php',['_csrf'=>token($login['body']),'user'=>'hml_editorial_test','pass'=>getenv('HML_TEST_PASSWORD')]);
expect($result['status']===302&&$result['location']==='index.php','real_login_failed');
$review=request('/admin/drafts.php?edit='.urlencode($draftId));
expect($review['status']===200&&str_contains($review['body'],$draftId),'draft_visible_before_publication');
$result=request('/admin/drafts.php',['_csrf'=>token($review['body']),'id'=>$draftId,'action'=>'save','body'=>$body,'title'=>$title]);
expect($result['status']===302&&str_contains($result['location'],'saved=1'),'draft_save_failed');
$review=request('/admin/drafts.php?edit='.urlencode($draftId));
$result=request('/admin/drafts.php',['_csrf'=>token($review['body']),'id'=>$draftId,'action'=>'publish','review_confirm'=>'1','image_review_confirm'=>'1','body'=>$body,'title'=>$title]);
expect($result['status']===302&&str_contains($result['location'],'published=1'),'draft_publish_failed_'.$result['location']);
$news=rows($dir.'/noticias.json');
$published=array_values(array_filter($news,fn($n)=>($n['old_draft_id']??'')===$draftId));
expect(count($published)===1,'draft_publication_count');
expect($published[0]['source_published_at']===$sourceDate,'draft_source_date_changed');
expect($published[0]['published_at']!==$sourceDate,'draft_publication_date');
expect(!in_array($draftId,array_column(rows($dir.'/rascunhos.json'),'id'),true),'draft_still_persisted');
$approval=request('/admin/radar-regional.php?edit='.urlencode($radarId));
expect($approval['status']===200,'radar_edit_http');
$result=request('/admin/radar-regional.php',['_csrf'=>token($approval['body']),'id'=>$radarId,'action'=>'approve']);
expect($result['status']===302,'radar_post_redirect');
$news=rows($dir.'/noticias.json');
$radarNews=array_values(array_filter($news,fn($n)=>($n['radar_queue_id']??'')===$radarId));
expect(count($radarNews)===1,'radar_not_published');
expect(!in_array($radarId,array_column(rows($dir.'/materias_aprovacao.json'),'id'),true),'radar_queue_not_consumed');
$review=request('/admin/drafts.php');
expect($review['status']===200&&!str_contains($review['body'],$radarId),'published_discovery_reappeared_in_correction');
$article=request('/noticia.php?id='.urlencode($radarNews[0]['id']));
expect($article['status']===200&&str_contains($article['body'],$title),'article_display_failed');
expect(str_contains($article['body'],'datePublished')&&str_contains($article['body'],'dateModified'),'article_date_schema');
expect(str_contains($article['body'],'assets/'),'image_fallback_missing');
echo "EDITORIAL_HML_AUTHENTICATED_HTTP={$checks} PASS\n";
