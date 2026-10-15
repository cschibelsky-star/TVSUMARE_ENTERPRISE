<?php
require_once dirname(__DIR__).'/includes/script_generation.php';
$checks=0;
function sg_expect($ok,$label){ global $checks; $checks++; if(!$ok) throw new RuntimeException($label); }
function sg_reject($callback,$label){ try { $callback(); } catch(TvsScriptException $e){ sg_expect(true,$label); return; } throw new RuntimeException($label); }
// Load actual generator functions only. No auth, runtime configuration, HTTP or paid calls.
function sg_load_functions($path,$names){
  $tokens=token_get_all(file_get_contents($path));
  for($i=0;$i<count($tokens);$i++){
    if(!is_array($tokens[$i]) || $tokens[$i][0]!==T_FUNCTION) continue;
    $j=$i+1; while(isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0]===T_WHITESPACE) $j++;
    if(!is_array($tokens[$j]) || !in_array($tokens[$j][1],$names,true)) continue;
    $code=''; $depth=0; $started=false;
    for(;$i<count($tokens);$i++){
      $t=$tokens[$i]; $code.=is_array($t)?$t[1]:$t;
      if($t==='{' || (is_array($t) && in_array($t[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true))){ $depth++; $started=true; } elseif($t==='}') $depth--;
      if($started && $depth===0) break;
    }
    eval($code);
  }
}
function tvs_clean_text($s){ return trim(strip_tags((string)$s)); }
function tvs_substr($s,$a,$b){ return substr($s,$a,$b); }
function tvs_strlen($s){ return strlen($s); }
function tvs_gemini_generate_text(...$args){ return $GLOBALS['sg_provider']; }
function tvs_openai_generate_text(...$args){ return $GLOBALS['sg_provider']; }
function tvp_core_generate_text($prompt){ return $GLOBALS['sg_core']; }
function tvp_clean($s){ return trim(strip_tags((string)$s)); }
function tvp_avatar_profile_for_category($cat){ return 'generic'; }
function tvp_presenter_label($profile){ return 'TV Sumaré'; }
function tvp_script_opening_for_profile(...$args){ return 'Confira as informações da TV Sumaré.'; }
function tvp_video_engine_decide(...$args){ return 'orchestrated'; }
function bia_fallback_presenter(...$args){ return ['theme'=>'noticias']; }
function bia_format_meta($f){ return ['label'=>'Boletim','target'=>'60 segundos']; }
function bia_excerpt($s,$n){ return substr((string)$s,0,$n); }
function bia_clean($s){ return trim(strip_tags((string)$s)); }
function bia_presenter_result(...$args){ return ['theme'=>'noticias']; }
sg_load_functions(dirname(__DIR__).'/admin/reporter-ia.php',['rpia_clean_script','rpia_generate_script']);
sg_load_functions(dirname(__DIR__).'/admin/boletim-ia.php',['bia_openai_editorial']);
sg_load_functions(dirname(__DIR__).'/includes/video_ai_helper.php',['tvp_generate_script','tvp_polish_script']);
$text='Esta é uma pauta fictícia de homologação. O encontro de teste ocorre em Sumaré, com acesso gratuito e atendimento das nove às dez horas. A informação foi fornecida pela equipe de teste.';
$news=['id'=>'synthetic','title'=>'Encontro fictício','city'=>'Sumaré','body'=>$text];
foreach([''," \n",'<p></p>',"\u{200B}",[],null,'{}','```json '.json_encode(['script'=>$text]).'```','{"script":"'.$text.'"}','Erro: '.str_repeat('falha ',30),'texto curto'] as $bad) sg_reject(fn()=>tvs_script_require_text($bad),'invalid rejected');
sg_expect(tvs_script_require_text($text)===$text,'valid text');
foreach([['ok'=>false,'error'=>'HTTP 401'],['ok'=>false,'error'=>'HTTP 429'],['ok'=>false,'error'=>'timeout'],['ok'=>true,'text'=>''],['ok'=>true,'text'=>[]],['ok'=>true,'text'=>$text,'finish_reason'=>'MAX_TOKENS']] as $r) sg_reject(fn()=>tvs_script_provider_text($r),'provider rejected');
$anthropic_api_key=''; $gemini_api_key=''; $openai_api_key=''; $openai_model='test';
sg_reject(fn()=>rpia_generate_script($news),'reporter missing provider');
sg_expect(!bia_openai_editorial($news,'noticia_1_minuto',[])['ok'],'boletim absent key');
$openai_api_key='synthetic-not-a-key'; $gemini_api_key='synthetic-not-a-key';
foreach(['', '{}', '{"script":[]}', '{"script":""}', 'not JSON'] as $bad){ $GLOBALS['sg_provider']=['ok'=>true,'text'=>$bad]; sg_expect(!bia_openai_editorial($news,'test',[])['ok'],'boletim bad response'); }
$GLOBALS['sg_provider']=['ok'=>true,'text'=>json_encode(['theme'=>'noticias','script'=>$text])];
sg_expect(bia_openai_editorial($news,'test',[])['script']===$text,'boletim valid');
$GLOBALS['sg_provider']=['ok'=>true,'text'=>'']; sg_reject(fn()=>rpia_generate_script($news),'reporter empty');
$GLOBALS['sg_provider']=['ok'=>true,'text'=>$text]; sg_expect(rpia_generate_script($news)===$text,'reporter valid');
$GLOBALS['sg_core']=['ok'=>true,'text'=>'']; $GLOBALS['sg_provider']=['ok'=>false];
sg_reject(fn()=>tvp_generate_script($news),'tvplay failed providers');
sg_reject(fn()=>tvp_polish_script('',$news),'polishing empty cannot fabricate text');
$GLOBALS['sg_provider']=['ok'=>true,'text'=>$text]; sg_expect(str_contains(tvp_generate_script($news),'pauta fictícia'),'tvplay secondary fallback');
$GLOBALS['sg_core']=['ok'=>true,'text'=>$text]; sg_expect(str_contains(tvp_generate_script($news),'pauta fictícia'),'tvplay primary');
$dir=sys_get_temp_dir().'/tvs-script-'.bin2hex(random_bytes(5)); mkdir($dir);
$path=$dir.'/jobs.json'; $approved=['id'=>'approved','script'=>$text,'status'=>'roteiro_aprovado'];
file_put_contents($path,json_encode([$approved]));
$new=['id'=>'new','script'=>$text,'status'=>'roteiro_revisao']; tvs_script_store_job($path,$new);
$rows=json_decode(file_get_contents($path),true); sg_expect($rows[1]===$approved,'approved preserved');
$before=file_get_contents($path); sg_reject(fn()=>tvs_script_store_job($path,['id'=>'bad','script'=>'','status'=>'roteiro_aprovado']),'empty approval rejected'); sg_expect(file_get_contents($path)===$before,'failed save unchanged');
$updated=$new; $updated['status']='roteiro_aprovado'; tvs_script_store_job($path,$updated,$new);
sg_reject(fn()=>tvs_script_store_job($path,$new,$new),'stale generation cannot overwrite approval');
file_put_contents($path,'{broken'); sg_reject(fn()=>tvs_script_store_job($path,$new),'corrupt queue rejected'); sg_expect(file_get_contents($path)==='{broken','corrupt queue preserved');
mkdir($dir.'/not-a-file'); sg_reject(fn()=>tvs_script_store_job($dir.'/not-a-file',$new),'storage failure');
sg_reject(fn()=>tvs_script_store_job('/proc/tvs-script-no-write/jobs.json',$new),'unwritable storage');
$message=tvs_script_failure('test',new RuntimeException('sensitive raw provider text'));
sg_expect(!str_contains($message,'sensitive'),'safe operator message');
foreach(glob($dir.'/*') as $file){ if(is_dir($file)) rmdir($file); else unlink($file); } rmdir($dir);
echo 'SCRIPT_GENERATION=PASS checks='.$checks."\n";
