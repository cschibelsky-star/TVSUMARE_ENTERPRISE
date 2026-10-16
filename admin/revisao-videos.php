<?php
require_once __DIR__.'/auth.php'; require_login();
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/includes/video_ai_helper.php';
require_once dirname(__DIR__).'/includes/youtube_oauth.php';
$activeAdmin='revisao_videos';

function rv_redirect($params=[]){
  $q=$params?('?'.http_build_query($params)):'';
  header('Location: /admin/revisao-videos.php'.$q); exit;
}
function rv_find_job($id,&$jobs=null,&$idx=null){
  return tvp_find_job($id,$jobs,$idx);
}
function rv_generation_started_ts($job){
  foreach(['generation_requested_at','media_requested_at','heygen_requested_at','created_at'] as $key){
    $raw=trim((string)($job[$key]??''));
    if($raw!=='' && ($ts=strtotime($raw))) return $ts;
  }
  return time();
}
function rv_generation_timed_out($job,$seconds=1800){
  return (time()-rv_generation_started_ts($job))>=$seconds;
}
function rv_job_engine($job){
  $engine=strtolower(trim((string)($job['video_engine']??'')));
  if($engine==='heygen') return 'heygen';
  if(trim((string)($job['heygen_session_id']??''))!=='' || trim((string)($job['heygen_video_id']??''))!=='') return 'heygen';
  if(!empty($job['veo_operations']) && empty($job['media_operations'])) return 'veo';
  if(in_array($engine,['orchestrated','centro_ia','ia'],true) || !empty($job['media_operations'])) return 'orchestrated';
  if($engine==='veo') return 'veo';
  return $engine!==''?$engine:'orchestrated';
}
function rv_sync_job($job,&$jobs,$idx){
  $engine=rv_job_engine($job);
  if(($job['status']??'')!=='gerando') return $job;
  if($engine==='heygen'){
    $r=tvp_check_heygen($job);
    if(!empty($r['ok'])){
      if(isset($r['progress'])) $jobs[$idx]['heygen_progress']=$r['progress'];
      if(!empty($r['video_id'])) $jobs[$idx]['heygen_video_id']=$r['video_id'];
      if(!empty($r['video_url']) || !empty($r['captioned_video_url'])){
        $jobs[$idx]['video_url']=$r['video_url']??'';
        $jobs[$idx]['captioned_video_url']=$r['captioned_video_url']??'';
        $jobs[$idx]['thumb']=$r['thumb']?:($jobs[$idx]['image']??'assets/cat-cidade.svg');
        $jobs[$idx]['status']='revisao_video';
        $jobs[$idx]['review_ready_at']=date('c');
      } elseif(!empty($r['failure_message'])){
        $jobs[$idx]['status']='erro';
        $jobs[$idx]['heygen_failure']=$r['failure_message'];
      } elseif(rv_generation_timed_out($job)){
        $jobs[$idx]['status']='erro';
        $jobs[$idx]['heygen_failure']='Geração não concluída dentro de 30 minutos. Nenhuma nova geração foi iniciada automaticamente para evitar consumo duplicado de créditos.';
      }
      $jobs[$idx]['last_provider_check_at']=date('c');
      $jobs[$idx]['updated_at']=date('c');
      tvp_save_video_jobs($jobs);
      return $jobs[$idx];
    }
    $jobs[$idx]['status']='erro';
    $jobs[$idx]['heygen_failure']=trim((string)($r['error']??'Falha ao consultar o provider da geração.'));
    $jobs[$idx]['last_provider_check_at']=date('c');
    $jobs[$idx]['updated_at']=date('c');
    tvp_save_video_jobs($jobs);
    return $jobs[$idx];
  }
  $r=$engine==='veo' ? tvp_check_veo($job) : tvp_check_video_orchestrated($job);
  if(!empty($r['operations'])) $jobs[$idx]['media_operations']=$r['operations'];
  if(isset($r['progress'])) $jobs[$idx]['media_progress']=$r['progress'];
  if(!empty($r['models'])) $jobs[$idx]['media_models']=$r['models'];
  if(!empty($r['providers'])) $jobs[$idx]['media_providers']=$r['providers'];
  $jobs[$idx]['last_provider_check_at']=date('c');

  if(!empty($r['ok'])){
    if(!empty($r['video_url'])){
      $jobs[$idx]['video_url']=$r['video_url'];
      $jobs[$idx]['status']='revisao_video';
      $jobs[$idx]['review_ready_at']=date('c');
    } elseif(rv_generation_timed_out($job)){
      $jobs[$idx]['status']='erro';
      $jobs[$idx]['media_failure']='A geração da imagem/cena não foi concluída dentro de 30 minutos. Nenhuma nova geração foi iniciada automaticamente para evitar novo consumo de créditos.';
    } else {
      $jobs[$idx]['status']='gerando';
    }
    $jobs[$idx]['updated_at']=date('c');
    tvp_save_video_jobs($jobs);
    return $jobs[$idx];
  }

  $jobs[$idx]['status']='erro';
  $jobs[$idx]['media_failure']=trim((string)($r['error']??'Falha na geração da imagem/cena pelo Centro IA.'));
  $jobs[$idx]['updated_at']=date('c');
  tvp_save_video_jobs($jobs);
  return $jobs[$idx];
}

$msg=(string)($_GET['msg']??''); $err=(string)($_GET['err']??'');
$focus=trim((string)($_GET['job_id']??''));

if($_SERVER['REQUEST_METHOD']==='POST'){
  tvs_verify_csrf();
  $action=(string)($_POST['action']??'');
  $jobId=(string)($_POST['job_id']??'');
  $idx=null; $jobs=null; $job=rv_find_job($jobId,$jobs,$idx);
  if(!$job) rv_redirect(['err'=>'Vídeo não encontrado.']);

  if($action==='approve_tvplay'){
    if(!in_array((string)($job['status']??''),['revisao_video','video_aprovado'],true)) rv_redirect(['job_id'=>$jobId,'err'=>'O vídeo ainda não está pronto para revisão.']);
    $r=tvp_publish_video($job);
    if(empty($r['ok'])) rv_redirect(['job_id'=>$jobId,'err'=>$r['error']??'Falha ao publicar no TV Play.']);
    $jobs[$idx]['status']='publicado';
    $jobs[$idx]['video_review_status']='aprovado';
    $jobs[$idx]['reviewed_at']=date('c');
    $jobs[$idx]['published_at']=date('c');
    $jobs[$idx]['updated_at']=date('c');
    tvp_save_video_jobs($jobs);
    rv_redirect(['job_id'=>$jobId,'msg'=>'Vídeo aprovado e publicado no TV Play.']);
  }

  if($action==='approve_youtube'){
    if(!in_array((string)($job['status']??''),['revisao_video','video_aprovado','publicado'],true)) rv_redirect(['job_id'=>$jobId,'err'=>'O vídeo ainda não está pronto para revisão.']);
    $r=tvs_youtube_upload_branded($job);
    if(empty($r['ok'])) rv_redirect(['job_id'=>$jobId,'err'=>$r['error']??'Falha ao publicar no YouTube.']);
    $jobs[$idx]['youtube_video_id']=$r['youtube_video_id']??'';
    $jobs[$idx]['youtube_url']=$r['url']??'';
    $jobs[$idx]['youtube_status']='publicado';
    $jobs[$idx]['youtube_published_at']=date('c');
    $jobs[$idx]['video_review_status']='aprovado';
    if(($jobs[$idx]['status']??'')!=='publicado') $jobs[$idx]['status']='video_aprovado';
    $jobs[$idx]['reviewed_at']=date('c');
    $jobs[$idx]['updated_at']=date('c');
    tvp_save_video_jobs($jobs);
    rv_redirect(['job_id'=>$jobId,'msg'=>'Vídeo aprovado e enviado ao YouTube.']);
  }

  if($action==='approve_both'){
    if(!in_array((string)($job['status']??''),['revisao_video','video_aprovado'],true)) rv_redirect(['job_id'=>$jobId,'err'=>'O vídeo ainda não está pronto para revisão.']);
    $r1=tvp_publish_video($job);
    if(empty($r1['ok'])) rv_redirect(['job_id'=>$jobId,'err'=>$r1['error']??'Falha ao publicar no TV Play.']);
    $r2=tvs_youtube_upload_branded($job);
    if(empty($r2['ok'])) rv_redirect(['job_id'=>$jobId,'err'=>'TV Play publicado, mas YouTube falhou: '.($r2['error']??'erro desconhecido')]);
    $jobs[$idx]['youtube_video_id']=$r2['youtube_video_id']??'';
    $jobs[$idx]['youtube_url']=$r2['url']??'';
    $jobs[$idx]['youtube_status']='publicado';
    $jobs[$idx]['youtube_published_at']=date('c');
    $jobs[$idx]['status']='publicado';
    $jobs[$idx]['video_review_status']='aprovado';
    $jobs[$idx]['reviewed_at']=date('c');
    $jobs[$idx]['published_at']=date('c');
    $jobs[$idx]['updated_at']=date('c');
    tvp_save_video_jobs($jobs);
    rv_redirect(['job_id'=>$jobId,'msg'=>'Vídeo aprovado e publicado no TV Play e YouTube.']);
  }

  if($action==='reject'){
    $jobs[$idx]['status']='video_reprovado';
    $jobs[$idx]['video_review_status']='reprovado';
    $jobs[$idx]['review_reason']=tvp_clean($_POST['reason']??'Reprovado na checagem editorial.');
    $jobs[$idx]['reviewed_at']=date('c');
    $jobs[$idx]['updated_at']=date('c');
    tvp_save_video_jobs($jobs);
    rv_redirect(['msg'=>'Vídeo reprovado e retirado da fila de publicação.']);
  }

  if($action==='delete_review'){
    $jobs[$idx]['status']='video_excluido';
    $jobs[$idx]['video_review_status']='excluido';
    $jobs[$idx]['deleted_at']=date('c');
    $jobs[$idx]['deleted_reason']=tvp_clean($_POST['reason']??'Excluído manualmente na revisão de vídeos.');
    $jobs[$idx]['local_file_preserved']=true;
    $jobs[$idx]['updated_at']=date('c');
    tvp_save_video_jobs($jobs);
    rv_redirect(['msg'=>'Vídeo retirado da revisão. O arquivo foi preservado para auditoria e não será publicado.']);
  }
}

$jobs=tvp_load_video_jobs();
$changed=false;
foreach($jobs as $i=>$j){
  if(($j['status']??'')==='pronto'){
    $jobs[$i]['status']='revisao_video';
    $jobs[$i]['review_ready_at']=$jobs[$i]['review_ready_at']??date('c');
    $jobs[$i]['updated_at']=date('c');
    $changed=true;
  }
}
if($changed) tvp_save_video_jobs($jobs);

if($focus!==''){
  $idx=null; $all=null; $job=rv_find_job($focus,$all,$idx);
  if($job && ($job['status']??'')==='gerando') rv_sync_job($job,$all,$idx);
}

/* Atualiza todos os jobs em produção. Assim a página funciona também quando
   aberta diretamente pelo menu, sem depender de ?job_id=... */
$allJobs=tvp_load_video_jobs();
foreach($allJobs as $i=>$j){
  if(($j['status']??'')==='gerando'){
    rv_sync_job($j,$allJobs,$i);
    $allJobs=tvp_load_video_jobs();
  }
}

$jobs=tvp_load_video_jobs();
$review=array_values(array_filter($jobs,fn($j)=>in_array((string)($j['status']??''),['gerando','revisao_video','video_aprovado','erro'],true)));
usort($review,fn($a,$b)=>strcmp((string)($b['updated_at']??$b['created_at']??''),(string)($a['updated_at']??$a['created_at']??'')));
function rv_status_label($s){
  return match($s){
    'gerando'=>'Em produção',
    'revisao_video'=>'Pronto para revisão',
    'video_aprovado'=>'Aprovado',
    'erro'=>'Erro',
    default=>$s
  };
}
function rv_progress($j){
  if(($j['status']??'')==='revisao_video') return 100;
  if(rv_job_engine($j)==='heygen') return max(8,(int)($j['heygen_progress']??35));
  return max(8,(int)($j['media_progress']??35));
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Revisão de Vídeos | TV Sumaré</title>
<link rel="stylesheet" href="admin.css?v=181">
<style>
.review-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:16px}
.video-card{background:#fff;border:1px solid #dbe5f2;border-radius:20px;padding:16px;box-shadow:0 10px 26px rgba(15,47,104,.06)}
.video-card.focus{outline:3px solid #2563eb}
.preview{aspect-ratio:16/9;background:linear-gradient(135deg,#071a38,#123c78);border-radius:16px;overflow:hidden;display:flex;align-items:center;justify-content:center;position:relative}
.preview video{width:100%;height:100%;object-fit:contain;background:#000}
.processing{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;gap:14px;background:radial-gradient(circle at 50% 35%,rgba(37,99,235,.35),transparent 45%),linear-gradient(135deg,#07152d,#0d3a70)}
.spinner{width:54px;height:54px;border:5px solid rgba(255,255,255,.2);border-top-color:#ff8a00;border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.progress{height:9px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin:10px 0}
.progress>span{display:block;height:100%;background:linear-gradient(90deg,#2563eb,#ff8a00);transition:width .4s ease}
.meta{display:flex;gap:7px;flex-wrap:wrap;margin:10px 0}.meta span{background:#eef4ff;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;color:#24436e}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.actions form{display:inline-flex;gap:6px}
.reason{width:100%;margin-top:8px}
.empty{padding:40px;text-align:center;background:#fff;border:1px dashed #b9c9dc;border-radius:18px}
</style>
</head>
<body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main">
<div class="top"><div><span class="eyebrow">Controle Editorial</span><h1>Revisão de Vídeos</h1><p class="muted" style="text-align:left">Acompanhe a geração em tempo real. Nenhum vídeo gerado por IA é publicado automaticamente.</p></div><div class="actions"><a class="btn secondary" href="tvplay.php">Voltar ao TV Play</a><a class="btn secondary" href="videos.php">Ver vídeos publicados</a></div></div>
<?php if($msg): ?><div class="notice"><?=tvp_h($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="notice error"><?=tvp_h($err)?></div><?php endif; ?>
<?php if(!$review): ?><div class="empty"><h2>Nenhum vídeo aguardando checagem</h2><p class="muted">Quando um vídeo for enviado para geração, ele aparecerá aqui automaticamente.</p></div><?php endif; ?>
<div class="review-grid">
<?php foreach($review as $j): $st=(string)($j['status']??''); $ready=$st==='revisao_video'||$st==='video_aprovado'; $p=rv_progress($j); $url=trim((string)(($j['captioned_video_url']??'')?:($j['video_url']??''))); ?>
<article class="video-card <?=$focus===($j['id']??'')?'focus':''?>">
  <div class="preview">
    <?php if($ready && $url!==''): ?><video controls preload="metadata" src="<?=tvp_h(tvp_abs_url($url))?>"></video>
    <?php elseif($st==='erro'): ?><div class="processing"><strong>Falha na geração</strong><span><?=tvp_h((string)($j['media_failure']??$j['heygen_failure']??'O provider não concluiu a geração.'))?></span></div>
    <?php else: ?><div class="processing"><div class="spinner"></div><strong><?=tvp_h(rv_status_label($st))?></strong><span><?=tvp_h((string)($j['title']??'Vídeo em produção'))?></span></div><?php endif; ?>
  </div>
  <div class="progress"><span style="width:<?=$p?>%"></span></div>
  <div class="meta"><span><?=tvp_h(rv_status_label($st))?></span><span><?=tvp_h($j['city']??'Região')?></span><span><?=tvp_h($j['category']??'Vídeo')?></span><span><?=tvp_h(match(rv_job_engine($j)){'heygen'=>'HeyGen','veo'=>'VEO',default=>'Centro IA'})?></span></div>
  <h2><?=tvp_h($j['title']??'Sem título')?></h2>
  <?php if(!empty($j['script'])): ?><p class="muted"><?=tvp_h(tvp_substr($j['script'],0,280))?></p><?php endif; ?>
  <?php if($st==='gerando'): ?><p class="muted">A página atualiza automaticamente enquanto o vídeo é processado.</p><?php endif; ?>
  <?php if($st==='erro'): ?><div class="notice error"><?=tvp_h($j['media_failure']??$j['heygen_failure']??'Falha na geração.')?></div><?php endif; ?>
  <?php if($ready): ?><div class="actions">
    <form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="approve_tvplay"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><button class="btn">Publicar no TV Play</button></form>
    <form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="approve_youtube"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><button class="btn secondary">Publicar no YouTube</button></form>
    <form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="approve_both"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><button class="btn secondary">Publicar nos dois</button></form>
  </div>
  <form method="post" style="margin-top:10px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="reject"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><input class="reason" name="reason" maxlength="220" placeholder="Motivo da reprovação"><button class="btn danger" style="margin-top:8px" onclick="return confirm('Reprovar este vídeo? Ele não será publicado e o arquivo será preservado para auditoria.')">Reprovar / retirar da fila</button></form>
  <?php endif; ?>
  <?php if(in_array($st,['gerando','revisao_video','video_aprovado','erro'],true)): ?>
  <form method="post" style="margin-top:10px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="delete_review"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><input class="reason" name="reason" maxlength="220" placeholder="Motivo da exclusão (opcional)"><button class="btn danger" style="margin-top:8px" onclick="return confirm('Retirar este vídeo da revisão? Ele não será publicado. O arquivo será preservado para auditoria.')">Excluir da revisão</button></form>
  <?php endif; ?>
</article>
<?php endforeach; ?>
</div>
</main></div>
<?php if(array_filter($review,fn($j)=>(string)($j['status']??'')==='gerando')): ?>
<script>setTimeout(function(){location.reload()},12000);</script>
<?php endif; ?>
</body></html>
