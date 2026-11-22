<?php
/**
 * Lightweight deterministic safety gate before paid HeyGen requests.
 * Semantic mismatches require editorial review; do not silently rewrite scripts.
 */
if (!function_exists('tvs_heygen_editorial_gate')) {
  function tvs_heygen_editorial_gate($script) {
    $text=trim((string)$script);
    $errors=[];
    if ($text==='' || (function_exists('mb_strlen') ? mb_strlen($text,'UTF-8') : strlen($text)) < 100) $errors[]='Roteiro vazio ou muito curto.';
    if (preg_match('/\\bportal\\s*[.!?]/iu',$text)) $errors[]='Referência ao portal sem endereço.';
    if (preg_match('/\\b(?:undefined|null|lorem ipsum|\\[inserir[^]]*\\]|\\{\\{[^}]+\\}\\})\\b/iu',$text)) $errors[]='Marcador de conteúdo não resolvido.';
    if (preg_match('/(?:\\.\\s*\\.|,\\s*\\.|\\s{3,})/u',$text)) $errors[]='Possível frase incompleta ou formatação inválida.';
    return ['ok'=>count($errors)===0,'errors'=>$errors];
  }
}
