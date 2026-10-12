<?php
/** Politica territorial: a selecao de identidade nao pode depender apenas do prompt. */
if (!function_exists('tvs_director_classify_city')) {
  function tvs_director_classify_city($value) {
    $raw=trim((string)$value);
    $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$raw);
    $city=strtolower(trim(preg_replace('/\s+/', ' ', $ascii===false?$raw:$ascii)));
    if($city==='' || in_array($city,['regiao','rmc','indefinido','indefinida','varias cidades','nao informado','multiplas cidades'],true)) return 'review';
    if(preg_match('/^sumare(?:\s*[-\/,]\s*(?:sp|sao paulo)|\s*\(\s*(?:sp|sao paulo)\s*\))?$/',$city)) return 'cristian';
    if(preg_match('/\bsumare\b|\s+e\s+|\s+\/\s+|\bregiao\b|\bmunicipios\b/',$city)) return 'review';
    if(strlen($city)>120 || !preg_match('/^[a-z0-9 .(),\/-]+$/',$city)) return 'review';
    return 'virtual';
  }
  function tvs_director_decide($job,$cfg) {
    $mode=tvs_director_classify_city($job['city']??'');
    if($mode==='review') return ['ok'=>false,'mode'=>$mode,'error'=>'Cidade principal ausente ou ambigua: confirme o municipio antes do HeyGen.'];
    if($mode==='cristian') {
      $avatar=trim((string)($cfg['heygen_cristian_avatar_id']??''));
      $voice=trim((string)($cfg['heygen_cristian_voice_id']??''));
      if($avatar==='' || $voice==='') return ['ok'=>false,'mode'=>$mode,'error'=>'Confirme Avatar ID e Voice ID oficiais de Cristian Schibelsky antes do envio.'];
      return ['ok'=>true,'mode'=>$mode,'presenter_name'=>'Cristian Schibelsky','avatar_id'=>$avatar,'voice_id'=>$voice];
    }
    if(preg_match('/\bcristian\s+schibelsky\b/iu',(string)($job['script']??''))) return ['ok'=>false,'mode'=>$mode,'error'=>'Roteiro de outra cidade contem assinatura de Cristian; revise antes de enviar.'];
    return ['ok'=>true,'mode'=>$mode,'presenter_name'=>'','avatar_id'=>'','voice_id'=>''];
  }
  function tvs_director_instructions($decision) {
    if(($decision['mode']??'')==='cristian') return 'POLITICA OBRIGATORIA: Sumare/SP. Somente avatar e voz oficiais de Cristian Schibelsky. GC: CRISTIAN SCHIBELSKY.';
    return 'POLITICA OBRIGATORIA: outra localidade. Apresentador virtual do HeyGen, sem nome de apresentador, sem GC de identidade e sem atribuir o texto a Cristian Schibelsky. Tarjas somente de cidade, titulo e editoria.';
  }
  function tvs_director_apply_payload($payload,$decision,$cfg) {
    unset($payload['avatar_id'],$payload['voice_id'],$payload['brand_kit_id']);
    if(($decision['mode']??'')==='cristian') { $payload['avatar_id']=$decision['avatar_id']; $payload['voice_id']=$decision['voice_id']; }
    $kit=trim((string)($cfg['heygen_editorial_brand_kit_id']??'1a38baabf787404590ba2b36f922248d'));
    if($kit!=='') $payload['brand_kit_id']=$kit;
    return $payload;
  }
}
