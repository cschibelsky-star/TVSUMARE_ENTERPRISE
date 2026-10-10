<?php
require_once __DIR__.'/auth.php'; require_login();
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/includes/video_ai_helper.php';
require_once dirname(__DIR__).'/includes/tvs_public_helpers.php';
$activeAdmin='videos_publicados';

function av_redirect($params=[]){
  $q=$params?('?'.http_build_query($params)):'';
  header('Location: /admin/videos.php'.$q); exit;
}
function av_key($v){
  $id=trim((string)($v['id']??''));
  if($id!=='') return 'id:'.$id;
  $job=trim((string)($v['ia_job_id']??''));
  if($job!=='') return 'job:'.$job;
  return 'url:'.trim((string)tvs_video_url($v));
}
function av_same($v,$key){
  return av_key($v)===$key;
}
function av_trash_append($item){
  $trash=tvp_read_json('lixeira_videos.json');
  $sig=av_key($item);
  foreach($trash as $t){ if(av_key($t)===$sig) return; }
  array_unshift($trash,$item);
  tvp_write_json('lixeira_videos.json',$trash);
}

$msg=(string)($_GET['msg']??''); $err=(string)($_GET['err']??'');

if($_SERVER['REQUEST_METHOD']==='POST'){
  tvs_verify_csrf();
  $action=(string)($_POST['action']??'');
  $key=(string)($_POST['video_key']??'');
  if($key==='') av_redirect(['err'=>'Vídeo inválido.']);

  if($action==='remove_portal'){
    $reason=tvp_clean($_POST['reason']??'Removido manualmente após publicação.');
    $found=false;
    foreach(['videos.json','videos_ia.json'] as $file){
      $items=tvp_read_json($file);
      $changed=false;
      foreach($items as $i=>$v){
        if(!av_same($v,$key)) continue;
        $found=true;
        $copy=$v;
        $copy['deleted_at']=date('c');
        $copy['deleted_reason']=$reason;
        $copy['deleted_from']='portal_publicado';
        $copy['local_file_preserved']=true;
        av_trash_append($copy);
        $items[$i]['status']='video_excluido';
        $items[$i]['deleted_at']=$copy['deleted_at'];
        $items[$i]['deleted_reason']=$reason;
        $items[$i]['deleted_from']='portal_publicado';
        $items[$i]['local_file_preserved']=true;
        $items[$i]['updated_at']=date('c');
        $changed=true;
      }
      if($changed) tvp_write_json($file,$items);
    }
    av_redirect($found?['msg'=>'Vídeo retirado do portal e preservado na lixeira para auditoria.']:['err'=>'Vídeo não encontrado nas bases.']);
  }

  if($action==='restore_portal'){
    $found=false;
    foreach(['videos.json','videos_ia.json'] as $file){
      $items=tvp_read_json($file);
      $changed=false;
      foreach($items as $i=>$v){
        if(!av_same($v,$key)) continue;
        $found=true;
        $items[$i]['status']=!empty($v['ia_job_id'])?'publicado':'active';
        unset($items[$i]['deleted_at'],$items[$i]['deleted_reason'],$items[$i]['deleted_from']);
        $items[$i]['restored_at']=date('c');
        $items[$i]['updated_at']=date('c');
        $changed=true;
      }
      if($changed) tvp_write_json($file,$items);
    }
    av_redirect($found?['msg'=>'Vídeo restaurado no portal.']:['err'=>'Vídeo não encontrado nas bases.']);
  }
}

$all=[];
foreach(['videos.json','videos_ia.json'] as $file){
  foreach(tvp_read_json($file) as $v){
    $url=tvs_video_url($v);
    if($url==='') continue;
    $v['_source_file']=$file;
    $all[]= $v;
  }
}
usort($all,fn($a,$b)=>tvs_date_ts($b)<=>tvs_date_ts($a));
$seen=[];$videos=[];
foreach($all as $v){
  $k=av_key($v);
  if(isset($seen[$k])) continue;
  $seen[$k]=1;$videos[]=$v;
}
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vídeos Publicados | TV Sumaré</title>
<link rel="stylesheet" href="admin.css?v=182">
<style>
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px}
.card{background:#fff;border:1px solid #dbe5f2;border-radius:18px;padding:16px;box-shadow:0 10px 24px rgba(15,47,104,.05)}
.preview{aspect-ratio:16/9;background:#061a38;border-radius:14px;overflow:hidden}.preview video,.preview iframe{width:100%;height:100%;border:0;background:#000}
.meta{display:flex;gap:7px;flex-wrap:wrap;margin:10px 0}.meta span{background:#eef4ff;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;color:#24436e}
.actions{display:flex;gap:8px;flex-wrap:wrap}.actions form{display:inline}
.reason{width:100%;margin:8px 0}
.removed{opacity:.72;border-style:dashed}
.warn{font-size:12px;color:#92400e;background:#fff7ed;border:1px solid #fed7aa;padding:8px 10px;border-radius:10px;margin:8px 0}
</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main">
<div class="top"><div><span class="eyebrow">Controle Editorial</span><h1>Vídeos Publicados</h1><p class="muted" style="text-align:left">Gerencie inclusive os vídeos que já foram publicados. A exclusão do portal é reversível e preserva o arquivo para auditoria.</p></div><div class="actions"><a class="btn secondary" href="revisao-videos.php">Revisão de Vídeos</a><a class="btn secondary" href="../videos.php" target="_blank">Ver página pública</a></div></div>
<?php if($msg): ?><div class="notice"><?=tvp_h($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="notice error"><?=tvp_h($err)?></div><?php endif; ?>
<div class="grid">
<?php foreach($videos as $v):
  $status=(string)($v['status']??'active');
  $removed=in_array($status,['video_excluido','excluido','removido','deleted','inactive','inativo'],true);
  $url=tvs_video_url($v); $embed=tvs_youtube_embed($url); $isMp4=preg_match('~\.mp4(?:\?|$)~i',$url);
  $key=av_key($v);
?>
<article class="card <?=$removed?'removed':''?>">
  <div class="preview"><?php if($embed): ?><iframe src="<?=tvp_h($embed)?>" allowfullscreen></iframe><?php elseif($isMp4): ?><video controls preload="metadata" src="<?=tvp_h(tvp_abs_url($url))?>"></video><?php endif; ?></div>
  <div class="meta"><span><?=tvp_h($removed?'Retirado do portal':'Publicado')?></span><span><?=tvp_h($v['city']??'Região')?></span><span><?=tvp_h($v['category']??'Vídeo')?></span></div>
  <h2><?=tvp_h($v['title']??'Vídeo TV Sumaré')?></h2>
  <?php if(!empty($v['description'])): ?><p class="muted"><?=tvp_h(tvp_substr($v['description'],0,220))?></p><?php endif; ?>
  <?php if(!empty($v['youtube_video_id']) || $embed): ?><div class="warn">Se este conteúdo também estiver no YouTube, retirar do portal não apaga o vídeo do canal. A exclusão do YouTube deve ser uma ação separada.</div><?php endif; ?>
  <div class="actions"><a class="btn secondary" href="<?=tvp_h($url)?>" target="_blank" rel="noopener">Abrir vídeo</a></div>
  <?php if(!$removed): ?>
  <form method="post" style="margin-top:10px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="remove_portal"><input type="hidden" name="video_key" value="<?=tvp_h($key)?>"><input class="reason" name="reason" maxlength="220" placeholder="Motivo da exclusão (opcional)"><button class="btn danger" onclick="return confirm('Retirar este vídeo já publicado do portal? O arquivo será preservado para auditoria.')">Excluir do portal</button></form>
  <?php else: ?>
  <form method="post" style="margin-top:10px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="restore_portal"><input type="hidden" name="video_key" value="<?=tvp_h($key)?>"><button class="btn">Restaurar no portal</button></form>
  <?php endif; ?>
</article>
<?php endforeach; ?>
</div>
</main></div></body></html>