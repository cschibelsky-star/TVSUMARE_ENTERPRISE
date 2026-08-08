<?php
require_once __DIR__.'/auth.php'; require_login();
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once dirname(__DIR__).'/includes/heygen_helper.php';
require_once dirname(__DIR__).'/includes/video_ai_helper.php';
$activeAdmin='tvplay';
$msg=''; $err='';

function tvp_admin_find_news($id){ foreach(tvp_read_json('noticias.json') as $n){ if(tvp_news_id($n)===$id) return $n; } return null; }
function tvp_admin_redirect($params=[]){ $q=$params?('?'.http_build_query($params)):''; header('Location: tvplay.php'.$q); exit; }
if(isset($_GET['msg'])) $msg=(string)$_GET['msg']; if(isset($_GET['err'])) $err=(string)$_GET['err'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='suggest_top3'){
    $r=tvp_generate_top_suggestions(3);
    tvp_admin_redirect(['msg'=>'Sugestões geradas: '.($r['created']??0).'.']);
  }
  if($action==='create_manual'){
    $n=tvp_admin_find_news($_POST['news_id']??'');
    if(!$n) tvp_admin_redirect(['err'=>'Notícia não encontrada.']);
    $r=tvp_create_video_job($n,'manual',false);
    tvp_admin_redirect($r['ok']?['msg'=>'Matéria enviada para produção de vídeo.']:['err'=>$r['error']??'Falha ao criar job.']);
  }
  if($action==='approve_suggestion'){
    $idx=null; $jobs=null; $job=tvp_find_job($_POST['job_id']??'',$jobs,$idx);
    if(!$job) tvp_admin_redirect(['err'=>'Job não encontrado.']);
    $jobs[$idx]['status']='roteiro_pendente'; $jobs[$idx]['approved_at']=date('c'); $jobs[$idx]['updated_at']=date('c'); tvp_save_video_jobs($jobs);
    tvp_admin_redirect(['msg'=>'Sugestão aprovada para roteiro.']);
  }
  if($action==='discard_job'){
    $idx=null; $jobs=null; $job=tvp_find_job($_POST['job_id']??'',$jobs,$idx);
    if($job){ $jobs[$idx]['status']='cancelado'; $jobs[$idx]['updated_at']=date('c'); tvp_save_video_jobs($jobs); }
    tvp_admin_redirect(['msg'=>'Item removido da fila ativa.']);
  }
  if($action==='generate_script'){
    $idx=null; $jobs=null; $job=tvp_find_job($_POST['job_id']??'',$jobs,$idx);
    if(!$job) tvp_admin_redirect(['err'=>'Job não encontrado.']);
    $script=tvp_generate_script($job); $jobs[$idx]['script']=$script; $jobs[$idx]['status']='roteiro_revisao'; $jobs[$idx]['updated_at']=date('c'); tvp_save_video_jobs($jobs);
    tvp_admin_redirect(['msg'=>'Roteiro gerado. Revise antes de enviar para a HeyGen.']);
  }
  if($action==='save_script'){
    $idx=null; $jobs=null; $job=tvp_find_job($_POST['job_id']??'',$jobs,$idx);
    if(!$job) tvp_admin_redirect(['err'=>'Job não encontrado.']);
    $jobs[$idx]['script']=tvp_polish_script(trim((string)($_POST['script']??'')),$job); $jobs[$idx]['status']='roteiro_aprovado'; $jobs[$idx]['updated_at']=date('c'); tvp_save_video_jobs($jobs);
    tvp_admin_redirect(['msg'=>'Roteiro salvo e aprovado.']);
  }
  if($action==='send_heygen'){
    $idx=null; $jobs=null; $job=tvp_find_job($_POST['job_id']??'',$jobs,$idx);
    if(!$job) tvp_admin_redirect(['err'=>'Job não encontrado.']);
    if(trim((string)($job['script']??''))==='') tvp_admin_redirect(['err'=>'Gere e aprove o roteiro antes de enviar para HeyGen.']);
    if(($job['status']??'')!=='roteiro_aprovado') tvp_admin_redirect(['err'=>'Salve/aprove o roteiro antes de enviar para HeyGen.']);
    $r=tvp_send_heygen($job);
    if(!$r['ok']) tvp_admin_redirect(['err'=>$r['error']??'Falha no envio para HeyGen.']);
    $jobs[$idx]['heygen_session_id']=$r['session_id']??''; $jobs[$idx]['heygen_video_id']=$r['video_id']??''; $jobs[$idx]['status']='gerando'; $jobs[$idx]['updated_at']=date('c'); tvp_save_video_jobs($jobs);
    tvp_admin_redirect(['msg'=>'Sessão HeyGen criada. Use Atualizar status até o vídeo ficar pronto.']);
  }
  if($action==='check_heygen'){
    $idx=null; $jobs=null; $job=tvp_find_job($_POST['job_id']??'',$jobs,$idx);
    if(!$job) tvp_admin_redirect(['err'=>'Job não encontrado.']);
    $r=tvp_check_heygen($job);
    if(!$r['ok']) tvp_admin_redirect(['err'=>$r['error']??'Falha ao consultar HeyGen.']);
    if(isset($r['progress'])) $jobs[$idx]['heygen_progress']=$r['progress'];
    if(!empty($r['video_id'])) $jobs[$idx]['heygen_video_id']=$r['video_id'];
    if(!empty($r['video_url']) || !empty($r['captioned_video_url'])){ $jobs[$idx]['video_url']=$r['video_url']??''; $jobs[$idx]['captioned_video_url']=$r['captioned_video_url']??''; $jobs[$idx]['thumb']=$r['thumb']?:($jobs[$idx]['image']??'assets/cat-cidade.svg'); $jobs[$idx]['status']='pronto'; }
    elseif(!empty($r['failure_message'])){ $jobs[$idx]['status']='erro'; $jobs[$idx]['heygen_failure']=$r['failure_message']; }
    else { $jobs[$idx]['status']='gerando'; }
    $jobs[$idx]['updated_at']=date('c'); tvp_save_video_jobs($jobs);
    tvp_admin_redirect(['msg'=>'Status atualizado.']);
  }
  if($action==='publish_video'){
    $idx=null; $jobs=null; $job=tvp_find_job($_POST['job_id']??'',$jobs,$idx);
    if(!$job) tvp_admin_redirect(['err'=>'Job não encontrado.']);
    $r=tvp_publish_video($job);
    if(!$r['ok']) tvp_admin_redirect(['err'=>$r['error']??'Falha ao publicar.']);
    $jobs[$idx]['status']='publicado'; $jobs[$idx]['published_at']=date('c'); $jobs[$idx]['updated_at']=date('c'); tvp_save_video_jobs($jobs);
    tvp_admin_redirect(['msg'=>'Vídeo publicado no TV Play.']);
  }
}

$jobs=tvp_load_video_jobs();
$active=array_values(array_filter($jobs,function($j){ return !in_array(($j['status']??''),['cancelado'],true); }));
$news=tvp_read_json('noticias.json'); usort($news,function($a,$b){ return strcmp($b['published_at']??$b['created_at']??'', $a['published_at']??$a['created_at']??''); }); $news=array_slice($news,0,60);
$cfg=function_exists('tvs_heygen_load_config')?tvs_heygen_load_config([]):[];
$stats=['sugerido'=>0,'roteiro'=>0,'gerando'=>0,'pronto'=>0,'publicado'=>0,'erro'=>0]; foreach($active as $j){ $s=$j['status']??''; if($s==='sugerido')$stats['sugerido']++; elseif(in_array($s,['roteiro_pendente','roteiro_revisao','roteiro_aprovado'],true))$stats['roteiro']++; elseif($s==='gerando')$stats['gerando']++; elseif($s==='pronto')$stats['pronto']++; elseif($s==='publicado')$stats['publicado']++; elseif($s==='erro')$stats['erro']++; }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TV Play IA</title><link rel="stylesheet" href="admin.css?v=180"><style>
.cards{display:grid;grid-template-columns:repeat(6,1fr);gap:12px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px}.card b{font-size:28px}.grid2{display:grid;grid-template-columns:minmax(320px,.9fr) minmax(420px,1.1fr);gap:16px}.job{border:1px solid #dbe5f2;border-radius:18px;padding:16px;margin:12px 0;background:#fff;box-shadow:0 10px 24px rgba(15,47,104,.05)}.pill{display:inline-block;padding:5px 9px;border-radius:999px;background:#eef2ff;color:#1d4ed8;font-size:12px;font-weight:900;margin:0 6px 6px 0}.pill.prioridade_maxima{background:#fee2e2;color:#991b1b}.pill.destaque{background:#dbeafe;color:#1d4ed8}.pill.publicavel{background:#dcfce7;color:#166534}.pill.revisao{background:#fef3c7;color:#92400e}.pill.baixa{background:#f1f5f9;color:#475569}.muted{color:#64748b}.mini{font-size:12px}.news-list{max-height:720px;overflow:auto}.news-item{border-bottom:1px solid #e5e7eb;padding:12px 0}.script{width:100%;min-height:210px;line-height:1.55}.workflow{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}.step{font-size:12px;border-radius:999px;padding:6px 10px;background:#f1f5f9;color:#64748b;font-weight:800}.step.on{background:#dbeafe;color:#1d4ed8}.step.done{background:#dcfce7;color:#166534}.step.err{background:#fee2e2;color:#991b1b}.job-actions form{display:inline}.hint{border:1px dashed #bfd2ea;background:#f8fbff;border-radius:14px;padding:10px;margin:10px 0;color:#475569}.status-line{font-size:13px;color:#475569;margin-top:6px}.btn[disabled]{opacity:.45;cursor:not-allowed}.auto-refresh{font-size:12px;color:#64748b;margin-left:8px}@media(max-width:1100px){.cards,.grid2{grid-template-columns:1fr}.card b{font-size:22px}}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Enterprise 1.3.1</span><h1>Assistente de Produção IA</h1><p class="muted" style="text-align:left">Fluxo corrigido: produzir vídeo, gerar roteiro, aprovar, enviar para HeyGen, atualizar status, publicar.</p></div><div class="actions"><a class="btn secondary" href="../videos.php" target="_blank">Ver TV Play</a><a class="btn secondary" href="heygen-diagnostico.php">Diagnóstico HeyGen</a></div></div><?php if($msg): ?><div class="notice"><?=tvp_h($msg)?></div><?php endif; ?><?php if($err): ?><div class="notice error"><?=tvp_h($err)?></div><?php endif; ?>
<section class="cards"><div class="card"><b><?=$stats['sugerido']?></b><br><small>Sugeridos</small></div><div class="card"><b><?=$stats['roteiro']?></b><br><small>Roteiros</small></div><div class="card"><b><?=$stats['gerando']?></b><br><small>Gerando</small></div><div class="card"><b><?=$stats['pronto']?></b><br><small>Prontos</small></div><div class="card"><b><?=$stats['publicado']?></b><br><small>Publicados</small></div><div class="card"><b><?=$stats['erro']?></b><br><small>Erros</small></div></section>
<section class="box" style="margin-top:16px"><h2>Produção Inteligente</h2><p class="muted">Gera até 3 sugestões com score alto e sem temas sensíveis. O envio para HeyGen só libera depois que o roteiro for salvo/aprovado.</p><p class="mini"><strong>HeyGen:</strong> <?=trim((string)($cfg['heygen_api_key']??''))!==''?'chave configurada':'sem chave'?> • <strong>Avatar:</strong> <?=tvp_h($cfg['heygen_avatar_id']??'')?> • <strong>Voz:</strong> <?=tvp_h($cfg['heygen_voice_id']??'')?></p><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="suggest_top3"><button class="btn orange">Gerar Top 3 sugestões de vídeo</button></form></section>
<div class="grid2" style="margin-top:16px"><section class="box"><h2>Produção Manual</h2><p class="muted">Use para transformar uma matéria aprovada em vídeo, mesmo fora do Top 3.</p><div class="news-list"><?php if(!$news): ?><p>Nenhuma notícia publicada ainda.</p><?php endif; ?><?php foreach($news as $n): $nid=tvp_news_id($n); $score=tvp_video_score($n); $pri=tvp_video_priority($score); ?><div class="news-item"><span class="pill <?=tvp_h($pri)?>"><?=tvp_h(strtoupper($pri))?></span><strong><?=tvp_h(tvp_news_title($n))?></strong><br><small><?=tvp_h(tvp_news_city($n).' • '.tvp_news_category($n).' • pontuação '.$score)?></small><?php if(tvp_is_sensitive_topic($n)): ?><div class="mini" style="color:#b91c1c;margin-top:4px">Revisão humana obrigatória antes de vídeo.</div><?php endif; ?><form method="post" style="margin-top:8px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="create_manual"><input type="hidden" name="news_id" value="<?=tvp_h($nid)?>"><button class="btn secondary">🎬 Produzir vídeo</button></form></div><?php endforeach; ?></div></section>
<section class="box"><h2>Fila de Produção</h2><?php if(!$active): ?><p class="muted">Nenhum vídeo na fila.</p><?php endif; ?><?php foreach($active as $j): $status=$j['status']??'fila'; $ready=!empty($j['video_url'])||!empty($j['captioned_video_url']); $hasScript=trim((string)($j['script']??''))!==''; $canSend=($status==='roteiro_aprovado' && $hasScript); ?><article class="job"><span class="pill <?=tvp_h($j['priority']??'media')?>"><?=tvp_h(strtoupper($j['priority']??'media'))?></span><span class="pill"><?=tvp_h($status)?></span><span class="pill">Score <?=tvp_h($j['score']??0)?></span><h3><?=tvp_h($j['title']??'Vídeo')?></h3><small class="muted"><?=tvp_h(($j['city']??'Região').' • '.($j['category']??'Notícia').' • '.($j['source']??''))?></small><?php if(!empty($j['presenter_profile'])): ?><div class="status-line">Apresentador: <?=tvp_h(tvp_presenter_label($j['presenter_profile']))?></div><?php endif; ?><?php if(!empty($j['heygen_session_id'])): ?><div class="mini muted">Sessão HeyGen: <?=tvp_h($j['heygen_session_id'])?> <?=isset($j['heygen_progress'])?' • '.$j['heygen_progress'].'%':''?></div><?php endif; ?><?php if(!empty($j['heygen_failure'])): ?><div class="notice error mini"><?=tvp_h($j['heygen_failure'])?></div><?php endif; ?>
<div class="workflow"><span class="step <?=in_array($status,['sugerido','roteiro_pendente','roteiro_revisao','roteiro_aprovado','gerando','pronto','publicado'],true)?'done':''?>">1 Fila</span><span class="step <?=in_array($status,['roteiro_revisao','roteiro_aprovado','gerando','pronto','publicado'],true)?'done':($status==='roteiro_pendente'?'on':'')?>">2 Roteiro</span><span class="step <?=in_array($status,['roteiro_aprovado','gerando','pronto','publicado'],true)?'done':($status==='roteiro_revisao'?'on':'')?>">3 Aprovação</span><span class="step <?=in_array($status,['gerando','pronto','publicado'],true)?'done':($status==='roteiro_aprovado'?'on':'')?>">4 HeyGen</span><span class="step <?=in_array($status,['pronto','publicado'],true)?'done':($status==='gerando'?'on':'')?>">5 Pronto</span><span class="step <?=$status==='publicado'?'done':''?>">6 Publicado</span></div>
<div class="job-actions" style="margin-top:10px"><?php if($status==='sugerido'): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="approve_suggestion"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><button class="btn">Aprovar sugestão</button></form><?php endif; ?><?php if(in_array($status,['sugerido','roteiro_pendente','roteiro_revisao'],true)): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="generate_script"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><button class="btn secondary">Gerar/Regerar roteiro</button></form><?php endif; ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="discard_job"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><button class="btn danger" onclick="return confirm('Remover da fila?')">Descartar</button></form></div>
<?php if(in_array($status,['roteiro_revisao','roteiro_aprovado'],true) || $hasScript): ?><form method="post" style="margin-top:10px"><?=tvs_csrf_field()?><input type="hidden" name="action" value="save_script"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><label>Roteiro aprovado pelo editor</label><textarea class="script" name="script"><?=tvp_h($j['script']??'')?></textarea><div class="hint">Revise o texto. Depois clique em <strong>Salvar/aprovar roteiro</strong>. Só então o botão de envio para HeyGen ficará disponível.</div><button class="btn secondary">Salvar/aprovar roteiro</button></form><?php endif; ?>
<div class="actions" style="margin-top:8px"><?php if($canSend): ?><form method="post" style="display:inline"><?=tvs_csrf_field()?><input type="hidden" name="action" value="send_heygen"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><button class="btn orange">Enviar para HeyGen</button></form><?php elseif(in_array($status,['roteiro_pendente','roteiro_revisao'],true)): ?><button class="btn orange" disabled>Enviar para HeyGen</button><span class="auto-refresh">Aprove o roteiro primeiro.</span><?php endif; ?><?php if(!empty($j['heygen_session_id'])||!empty($j['heygen_video_id'])): ?><form method="post" style="display:inline"><?=tvs_csrf_field()?><input type="hidden" name="action" value="check_heygen"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><button class="btn secondary">Atualizar status</button></form><?php endif; ?><?php if($ready): ?><form method="post" style="display:inline"><?=tvs_csrf_field()?><input type="hidden" name="action" value="publish_video"><input type="hidden" name="job_id" value="<?=tvp_h($j['id'])?>"><button class="btn">Publicar no TV Play</button></form><a class="btn secondary" target="_blank" href="<?=tvp_h(($j['captioned_video_url']??'')?:($j['video_url']??''))?>">Abrir vídeo</a><?php endif; ?></div></article><?php endforeach; ?></section></div></main></div></body></html>