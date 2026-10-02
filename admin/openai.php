<?php
require_once (is_file(__DIR__.'/includes/outbound_guard.php') ? __DIR__.'/includes/outbound_guard.php' : dirname(__DIR__).'/includes/outbound_guard.php');

function tvs_openai_log($msg){
    $file=dirname(__DIR__).'/data/ia_erros.log';
    @file_put_contents($file,'['.date('c').'] OpenAI: '.$msg."\n",FILE_APPEND);
}

function tvs_openai_extract_text($json){
    if(!is_array($json)) return '';
    if(isset($json['output_text']) && is_string($json['output_text'])) return trim($json['output_text']);
    $parts=[];
    foreach(($json['output']??[]) as $item){
        foreach(($item['content']??[]) as $content){
            if(isset($content['text']) && is_string($content['text'])) $parts[]=$content['text'];
        }
    }
    return trim(implode("\n",$parts));
}

function tvs_openai_generate_text($apiKey,$prompt,$options=[],$timeout=25){
    $apiKey=trim((string)$apiKey);
    if($apiKey==='') return ['ok'=>false,'error'=>'Chave OpenAI ausente.'];
    if(!function_exists('curl_init')) return ['ok'=>false,'error'=>'cURL não está habilitado no servidor.'];
    $model=trim((string)($options['model']??($GLOBALS['openai_model']??'gpt-5-mini')));
    if($model==='') $model='gpt-5-mini';
    $payload=['model'=>$model,'input'=>(string)$prompt];
    if(isset($options['max_output_tokens'])) $payload['max_output_tokens']=(int)$options['max_output_tokens'];
    $url='https://api.openai.com/v1/responses';
    $outbound=tvs_outbound_curl_options($url,(int)$timeout);
    if($outbound===null){ tvs_openai_log('URL bloqueada pela política de saída.'); return ['ok'=>false,'error'=>'URL OpenAI bloqueada pela política de saída.']; }
    $ch=curl_init($url);
    curl_setopt_array($ch,$outbound+[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    ]);
    $res=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($res===false || $res===''){ tvs_openai_log('Sem resposta: '.$err); return ['ok'=>false,'error'=>'Sem resposta da OpenAI.']; }
    if(strlen((string)$res)>2097152){ tvs_openai_log('Resposta excedeu limite seguro.'); return ['ok'=>false,'error'=>'Resposta OpenAI excedeu o limite seguro.']; }
    $json=json_decode((string)$res,true);
    if($http>=400){ tvs_openai_log('HTTP '.$http.': '.substr((string)$res,0,1200)); return ['ok'=>false,'error'=>'HTTP OpenAI '.$http,'http'=>$http]; }
    $text=tvs_openai_extract_text($json);
    if($text===''){ tvs_openai_log('Resposta sem texto utilizável.'); return ['ok'=>false,'error'=>'Resposta OpenAI sem texto.']; }
    return ['ok'=>true,'text'=>$text,'model'=>$model,'http'=>$http];
}
