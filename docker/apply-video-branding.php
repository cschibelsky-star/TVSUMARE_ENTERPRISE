<?php
$root=dirname(__DIR__);
$targets=[
  $root.'/includes/video_ai_helper.php'=>[
    'require_once __DIR__.\'/outbound_guard.php\';'=>"require_once __DIR__.'/outbound_guard.php';\nrequire_once __DIR__.'/video_branding.php';",
    '$url=trim((string)(($job[\'captioned_video_url\']??\'\') ?: ($job[\'video_url\']??\'\'))); if($url===\'\') return [\'ok\'=>false,\'error\'=>\'URL do vídeo ausente.\'];'=>'$url=trim((string)(($job[\'captioned_video_url\']??\'\') ?: ($job[\'video_url\']??\'\'))); if($url===\'\') return [\'ok\'=>false,\'error\'=>\'URL do vídeo ausente.\']; $branded=tvs_video_branding_apply($url,$job[\'id\']??\'tvplay\'); if(empty($branded[\'ok\'])) return [\'ok\'=>false,\'error\'=>\'Marca d’água obrigatória: \'.($branded[\'error\']??\'falha no processamento\')]; $url=$branded[\'url\'];'
  ],
  $root.'/admin/reporter-ia.php'=>[
    'require_once dirname(__DIR__).\'/includes/heygen_helper.php\';'=>"require_once dirname(__DIR__).'/includes/heygen_helper.php';\nrequire_once dirname(__DIR__).'/includes/video_branding.php';",
    '$url=$job[\'captioned_video_url\']??($job[\'video_url\']??\'\'); array_unshift($videos,['=>'$url=$job[\'captioned_video_url\']??($job[\'video_url\']??\'\'); $branded=tvs_video_branding_apply($url,$job[\'id\']??\'reporter\'); if(empty($branded[\'ok\'])) return false; $url=$branded[\'url\']; array_unshift($videos,['
  ]
];
foreach($targets as $file=>$replacements){
  if(!is_file($file)){ fwrite(STDERR,"Arquivo ausente: $file\n"); exit(1); }
  $s=file_get_contents($file);
  foreach($replacements as $from=>$to){
    if(strpos($s,$to)!==false) continue;
    if(strpos($s,$from)===false){ fwrite(STDERR,"Trecho esperado não encontrado em $file\n"); exit(1); }
    $s=str_replace($from,$to,$s,$count);
    if($count!==1){ fwrite(STDERR,"Substituição ambígua em $file: $count ocorrências\n"); exit(1); }
  }
  file_put_contents($file,$s);
}
echo "Video branding conectado aos fluxos TV Play e Reporter IA.\n";
