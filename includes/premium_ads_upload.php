<?php
/** Server-side media inspection. Never trust the file extension or the HTML duration. */
function tvs_premium_upload(array $file): array {
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
        !is_uploaded_file((string)($file['tmp_name']??''))) {
        return ['ok'=>false,'error'=>'Selecione um arquivo válido.'];
    }
    $tmp=(string)$file['tmp_name'];
    $size=(int)($file['size']??0);
    if ($size<1 || $size>20*1024*1024) {
        return ['ok'=>false,'error'=>'Arquivo deve ter no máximo 20 MB.'];
    }
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $images=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
    $video=($mime==='video/mp4' || $mime==='application/mp4');
    if (!$video && !isset($images[$mime])) {
        return ['ok'=>false,'error'=>'Use imagem JPG/PNG/WEBP ou vídeo MP4.'];
    }
    $duration=0;
    if ($video) {
        // ffprobe is installed alongside ffmpeg in the TV Sumaré Docker image.
        $output=[]; $exit=1;
        exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($tmp).' 2>&1', $output, $exit);
        $raw=trim((string)($output[0]??''));
        if ($exit!==0 || !is_numeric($raw) || !is_finite((float)$raw)) {
            return ['ok'=>false,'error'=>'Não foi possível validar o vídeo MP4.'];
        }
        $duration=(float)$raw;
        if ($duration<=0 || $duration>10.00) {
            return ['ok'=>false,'error'=>'Vídeo comercial deve ter no máximo 10 segundos.'];
        }
        $extension='mp4';
    } else {
        if (@getimagesize($tmp)===false) {
            return ['ok'=>false,'error'=>'O arquivo de imagem está inválido.'];
        }
        $extension=$images[$mime];
    }
    $dir=dirname(__DIR__).'/uploads/anuncios';
    if (!is_dir($dir) && !@mkdir($dir,0755,true) && !is_dir($dir)) {
        return ['ok'=>false,'error'=>'Diretório de anúncios indisponível.'];
    }
    $name='premium_'.bin2hex(random_bytes(12)).'.'.$extension;
    if (!move_uploaded_file($tmp,$dir.'/'.$name)) {
        return ['ok'=>false,'error'=>'Não foi possível salvar a mídia.'];
    }
    @chmod($dir.'/'.$name,0644);
    return ['ok'=>true,'path'=>'uploads/anuncios/'.$name,'kind'=>$video?'video':'image','duration'=>$duration];
}
