<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';

$activeAdmin='noticias';
$base=dirname(__DIR__).'/data';
$newsFile=$base.'/noticias.json';
$trashFile=$base.'/lixeira_noticias.json';
$logFile=$base.'/radar_log.json';

function np_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function np_log($item,$status,$reason){
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
    'created_at'=>date('c')
  ];
  $log=array_slice($log,-500);
  tvs_save_json_file($logFile,$log);
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $action=(string)($_POST['action']??'');
  $id=(string)($_POST['id']??'');

  if($action==='trash' && $id!==''){
    $news=tvs_read_json_file($newsFile); if(!is_array($news)) $news=[];
    $removed=null; $keep=[];
    foreach($news as $item){
      if((string)($item['id']??'')===$id && $removed===null){
        $removed=$item;
      } else {
        $keep[]=$item;
      }
    }

    if(!$removed){
      header('Location: noticias.php?error=not_found'); exit;
    }

    $trash=tvs_read_json_file($trashFile); if(!is_array($trash)) $trash=[];
    $removed['deleted_at']=date('c');
    $removed['deleted_from']='admin/noticias.php';
    $trash[]=$removed;

    if(!tvs_save_json_file($trashFile,array_values($trash))){
      header('Location: noticias.php?error=trash_write'); exit;
    }

    if(!tvs_save_json_file($newsFile,array_values($keep))){
      array_pop($trash);
      tvs_save_json_file($trashFile,array_values($trash));
      header('Location: noticias.php?error=news_write'); exit;
    }

    np_log($removed,'ENVIADA_PARA_LIXEIRA','Notícia removida de Publicadas e enviada para a Lixeira editorial.');
    header('Location: noticias.php?trashed=1'); exit;
  }
}

$news=tvs_read_json_file($newsFile); if(!is_array($news)) $news=[];
usort($news,function($a,$b){return strcmp((string)($b['published_at']??$b['created_at']??''),(string)($a['published_at']??$a['created_at']??''));});
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Publicadas | TV Sumaré</title>
<link rel="stylesheet" href="admin.css?v=2.0.4">
<style>.inline-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.inline-actions form{margin:0}.btn.danger{background:#b42318;color:#fff}.image-audit{margin-top:5px;font-size:12px;color:#64748b}.image-audit.warn{color:#9a3412;font-weight:700}</style>
</head>
<body>
<div class="admin">
<?php include __DIR__.'/_menu.php';?>
<main class="main">
  <div class="top">
    <div>
      <span class="eyebrow">Redação</span>
      <h1>Matérias Publicadas</h1>
      <p class="muted">Visão administrativa do conteúdo publicado, com acesso à matéria pública, validade editorial e remoção segura para a Lixeira.</p>
    </div>
    <div class="actions">
      <a class="btn" href="content-validity.php">Validade Editorial</a>
      <a class="btn secondary" href="lixeira.php">Lixeira</a>
      <a class="btn secondary" href="../noticias.php" target="_blank">Ver página pública</a>
    </div>
  </div>

  <?php if(isset($_GET['trashed'])): ?><div class="notice">Notícia enviada para a Lixeira e registrada no Log Editorial.</div><?php endif; ?>
  <?php if(isset($_GET['error'])): ?><div class="notice error">Não foi possível concluir a remoção. A notícia deve ser conferida antes de qualquer nova tentativa.</div><?php endif; ?>

  <div class="admin-kpi-grid"><div class="admin-kpi"><span>Total publicadas</span><strong><?=count($news)?></strong></div></div>
  <section class="box">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Data</th><th>Cidade</th><th>Categoria</th><th>Matéria</th><th>Imagem</th><th>Ação</th></tr></thead>
        <tbody>
        <?php if(!$news):?><tr><td colspan="6">Nenhuma matéria publicada.</td></tr><?php endif;?>
        <?php foreach(array_slice($news,0,150) as $n):?>
          <tr>
            <td><?=np_h(!empty($n['published_at']??$n['created_at'])?date('d/m/Y H:i',strtotime($n['published_at']??$n['created_at'])):'')?></td>
            <td><?=np_h($n['city']??'Região')?></td>
            <td><?=np_h($n['category']??'Notícia')?></td>
            <td><strong><?=np_h($n['title']??'Sem título')?></strong><br><small><?=np_h($n['source']??'')?></small></td>
            <td>
              <?php
                $imgType=(string)($n['image_source_type']??'legado');
                $imgCredit=(string)($n['image_credit']??'');
                $imgPending=!empty($n['image_review_required']);
              ?>
              <strong><?=np_h($imgType)?></strong>
              <?php if($imgCredit!==''): ?><div class="image-audit"><?=np_h($imgCredit)?></div><?php endif; ?>
              <?php if(!empty($n['image_reviewed_at'])): ?><div class="image-audit">Revisada em <?=np_h(date('d/m/Y H:i',strtotime((string)$n['image_reviewed_at'])))?></div><?php endif; ?>
              <?php if($imgPending): ?><div class="image-audit warn">REVISÃO PENDENTE</div><?php endif; ?>
            </td>
            <td>
              <div class="inline-actions">
                <a class="btn small" href="../noticia.php?id=<?=rawurlencode((string)($n['id']??''))?>" target="_blank">Abrir</a>
                <form method="post" onsubmit="return confirm('Enviar esta notícia para a Lixeira? Ela poderá ser restaurada depois.');">
                  <?=tvs_csrf_field()?>
                  <input type="hidden" name="action" value="trash">
                  <input type="hidden" name="id" value="<?=np_h($n['id']??'')?>">
                  <button class="btn small danger" type="submit">Enviar para Lixeira</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</div>
</body>
</html>