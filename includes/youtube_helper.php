<?php
require_once __DIR__.'/outbound_guard.php';

if (!function_exists('tvs_youtube_channel_url')) {
  function tvs_youtube_channel_url(){ return 'https://www.youtube.com/@tvsumare'; }
  function tvs_youtube_fetch($url,$timeout=20){
    $opts=tvs_outbound_curl_options($url,$timeout);
    if($opts===null || !function_exists('curl_init')) return ['ok'=>false,'error'=>'Saída HTTP para YouTube indisponível.'];
    $ch=curl_init($url);
    curl_setopt_array($ch,$opts+[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_FOLLOWLOCATION=>false,
      CURLOPT_HTTPHEADER=>['Accept: text/html,application/atom+xml,application/xml;q=0.9,*/*;q=0.8','User-Agent: Mozilla/5.0 TVSumareEnterprise/1.0']
    ]);
    $body=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $location=(string)curl_getinfo($ch,CURLINFO_REDIRECT_URL); curl_close($ch);
    if(!is_string($body) || $http<200 || $http>=400) return ['ok'=>false,'http'=>$http,'error'=>'YouTube HTTP '.$http.($err?': '.$err:'')];
    return ['ok'=>true,'http'=>$http,'body'=>$body,'redirect'=>$location];
  }
  function tvs_youtube_channel_id(){
    $r=tvs_youtube_fetch(tvs_youtube_channel_url(),20); if(empty($r['ok'])) return $r;
    $body=$r['body'];
    foreach([
      '/"channelId":"(UC[A-Za-z0-9_-]{20,})"/',
      '/"externalId":"(UC[A-Za-z0-9_-]{20,})"/',
      '/itemprop="channelId" content="(UC[A-Za-z0-9_-]{20,})"/'
    ] as $re){ if(preg_match($re,$body,$m)) return ['ok'=>true,'channel_id'=>$m[1]]; }
    return ['ok'=>false,'error'=>'Não foi possível resolver o channel_id do @tvsumare.'];
  }
  function tvs_youtube_feed($limit=15){
    $cid=tvs_youtube_channel_id(); if(empty($cid['ok'])) return $cid;
    $url='https://www.youtube.com/feeds/videos.xml?channel_id='.rawurlencode($cid['channel_id']);
    $r=tvs_youtube_fetch($url,20); if(empty($r['ok'])) return $r;
    libxml_use_internal_errors(true); $xml=simplexml_load_string($r['body']); if(!$xml) return ['ok'=>false,'error'=>'Feed XML do YouTube inválido.'];
    $ns=$xml->getNamespaces(true); $yt=$ns['yt']??null; $media=$ns['media']??null; $items=[];
    foreach($xml->entry as $entry){
      $videoId=$yt?(string)$entry->children($yt)->videoId:''; if($videoId==='') continue;
      $group=$media?$entry->children($media)->group:null;
      $thumb='https://i.ytimg.com/vi/'.$videoId.'/hqdefault.jpg';
      if($group && isset($group->thumbnail)){ $a=$group->thumbnail->attributes(); if(!empty($a['url'])) $thumb=(string)$a['url']; }
      $items[]=[
        'youtube_video_id'=>$videoId,
        'title'=>trim((string)$entry->title),
        'url'=>'https://www.youtube.com/watch?v='.$videoId,
        'thumb'=>$thumb,
        'published_at'=>trim((string)$entry->published),
        'updated_at'=>trim((string)$entry->updated),
        'channel_id'=>$cid['channel_id'],
        'channel_url'=>tvs_youtube_channel_url(),
        'origin'=>'youtube_official'
      ];
      if(count($items)>=$limit) break;
    }
    return ['ok'=>true,'channel_id'=>$cid['channel_id'],'items'=>$items];
  }
  function tvs_youtube_sync_to_tvplay($limit=15){
    $feed=tvs_youtube_feed($limit); if(empty($feed['ok'])) return $feed;
    $path=dirname(__DIR__).'/data/videos.json'; $videos=[]; if(is_file($path)){ $d=json_decode((string)file_get_contents($path),true); if(is_array($d)) $videos=$d; }
    $existing=[]; foreach($videos as $v){ $id=trim((string)($v['youtube_video_id']??'')); if($id!=='') $existing[$id]=true; $url=(string)($v['url']??''); if(preg_match('~(?:v=|youtu\.be/|shorts/)([A-Za-z0-9_-]{6,})~',$url,$m)) $existing[$m[1]]=true; }
    $created=0;
    foreach(array_reverse($feed['items']) as $it){ if(isset($existing[$it['youtube_video_id']])) continue; array_unshift($videos,[
      'id'=>'yt_'.$it['youtube_video_id'], 'title'=>$it['title'], 'category'=>'YouTube', 'description'=>'Publicado no canal oficial da TV Sumaré.',
      'url'=>$it['url'], 'thumb'=>$it['thumb'], 'status'=>'active', 'featured'=>0, 'youtube_video_id'=>$it['youtube_video_id'],
      'youtube_channel_id'=>$it['channel_id'], 'origin'=>'youtube_official', 'published_at'=>$it['published_at'], 'created_at'=>date('c')
    ]); $existing[$it['youtube_video_id']]=true; $created++; }
    if(!is_dir(dirname($path))) @mkdir(dirname($path),0775,true);
    file_put_contents($path,json_encode(array_values($videos),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    return ['ok'=>true,'created'=>$created,'total_feed'=>count($feed['items']),'channel_id'=>$feed['channel_id']];
  }
  function tvs_youtube_live_status(){
    $url=rtrim(tvs_youtube_channel_url(),'/').'/live'; $r=tvs_youtube_fetch($url,20); if(empty($r['ok'])) return $r;
    $body=$r['body'];
    if(strpos($body,'"isLiveNow":true')===false && strpos($body,'"isLive":true')===false) return ['ok'=>true,'live'=>false,'channel_url'=>tvs_youtube_channel_url()];
    if(preg_match('/"videoId":"([A-Za-z0-9_-]{6,})"/',$body,$m)) return ['ok'=>true,'live'=>true,'video_id'=>$m[1],'watch_url'=>'https://www.youtube.com/watch?v='.$m[1],'embed_url'=>'https://www.youtube.com/embed/'.$m[1]];
    return ['ok'=>true,'live'=>false,'channel_url'=>tvs_youtube_channel_url(),'warning'=>'Sinal de live detectado sem video_id confiável.'];
  }
}
