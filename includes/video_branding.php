<?php
require_once __DIR__.'/outbound_guard.php';

if (!function_exists('tvs_video_branding_root')) {
  function tvs_video_branding_root(){ return dirname(__DIR__); }
  function tvs_video_branding_safe_id($id){
    $id=preg_replace('/[^a-zA-Z0-9_-]+/','-',trim((string)$id));
    $id=trim((string)$id,'-_');
    return $id!==''?$id:'video-'.date('YmdHis');
  }
  function tvs_video_branding_watermark(){
    $root=tvs_video_branding_root();
    $preferred=$root.'/assets/branding/tv-sumare-watermark.png';
    if(is_file($preferred)) return $preferred;
    $fallback=$root.'/assets/logo-tv-sumare.jpeg';
    return is_file($fallback)?$fallback:'';
  }
  function tvs_video_branding_download($url,$dest){
    $url=trim((string)$url);
    if(!preg_match('~^https://~i',$url)) return ['ok'=>false,'error'=>'A origem do vídeo precisa usar HTTPS.'];
    if(!function_exists('curl_init')) return ['ok'=>false,'error'=>'cURL indisponível para baixar o vídeo.'];
    $opts=tvs_outbound_curl_options($url,120);
    if($opts===null) return ['ok'=>false,'error'=>'Origem do vídeo bloqueada pela política de saída.'];
    $fp=@fopen($dest,'wb');
    if(!$fp) return ['ok'=>false,'error'=>'Não foi possível criar arquivo temporário do vídeo.'];
    $ch=curl_init($url);
    curl_setopt_array($ch,$opts+[
      CURLOPT_FILE=>$fp,
      CURLOPT_FOLLOWLOCATION=>false,
      CURLOPT_FAILONERROR=>false,
      CURLOPT_HTTPHEADER=>['Accept: video/mp4,video/*;q=0.9,*/*;q=0.1']
    ]);
    $ok=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
    curl_close($ch); fclose($fp);
    $size=is_file($dest)?(int)filesize($dest):0;
    if(!$ok || $http<200 || $http>=300 || $size<10240){ @unlink($dest); return ['ok'=>false,'error'=>'Falha ao baixar vídeo de origem (HTTP '.$http.'). '.$err]; }
    if($type!=='' && stripos($type,'video/')!==0 && stripos($type,'octet-stream')===false){ @unlink($dest); return ['ok'=>false,'error'=>'Origem retornou conteúdo incompatível: '.$type]; }
    return ['ok'=>true,'bytes'=>$size];
  }
  function tvs_video_branding_apply($sourceUrl,$jobId,$options=[]){
    $sourceUrl=trim((string)$sourceUrl);
    if($sourceUrl==='') return ['ok'=>false,'error'=>'URL do vídeo ausente.'];
    $ffmpeg=trim((string)@shell_exec('command -v ffmpeg 2>/dev/null'));
    if($ffmpeg==='') return ['ok'=>false,'error'=>'FFmpeg não está instalado no runtime da TV Sumaré.'];
    $watermark=tvs_video_branding_watermark();
    if($watermark==='') return ['ok'=>false,'error'=>'Arquivo oficial de marca d’água da TV Sumaré não encontrado.'];
    $safe=tvs_video_branding_safe_id($jobId);
    $dir=tvs_video_branding_root().'/videos/branded';
    if(!is_dir($dir) && !@mkdir($dir,0775,true)) return ['ok'=>false,'error'=>'Não foi possível preparar diretório de vídeos marcados.'];
    $out=$dir.'/'.$safe.'.mp4';
    if(is_file($out) && filesize($out)>10240){ return ['ok'=>true,'path'=>$out,'url'=>'videos/branded/'.rawurlencode($safe).'.mp4','cached'=>true]; }
    $tmp=tempnam('/tmp','tvs-video-');
    if($tmp===false) return ['ok'=>false,'error'=>'Não foi possível criar arquivo temporário.'];
    $download=tvs_video_branding_download($sourceUrl,$tmp);
    if(empty($download['ok'])){ @unlink($tmp); return $download; }
    $opacity=isset($options['opacity'])?max(0.35,min(1.0,(float)$options['opacity'])):0.82;
    $widthRatio=isset($options['width_ratio'])?max(0.10,min(0.28,(float)$options['width_ratio'])):0.18;
    $filter="[1:v][0:v]scale2ref=w=main_w*{$widthRatio}:h=-1[wm][base];[wm]format=rgba,colorchannelmixer=aa={$opacity}[wm2];[base][wm2]overlay=x=W*0.03:y=H*0.035:format=auto[v]";
    $cmd=escapeshellarg($ffmpeg).' -hide_banner -loglevel error -y -i '.escapeshellarg($tmp).' -i '.escapeshellarg($watermark).' -filter_complex '.escapeshellarg($filter).' -map '.escapeshellarg('[v]').' -map 0:a? -c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p -c:a aac -b:a 160k -movflags +faststart '.escapeshellarg($out).' 2>&1';
    $lines=[]; $code=0; exec($cmd,$lines,$code); @unlink($tmp);
    if($code!==0 || !is_file($out) || filesize($out)<10240){ @unlink($out); return ['ok'=>false,'error'=>'Falha ao aplicar marca d’água: '.substr(implode("\n",$lines),0,800)]; }
    return ['ok'=>true,'path'=>$out,'url'=>'videos/branded/'.rawurlencode($safe).'.mp4','bytes'=>(int)filesize($out),'cached'=>false];
  }
}
