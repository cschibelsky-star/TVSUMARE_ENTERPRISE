<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';
require_once __DIR__.'/openai.php';
$activeAdmin='distribuicao_social';

function ds_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function ds_data_path($name){ return dirname(__DIR__).'/data/'.$name; }
function ds_read($name){ $p=ds_data_path($name); if(!is_file($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function ds_write($name,$rows){ $p=ds_data_path($name); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true); return file_put_contents($p,json_encode(array_values($rows),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)!==false; }
function ds_video_url($j){ foreach(['captioned_video_url','video_url','url'] as $k){ $v=trim((string)($j[$k]??'')); if($v!=='') return $v; } return ''; }
function ds_ready($j){ $u=ds_video_url($j); $s=strtolower((string)($j['status']??$j['video_status']??'')); return $u!=='' || in_array($s,['completed','complete','ready','published','pronto','video_pronto'],true); }
function ds_social_ready($j){
  if(!ds_ready($j)) return false;
  if(!empty($j['social_ready'])) return true;
  $aspect=trim((string)($j['aspect_ratio']??''));
  $orientation=trim((string)($j['orientation']??''));
  return $aspect==='9:16' || $orientation==='portrait';
}
function ds_format($j){ return ds_social_ready($j)?'vertical_9_16':'video'; }
function ds_clean($v){ return trim(preg_replace('/\s+/u',' ',strip_tags((string)$v))); }
function ds_local_copy($video){
  $title=ds_clean($video['title']??'Boletim TV Sumaré');
  $city=ds_clean($video['city']??'Sumaré');
  $script=ds_clean($video['script']??'');
  if(function_exists('mb_substr')) $excerpt=mb_substr($script,0,220,'UTF-8'); else $excerpt=substr($script,0,220);
  $caption=$title.'. '.trim($excerpt);
  if($caption!=='' && !preg_match('/[.!?]$/u',$caption)) $caption.='.';
  $caption.=' Acompanhe a TV Sumaré para mais informações da região.';
  $slug=preg_replace('/[^\p{L}\p{N}]+/u','',$city);
  $hashtags='#TVSumare #Noticias #Regiao'.($slug!==''?' #'.$slug:'');
  return ['caption'=>$caption,'hashtags'=>$hashtags,'engine'=>'fallback_local'];
}
function ds_generate_copy($video){
  global $openai_api_key,$openai_model;
  $fallback=ds_local_copy($video);
  if(trim((string)($openai_api_key??''))==='') return $fallback;
  $prompt="Você é editor de redes sociais da TV Sumaré. Prepare conteúdo para Instagram Reels e TikTok a partir deste vídeo jornalístico. Não invente fatos. Retorne SOMENTE JSON válido com caption e hashtags. A legenda deve ter linguagem natural, clara, regional e jornalística, com no máximo 500 caracteres. Hashtags: 4 a 8, relevantes, sem spam.\n\nTítulo: ".ds_clean($video['title']??'')."\nCidade: ".ds_clean($video['city']??'')."\nCategoria: ".ds_clean($video['category']??'')."\nRoteiro: ".ds_clean($video['script']??'');
  $r=tvs_openai_generate_text($openai_api_key,$prompt,['model'=>$openai_model,'max_output_tokens'=>450],22);
  if(empty($r['ok'])) return $fallback;
  $raw=trim((string)($r['text']??'')); $raw=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$raw); $j=json_decode($raw,true);
  if(!is_array($j) || trim((string)($j['caption']??''))==='' || trim((string)($j['hashtags']??''))==='') return $fallback;
  return ['caption'=>ds_clean($j['caption']),'hashtags'=>ds_clean($j['hashtags']),'engine'=>'openai'];
}

$queue=ds_read('social_distribution.json');
$videos=ds_read('videos_ia.json');
$message=''; $error='';

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();

  if(isset($_POST['prepare'])){
    $videoId=trim((string)($_POST['video_id']??''));
    $selected=null; foreach($videos as $v){ if(($v['id']??'')===$videoId){ $selected=$v; break; } }
    if(!$selected || !ds_social_ready($selected)) $error='Selecione um vídeo vertical 9:16 pronto para preparar a publicação.';
    else {
      $copy=ds_generate_copy($selected);
      $_SESSION['social_draft']=['video_id'=>$videoId,'caption'=>$copy['caption'],'hashtags'=>$copy['hashtags'],'engine'=>$copy['engine'],'created_at'=>date('c')];
      $message=$copy['engine']==='openai'?'Legenda e hashtags preparadas pela IA. Revise antes de adicionar à fila.':'Legenda e hashtags preparadas automaticamente em modo local. Revise antes de adicionar à fila.';
    }
  }

  if(isset($_POST['enqueue'])){
    $videoId=trim((string)($_POST['video_id']??''));
    $targets=[]; foreach(['instagram','tiktok'] as $t){ if(!empty($_POST[$t])) $targets[]=$t; }
    $selected=null; foreach($videos as $v){ if(($v['id']??'')===$videoId){ $selected=$v; break; } }
    if(!$selected || !ds_social_ready($selected)) $error='O vídeo selecionado não está pronto ou não é vertical 9:16.';
    elseif(!$targets) $error='Selecione pelo menos uma rede.';
    elseif(($_POST['review_confirm']??'')!=='1') $error='Confirme a revisão editorial da legenda e hashtags antes de adicionar à fila.';
    else {
      $caption=trim((string)($_POST['caption']??'')); $hashtags=trim((string)($_POST['hashtags']??'')); $engine='manual';
      if($caption==='' || $hashtags===''){
        $copy=ds_generate_copy($selected); if($caption==='') $caption=$copy['caption']; if($hashtags==='') $hashtags=$copy['hashtags']; $engine=$copy['engine'];
      }
      if($caption==='' || $hashtags==='') $error='Legenda e hashtags são obrigatórias.';
      else {
        $duplicate=false; foreach($queue as $q){ if(($q['video_id']??'')===$videoId && in_array(($q['status']??''),['aguardando_integracao','na_fila','processando'],true)){ $duplicate=true; break; } }
        if($duplicate) $error='Este vídeo já está na fila de distribuição social.';
        else {
          $now=date('c');
          $queue[]=['id'=>uniqid('social_'),'video_id'=>$videoId,'title'=>$selected['title']??'Boletim TV Sumaré IA','video_url'=>ds_video_url($selected),'format'=>ds_format($selected),'targets'=>$targets,'caption'=>$caption,'hashtags'=>$hashtags,'copy_engine'=>$engine,'status'=>'aguardando_integracao','network_status'=>array_fill_keys($targets,'aguardando_integracao'),'reviewed_at'=>$now,'created_at'=>$now,'updated_at'=>$now];
          if(ds_write('social_distribution.json',$queue)){ unset($_SESSION['social_draft']); $message='Vídeo revisado e adicionado à fila de distribuição social.'; }
          else $error='Não foi possível gravar a fila de distribuição social.';
        }
      }
    }
  }
}

$ready=array_values(array_filter($videos,'ds_social_ready'));
usort($ready,function($a,$b){ return strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')); });
$draft=is_array($_SESSION['social_draft']??null)?$_SESSION['social_draft']:[];
$selectedId=$draft['video_id']??($ready[0]['id']??'');
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Distribuição Social | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=12"><style>.review-confirm{padding:12px;border:1px solid #fed7aa;background:#fff7ed;border-radius:12px;margin:14px 0}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Redes Sociais</span><h1>Central de Distribuição Social</h1><p class="muted">Vídeo vertical pronto → IA prepara legenda e hashtags → revisão humana → fila editorial → APIs oficiais.</p></div></div><?php if($message): ?><div class="notice"><strong><?=ds_h($message)?></strong></div><?php endif; ?><?php if($error): ?><div class="notice error"><strong><?=ds_h($error)?></strong></div><?php endif; ?>
<div class="box" style="margin-top:14px"><h2>Novo envio</h2><?php if(!$ready): ?><p>Nenhum vídeo vertical 9:16 do Repórter IA está pronto no momento.</p><?php else: ?><form method="post" class="form"><?=tvs_csrf_field()?><label>Vídeo vertical pronto</label><select name="video_id" required><?php foreach($ready as $v): ?><option value="<?=ds_h($v['id']??'')?>" <?=($v['id']??'')===$selectedId?'selected':''?>><?=ds_h($v['title']??'Boletim TV Sumaré IA')?> — <?=ds_h($v['city']??'Região')?> — 9:16</option><?php endforeach; ?></select><div class="grid2"><label><input type="checkbox" name="instagram" value="1" checked> Instagram Reels</label><label><input type="checkbox" name="tiktok" value="1" checked> TikTok</label></div><div class="actions"><button class="btn secondary" name="prepare" value="1">Gerar legenda + hashtags com IA</button></div><label>Legenda</label><textarea name="caption" rows="4" placeholder="A IA prepara a legenda; revise antes de adicionar à fila."><?=ds_h($draft['caption']??'')?></textarea><label>Hashtags</label><input name="hashtags" value="<?=ds_h($draft['hashtags']??'')?>" placeholder="Hashtags relevantes"><div class="review-confirm"><label><input type="checkbox" name="review_confirm" value="1"> Confirmo que revisei o vídeo, a legenda e as hashtags para publicação social.</label></div><button class="btn orange" name="enqueue" value="1">Adicionar à fila editorial</button></form><?php endif; ?></div>
<div class="box" style="margin-top:14px"><h2>Fila editorial</h2><?php if(!$queue): ?><p>Nenhum envio na fila.</p><?php else: ?><div style="overflow:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th align="left">Vídeo</th><th align="left">Redes</th><th align="left">Status</th><th align="left">Conteúdo</th><th align="left">Criado</th></tr></thead><tbody><?php foreach(array_reverse($queue) as $q): ?><tr><td style="padding:10px 4px;border-top:1px solid #e5e7eb"><strong><?=ds_h($q['title']??'')?></strong><br><small><?=ds_h($q['format']??'')?></small></td><td style="padding:10px 4px;border-top:1px solid #e5e7eb"><?=ds_h(implode(', ',array_map('ucfirst',$q['targets']??[])))?></td><td style="padding:10px 4px;border-top:1px solid #e5e7eb"><?=ds_h($q['status']??'')?></td><td style="padding:10px 4px;border-top:1px solid #e5e7eb"><small><?=ds_h($q['caption']??'')?><br><?=ds_h($q['hashtags']??'')?></small></td><td style="padding:10px 4px;border-top:1px solid #e5e7eb"><?=ds_h($q['created_at']??'')?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
</main></div></body></html>