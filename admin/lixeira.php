<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/monitor_lib.php';

$activeAdmin='lixeira';
$base=dirname(__DIR__).'/data';
$trash=$base.'/lixeira_noticias.json';
$nf=$base.'/noticias.json';
$logFile=$base.'/radar_log.json';
$items=tvs_read_json_file($trash);
if(!is_array($items)) $items=[];

function tvs_lixeira_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function tvs_lixeira_log($item,$status,$reason){
  global $logFile;
  $log=tvs_read_json_file($logFile);
  if(!is_array($log)) $log=[];
  $log[]=[
    'id'=>uniqid('log_'),
    'title'=>$item['title']??'Sem título',
    'source'=>$item['source']??'Fonte',
    'city'=>$item['city']??'Região',
    'status'=>$status,
    'reason'=>$reason,
    'url'=>$item['source_url']??($item['url']??''),
    'image'=>$item['image']??($item['image_url']??''),
    'image_source_type'=>$item['image_source_type']??'',
    'image_credit'=>$item['image_credit']??'',
    'image_review_required'=>$item['image_review_required']??0,
    'image_reviewed_at'=>$item['image_reviewed_at']??'',
    'created_at'=>date('c')
  ];
  $log=array_slice($log,-500);
  tvs_save_json_file($logFile,$log);
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $action=(string)($_POST['action']??'');
  $id=(string)($_POST['id']??'');

  if($action==='restore' && $id!==''){
    $restored=null; $keep=[];
    foreach($items as $it){
      if((string)($it['id']??'')===$id){
        unset($it['deleted_at']);
        $restored=$it;
      } else $keep[]=$it;
    }
    if(!$restored){ header('Location: lixeira.php?error=not_found'); exit; }

    $news=tvs_read_json_file($nf); if(!is_array($news)) $news=[];
    $already=false;
    foreach($news as $n){
      if((string)($n['id']??'')===$id){ $already=true; break; }
    }

    if(!$already){
      $news[]=$restored;
      if(!tvs_save_json_file($nf,$news)){ header('Location: lixeira.php?error=restore_write'); exit; }
    }
    if(!tvs_save_json_file($trash,$keep)){ header('Location: lixeira.php?error=trash_write'); exit; }

    tvs_lixeira_log(
      $restored,
      $already?'RESTAURACAO_JA_EXISTENTE':'RESTAURADA',
      $already
        ? 'Item removido da lixeira porque a notícia já estava publicada; duplicação evitada.'
        : 'Notícia restaurada manualmente da lixeira para publicadas.'
    );
    header('Location: lixeira.php?restored='.($already?'existing':'1')); exit;
  }

  if($action==='delete' && $id!==''){
    $deleted=null; $keep=[];
    foreach($items as $it){
      if((string)($it['id']??'')===$id){ $deleted=$it; continue; }
      $keep[]=$it;
    }
    if(!$deleted){ header('Location: lixeira.php?error=not_found'); exit; }
    if(!tvs_save_json_file($trash,array_values($keep))){ header('Location: lixeira.php?error=delete_write'); exit; }
    tvs_lixeira_log($deleted,'EXCLUIDA_DEFINITIVAMENTE','Notícia excluída definitivamente da lixeira após confirmação editorial.');
    header('Location: lixeira.php?deleted=1'); exit;
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lixeira | TV Sumaré</title>
  <link rel="stylesheet" href="admin.css?v=2.0.4">
</head>
<body>
<div class="admin">
  <?php include __DIR__.'/_menu.php'; ?>
  <main class="main">
    <div class="top">
      <div>
        <span class="eyebrow">Redação</span>
        <h1>Lixeira de notícias</h1>
        <p class="muted">Restaure conteúdos removidos por engano ou exclua definitivamente após conferência editorial. As ações ficam registradas no Log Editorial.</p>
      </div>
      <div class="actions"><a class="btn secondary" href="log-editorial.php">Ver Log Editorial</a></div>
    </div>

    <?php if(($_GET['restored']??'')==='1'): ?><div class="notice">Notícia restaurada com sucesso.</div><?php endif; ?>
    <?php if(($_GET['restored']??'')==='existing'): ?><div class="notice">A notícia já estava publicada. A duplicação foi evitada e o item foi removido da lixeira.</div><?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?><div class="notice">Notícia excluída definitivamente e registrada no Log Editorial.</div><?php endif; ?>
    <?php if(isset($_GET['error'])): ?><div class="notice error">Não foi possível concluir a operação. Nenhum conteúdo deve ser considerado restaurado/excluído até nova conferência.</div><?php endif; ?>

    <?php if(!$items): ?>
      <div class="box">Lixeira vazia.</div>
    <?php endif; ?>

    <?php foreach(array_reverse($items) as $n): ?>
      <section class="box" style="margin-top:12px">
        <small class="muted">Apagada em <?=tvs_lixeira_h(date('d/m/Y H:i',strtotime($n['deleted_at']??'now')))?> • <?=tvs_lixeira_h($n['city']??'Região')?> • <?=tvs_lixeira_h($n['category']??'Sem categoria')?></small>
        <h2><?=tvs_lixeira_h($n['title']??'Sem título')?></h2>
        <?php if(!empty($n['subtitle'])): ?><p><?=tvs_lixeira_h($n['subtitle'])?></p><?php endif; ?>
        <?php if(!empty($n['image'])): ?>
          <p class="muted">
            Imagem: <?=tvs_lixeira_h($n['image_source_type']??'legado')?>
            <?php if(!empty($n['image_credit'])): ?> • <?=tvs_lixeira_h($n['image_credit'])?><?php endif; ?>
            <?php if(!empty($n['image_reviewed_at'])): ?> • revisada em <?=tvs_lixeira_h(date('d/m/Y H:i',strtotime((string)$n['image_reviewed_at'])))?><?php endif; ?>
          </p>
        <?php endif; ?>
        <div class="actions">
          <form method="post">
            <?=tvs_csrf_field()?>
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="id" value="<?=tvs_lixeira_h($n['id']??'')?>">
            <button class="btn orange" type="submit">Restaurar</button>
          </form>
          <form method="post" onsubmit="return confirm('Excluir definitivamente esta notícia? Esta ação não poderá ser desfeita.');">
            <?=tvs_csrf_field()?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=tvs_lixeira_h($n['id']??'')?>">
            <button class="btn danger" type="submit">Excluir definitivamente</button>
          </form>
        </div>
      </section>
    <?php endforeach; ?>
  </main>
</div>
</body>
</html>