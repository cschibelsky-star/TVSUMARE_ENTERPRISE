<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/monitor_lib.php';

$activeAdmin='lixeira';
$trash=dirname(__DIR__).'/data/lixeira_noticias.json';
$nf=dirname(__DIR__).'/data/noticias.json';
$items=tvs_read_json_file($trash);
if(!is_array($items)) $items=[];

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
    if($restored){
      $news=tvs_read_json_file($nf); if(!is_array($news)) $news=[];
      $news[]=$restored;
      tvs_save_json_file($nf,$news);
      tvs_save_json_file($trash,$keep);
    }
    header('Location: lixeira.php?restored=1'); exit;
  }

  if($action==='delete' && $id!==''){
    $items=array_values(array_filter($items,static fn($i)=>(string)($i['id']??'')!==$id));
    tvs_save_json_file($trash,$items);
    header('Location: lixeira.php?deleted=1'); exit;
  }
}

function tvs_lixeira_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lixeira | TV Sumaré</title>
  <link rel="stylesheet" href="admin.css?v=16">
</head>
<body>
<div class="admin">
  <?php include __DIR__.'/_menu.php'; ?>
  <main class="main">
    <div class="top">
      <div>
        <span class="eyebrow">Redação</span>
        <h1>Lixeira de notícias</h1>
        <p class="muted">Restaure conteúdos removidos por engano ou exclua definitivamente após conferência editorial.</p>
      </div>
    </div>

    <?php if(isset($_GET['restored'])): ?><div class="notice">Notícia restaurada com sucesso.</div><?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?><div class="notice">Notícia excluída definitivamente.</div><?php endif; ?>

    <?php if(!$items): ?>
      <div class="box">Lixeira vazia.</div>
    <?php endif; ?>

    <?php foreach(array_reverse($items) as $n): ?>
      <section class="box" style="margin-top:12px">
        <small class="muted">Apagada em <?=tvs_lixeira_h(date('d/m/Y H:i',strtotime($n['deleted_at']??'now')))?> • <?=tvs_lixeira_h($n['city']??'Região')?> • <?=tvs_lixeira_h($n['category']??'Sem categoria')?></small>
        <h2><?=tvs_lixeira_h($n['title']??'Sem título')?></h2>
        <?php if(!empty($n['subtitle'])): ?><p><?=tvs_lixeira_h($n['subtitle'])?></p><?php endif; ?>
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
