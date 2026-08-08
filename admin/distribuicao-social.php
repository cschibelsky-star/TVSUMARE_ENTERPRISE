<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';
$activeAdmin='distribuicao_social';

function ds_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function ds_data_path($name){ return dirname(__DIR__).'/data/'.$name; }
function ds_read($name){ $p=ds_data_path($name); if(!is_file($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function ds_write($name,$rows){ $p=ds_data_path($name); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true); file_put_contents($p,json_encode(array_values($rows),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
function ds_video_url($j){ foreach(['captioned_video_url','video_url','url'] as $k){ $v=trim((string)($j[$k]??'')); if($v!=='') return $v; } return ''; }
function ds_ready($j){ $u=ds_video_url($j); $s=strtolower((string)($j['status']??$j['video_status']??'')); return $u!=='' || in_array($s,['completed','complete','ready','published','pronto'],true); }

$queue=ds_read('social_distribution.json');
$videos=ds_read('videos_ia.json');
$message='';

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($_POST['enqueue'])){
  tvs_verify_csrf();
  $videoId=trim((string)($_POST['video_id']??''));
  $targets=[];
  foreach(['instagram','tiktok'] as $t){ if(!empty($_POST[$t])) $targets[]=$t; }
  if($videoId!=='' && $targets){
    $selected=null; foreach($videos as $v){ if(($v['id']??'')===$videoId){ $selected=$v; break; } }
    if($selected && ds_ready($selected)){
      $queue[]=[
        'id'=>uniqid('social_'),'video_id'=>$videoId,'title'=>$selected['title']??'Boletim TV Sumaré IA',
        'video_url'=>ds_video_url($selected),'format'=>'vertical_9_16','targets'=>$targets,
        'caption'=>trim((string)($_POST['caption']??'')),'hashtags'=>trim((string)($_POST['hashtags']??'')),
        'status'=>'aguardando_integracao','network_status'=>array_fill_keys($targets,'aguardando_integracao'),
        'created_at'=>date('c'),'updated_at'=>date('c')
      ];
      ds_write('social_distribution.json',$queue);
      $message='Vídeo adicionado à fila de distribuição social.';
    } else $message='O vídeo selecionado ainda não está pronto para distribuição.';
  } else $message='Selecione um vídeo pronto e pelo menos uma rede.';
}

$ready=array_values(array_filter($videos,'ds_ready'));
usort($ready,function($a,$b){ return strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')); });
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Distribuição Social | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=10"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><h1>Central de Distribuição Social</h1><p>Fila editorial para publicar os Boletins TV Sumaré IA em Instagram Reels e TikTok. A publicação automática será ativada quando as APIs oficiais e permissões das contas estiverem conectadas.</p><?php if($message): ?><div class="box"><strong><?=ds_h($message)?></strong></div><?php endif; ?>
<div class="box" style="margin-top:14px"><h2>Novo envio</h2><?php if(!$ready): ?><p>Nenhum vídeo do Repórter IA está pronto no momento.</p><?php else: ?><form method="post" class="form"><?=tvs_csrf_field()?><label>Vídeo pronto</label><select name="video_id" required><?php foreach($ready as $v): ?><option value="<?=ds_h($v['id']??'')?>"><?=ds_h($v['title']??'Boletim TV Sumaré IA')?> — <?=ds_h($v['city']??'Região')?></option><?php endforeach; ?></select><div class="grid2"><label><input type="checkbox" name="instagram" value="1" checked> Instagram Reels</label><label><input type="checkbox" name="tiktok" value="1" checked> TikTok</label></div><label>Legenda</label><textarea name="caption" rows="4" placeholder="Resumo curto para acompanhar o vídeo"></textarea><label>Hashtags</label><input name="hashtags" placeholder="#TVSumare #Sumare #Noticias"><button class="btn orange" name="enqueue" value="1">Adicionar à fila</button></form><?php endif; ?></div>
<div class="box" style="margin-top:14px"><h2>Fila</h2><?php if(!$queue): ?><p>Nenhum envio na fila.</p><?php else: ?><div style="overflow:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th align="left">Vídeo</th><th align="left">Redes</th><th align="left">Status</th><th align="left">Criado</th></tr></thead><tbody><?php foreach(array_reverse($queue) as $q): ?><tr><td style="padding:10px 4px;border-top:1px solid #e5e7eb"><strong><?=ds_h($q['title']??'')?></strong><br><small><?=ds_h($q['format']??'')?></small></td><td style="padding:10px 4px;border-top:1px solid #e5e7eb"><?=ds_h(implode(', ',array_map('ucfirst',$q['targets']??[])))?></td><td style="padding:10px 4px;border-top:1px solid #e5e7eb"><?=ds_h($q['status']??'')?></td><td style="padding:10px 4px;border-top:1px solid #e5e7eb"><?=ds_h($q['created_at']??'')?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
</main></div></body></html>
