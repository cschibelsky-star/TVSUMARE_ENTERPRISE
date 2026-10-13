<?php
// Shared fail-closed contract for script generation; never logs provider bodies or secrets.
class TvsScriptException extends RuntimeException {}

function tvs_script_require_text($text){
  if(!is_string($text) || !preg_match('//u',$text)) throw new TvsScriptException('invalid_script');
  $plain=trim(preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u',' ',html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8')));
  $length=preg_match_all('/./us',$plain);
  if($length<80 || $length>20000 || preg_match_all('/\p{L}+/u',$plain)<12
    || preg_match('/^(?:```|[\{\[])|^(?:null|undefined|error|erro)\b/iu',$plain)) throw new TvsScriptException('invalid_script');
  return $plain;
}

function tvs_script_provider_text($result){
  if(!is_array($result) || empty($result['ok'])){ $http=(int)($result['http']??0); throw new TvsScriptException($http>=100 && $http<=599?'provider_http_'.$http:'provider_failed'); }
  $finish=$result['finish_reason']??'';
  if(in_array($finish,['MAX_TOKENS','length','SAFETY','RECITATION'],true)) throw new TvsScriptException('provider_incomplete');
  return tvs_script_require_text($result['text']??null);
}

function tvs_script_failure($context,$error){
  $id=bin2hex(random_bytes(6));
  $code=$error instanceof TvsScriptException ? $error->getMessage() : 'unexpected_failure';
  if(!preg_match('/^[a-z_0-9]+$/',$code)) $code='unexpected_failure';
  error_log('TVS_SCRIPT '.json_encode(['at'=>date('c'),'reference'=>$id,'context'=>$context,'code'=>$code]));
  $reason='Não foi possível concluir o roteiro.';
  if($code==='invalid_script' || $code==='provider_incomplete') $reason='O roteiro está vazio, inválido ou incompleto.';
  elseif($code==='provider_not_configured') $reason='O provedor de roteiros não está configurado.';
  elseif(strpos($code,'provider_http_')===0) $reason='O provedor de roteiros retornou HTTP '.substr($code,14).'.';
  elseif(strpos($code,'provider')!==false) $reason='O provedor não retornou um roteiro válido.';
  elseif(strpos($code,'storage')===0) $reason='Não foi possível salvar o roteiro no armazenamento.';
  elseif($code==='job_conflict') $reason='Este roteiro foi alterado durante a geração. Recarregue a página.';
  elseif($code==='script_state_locked') $reason='O estado atual impede esta alteração do roteiro.';
  return $reason.' Nenhum roteiro foi aprovado nesta tentativa. Referência: '.$id.'.';
}

// Re-read under lock after the slow provider call. Preserve unrelated and approved jobs.
// All script writes use this API; existing video writers remain outside this lock protocol.
function tvs_script_store_job($path,$job,$expected=null){
  $job['script']=tvs_script_require_text($job['script']??null);
  if(empty($job['id'])) throw new TvsScriptException('invalid_job');
  $dir=dirname($path);
  if(!is_dir($dir) && !@mkdir($dir,0775,true)) throw new TvsScriptException('storage_failed');
  $lock=@fopen($path.'.scripts.lock','c');
  if(!$lock) throw new TvsScriptException('storage_failed');
  $tmp=null;
  try {
    if(!flock($lock,LOCK_EX)) throw new TvsScriptException('storage_failed');
    $rows=[];
    if(file_exists($path)){
      $raw=@file_get_contents($path);
      $rows=is_string($raw)?json_decode($raw,true):null;
      if(!is_array($rows) || array_values($rows)!==$rows) throw new TvsScriptException('storage_invalid');
    }
    $index=null;
    foreach($rows as $i=>$row){ if(!is_array($row)) throw new TvsScriptException('storage_invalid'); if(($row['id']??'')===$job['id']) $index=$i; }
    if($expected===null){ if($index!==null) throw new TvsScriptException('job_conflict'); array_unshift($rows,$job); }
    else { if($index===null || $rows[$index]!==$expected) throw new TvsScriptException('job_conflict'); $rows[$index]=$job; }
    $json=json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(!is_string($json)) throw new TvsScriptException('storage_invalid');
    $tmp=@tempnam($dir,'.script-');
    if(!$tmp || dirname($tmp)!==realpath($dir)) throw new TvsScriptException('storage_failed');
    if(@file_put_contents($tmp,$json,LOCK_EX)!==strlen($json)) throw new TvsScriptException('storage_failed');
    @chmod($tmp,file_exists($path)?(fileperms($path)&0777):0664);
    if(!@rename($tmp,$path)) throw new TvsScriptException('storage_failed');
    $tmp=null;
    return true;
  } finally { if($tmp!==null) @unlink($tmp); flock($lock,LOCK_UN); fclose($lock); }
}
