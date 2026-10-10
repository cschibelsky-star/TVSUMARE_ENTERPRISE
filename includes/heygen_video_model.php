<?php
/**
 * HeyGen Video 1.0 — experimental scene adapter.
 * Paid generation is deliberately disabled until a separate budget approval.
 * Never use this model to fabricate documentary footage of real events.
 */
require_once __DIR__.'/outbound_guard.php';
if (!function_exists('tvs_hvm_validate')) {
  function tvs_hvm_validate(array $request): array {
    $mode=(string)($request['mode']??'text_to_video');
    if(!in_array($mode,['text_to_video','image_to_video','reference_to_video'],true)) return ['ok'=>false,'error'=>'Modo inválido'];
    $prompt=trim((string)($request['prompt']??''));
    if($prompt==='' || strlen($prompt)>32000) return ['ok'=>false,'error'=>'Prompt inválido'];
    $duration=(int)($request['duration']??5);
    if($duration<5 || $duration>15) return ['ok'=>false,'error'=>'Duração fora do intervalo de 5 a 15 segundos'];
    $resolution=(string)($request['resolution']??'768p');
    if(!in_array($resolution,['480p','768p','1080p','2k'],true)) return ['ok'=>false,'error'=>'Resolução inválida'];
    $ratio=(string)($request['aspect_ratio']??'16:9');
    if(!in_array($ratio,['16:9','9:16','1:1','4:3','3:4','21:9'],true)) return ['ok'=>false,'error'=>'Proporção inválida'];
    if(in_array($resolution,['1080p','2k'],true) && !in_array($ratio,['16:9','9:16'],true)) return ['ok'=>false,'error'=>'Alta resolução requer 16:9 ou 9:16'];
    $payload=['model'=>'heygen-video-1','mode'=>$mode,'prompt'=>$prompt,'duration'=>$duration,'resolution'=>$resolution,'aspect_ratio'=>$ratio];
    if(isset($request['seed'])) {
      if(!is_int($request['seed']) || $request['seed']<0 || $request['seed']>4294967295) return ['ok'=>false,'error'=>'Seed inválida'];
      $payload['seed']=$request['seed'];
    }
    $enhancement=(string)($request['prompt_enhancement']??'disabled');
    if(!in_array($enhancement,['turbo','quality','disabled'],true)) return ['ok'=>false,'error'=>'Prompt enhancement inválido'];
    $payload['prompt_enhancement']=$enhancement;
    if($mode==='image_to_video') {
      if(empty($request['image']) || !is_array($request['image'])) return ['ok'=>false,'error'=>'Imagem inicial obrigatória'];
      $payload['image']=$request['image'];
    }
    if($mode==='reference_to_video') {
      $images=$request['reference_images']??[]; $videos=$request['reference_videos']??[]; $audio=$request['reference_audio']??[];
      if(!is_array($images)||!is_array($videos)||!is_array($audio)||count($images)>9||count($videos)>3||count($audio)>3||count($images)+count($videos)+count($audio)>12||count($images)+count($videos)===0) return ['ok'=>false,'error'=>'Referências inválidas'];
      $payload['reference_images']=$images; $payload['reference_videos']=$videos; $payload['reference_audio']=$audio;
    }
    return ['ok'=>true,'payload'=>$payload];
  }
  function tvs_hvm_enabled(): bool { return false; } // Hard gate: no billable execution in experimental phase.
  function tvs_hvm_plan(array $request): array {
    $v=tvs_hvm_validate($request);
    if(!$v['ok']) return $v;
    return ['ok'=>true,'engine'=>'heygen_video_1','billable'=>true,'enabled'=>tvs_hvm_enabled(),'payload'=>$v['payload'],'requires_explicit_approval'=>true];
  }
  function tvs_hvm_submit(array $request, string $approvalToken=''): array {
    $v=tvs_hvm_plan($request);
    if(!$v['ok']) return $v;
    if(!$v['enabled']) return ['ok'=>false,'error'=>'Geração paga HeyGen Video 1.0 desativada no runtime'];
    if($approvalToken==='') return ['ok'=>false,'error'=>'Aprovação de custo obrigatória'];
    // Intentionally no paid API call in this experimental phase.
    return ['ok'=>false,'error'=>'Adaptador em homologação: envio pago ainda não implementado'];
  }
}
