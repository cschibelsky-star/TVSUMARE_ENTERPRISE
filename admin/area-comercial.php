<?php
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/includes/home_feature_ads.php';
require_once dirname(__DIR__).'/includes/premium_ads_upload.php';
$activeAdmin='comercial';
$file=dirname(__DIR__).'/data/anuncios_pedidos.json';
function tvs_ads_read($file){$rows=is_file($file)?json_decode((string)@file_get_contents($file),true):[];return is_array($rows)?array_values(array_filter($rows,'is_array')):[];}
function tvs_ads_save($file,$rows){return file_put_contents($file,json_encode(array_values($rows),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)!==false;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$orders=tvs_ads_read($file);
$error=''; $saved=false;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $action=(string)($_POST['action']??'');
  $id=(string)($_POST['id']??'');
  if($action==='create'){
    $company=trim(strip_tags((string)($_POST['company']??'')));
    $link=trim((string)($_POST['link']??''));
    $start=trim((string)($_POST['start_at']??''));
    $end=trim((string)($_POST['end_at']??''));
    if($company===''||strlen($company)>140)$error='Informe o nome do anunciante (até 140 caracteres).';
    elseif($link!=='' && tvs_premium_link($link)==='')$error='Informe URL http(s) válida ou caminho interno iniciado por /.';
    elseif(($start!==''&&!strtotime($start))||($end!==''&&!strtotime($end))||($start!==''&&$end!==''&&strtotime($start)>strtotime($end)))$error='Confira início e fim da campanha.';
    else{
      $upload=tvs_premium_upload($_FILES['media']??[]);
      if(!$upload['ok'])$error=$upload['error'];
      else{
        $row=[
          'id'=>'premium_'.bin2hex(random_bytes(10)), 'company'=>$company,
          'plan'=>'Destaque Premium','placement'=>'Home Premium','link'=>$link,
          'start_at'=>$start,'end_at'=>$end,'status'=>'recebido',
          'approved_at'=>'','created_at'=>date('c')
        ];
        if($upload['kind']==='video'){$row['video']=$upload['path'];$row['video_duration']=$upload['duration'];}
        else $row['banner']=$upload['path'];
        $orders[]=$row;
        $saved=tvs_ads_save($file,$orders);
        if(!$saved)$error='Falha ao salvar a campanha.';
      }
    }
  } else {
    $found=false;
    foreach($orders as &$o){
      if((string)($o['id']??'')!==$id)continue;
      $found=true;
      if($action==='upload'){
        if(($o['status']??'')==='publicado'){$error='Pause esta campanha antes de trocar a mídia.';break;}
        $upload=tvs_premium_upload($_FILES['media']??[]);
        if(!$upload['ok']){$error=$upload['error'];break;}
        unset($o['video'],$o['banner'],$o['video_duration']);
        if($upload['kind']==='video'){$o['video']=$upload['path'];$o['video_duration']=$upload['duration'];}
        else $o['banner']=$upload['path'];
        $o['status']='recebido';$o['approved_at']='';
      } elseif($action==='paid') {
        $o['is_paid']=true;
        if(($o['status']??'')==='recebido')$o['status']='pagamento confirmado';
      } elseif($action==='approve') {
        $o['status']='aprovado';$o['approved_at']=date('c');
      } elseif($action==='publish') {
        $approved=(!empty($o['approved_at'])||($o['status']??'')==='aprovado');
        $validVideo=tvs_premium_local_media($o['video']??'','video')!==''&&
          (float)($o['video_duration']??0)>0&&(float)($o['video_duration']??0)<=10;
        $validImage=tvs_premium_local_media($o['banner']??'','image')!=='';
        if(!$approved)$error='Aprove o material antes de publicar.';
        elseif(!$validVideo&&!$validImage)$error='Envie mídia validada: imagem ou MP4 de até 10 segundos.';
        else $o['status']='publicado';
      } elseif($action==='pause'){
        if(($o['status']??'')==='publicado')$o['status']='pausado';
      } elseif($action==='delete'){
        // Existing administrative removal, limited to an explicitly selected campaign.
        $orders=array_values(array_filter($orders,fn($row)=>(string)($row['id']??'')!==$id));
      } else {$error='Ação não reconhecida.';}
      break;
    }
    unset($o);
    if(!$found&&!$error)$error='Campanha não encontrada.';
    if(!$error)$saved=tvs_ads_save($file,$orders);
    if(!$saved&&!$error)$error='Falha ao salvar a atualização.';
  }
  if($saved){header('Location: area-comercial.php?ok=1');exit;}
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Área Comercial | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=41"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main">
<div class="top"><div><span class="eyebrow">Anunciantes</span><h1>Área Comercial</h1><p class="muted">Mídia premium: banners ou MP4 de no máximo 10 segundos. Publicação somente após aprovação.</p></div><a class="btn secondary" target="_blank" rel="noopener" href="../anuncie.php">Ver planos</a></div>
<?php if(isset($_GET['ok'])):?><div class="notice">Operação registrada.</div><?php endif;?>
<?php if($error):?><div class="notice error"><?=h($error)?></div><?php endif;?>
<section class="box"><h2>Novo anúncio premium — Home 50%</h2><p class="muted">A ordem de exibição é aleatória e alterna entre campanhas publicadas. Vídeos são reproduzidos sem áudio automático; a duração é validada no servidor.</p>
<form method="post" enctype="multipart/form-data" class="form-grid" style="display:grid;gap:13px;max-width:900px">
<?=tvs_csrf_field()?><input type="hidden" name="action" value="create">
<label>Anunciante<input name="company" maxlength="140" required placeholder="Empresa ou campanha"></label>
<label>Link do anúncio<input type="text" name="link" placeholder="https://exemplo.com.br ou /guia.php"></label>
<label>Início (opcional)<input type="datetime-local" name="start_at"></label>
<label>Fim (opcional)<input type="datetime-local" name="end_at"></label>
<label>Banner JPG/PNG/WEBP ou vídeo MP4 (até 10 segundos e 20 MB)<input type="file" name="media" accept="image/jpeg,image/png,image/webp,video/mp4,.mp4" required></label>
<button class="btn orange" type="submit">Cadastrar para aprovação</button>
</form></section>
<section class="box"><h2>Pedidos e campanhas</h2><?php if(!$orders):?><p>Nenhuma solicitação recebida.</p><?php endif;?>
<?php foreach(array_reverse($orders) as $o):
$hasVideo=!empty($o['video']);
$source=$hasVideo?($o['video']??''):($o['banner']??'');
?><article class="box" style="margin:14px 0">
<h2><?=h($o['company']??'Empresa')?></h2>
<p><b>Plano:</b> <?=h($o['plan']??'')?> • <b>Local:</b> <?=h($o['placement']??'')?> • <b>Status:</b> <?=h($o['status']??'recebido')?></p>
<?php if(!empty($o['responsible'])):?><p><b>Responsável:</b> <?=h($o['responsible'])?> • <b>WhatsApp:</b> <?=h($o['whatsapp']??'')?> • <b>E-mail:</b> <?=h($o['email']??'')?></p><?php endif;?>
<?php if(!empty($o['message'])):?><p><?=h($o['message'])?></p><?php endif;?>
<p><b>Arte:</b> <?=h($source?:'Não enviada')?><?=($hasVideo?' • duração: '.h($o['video_duration']??'não validada').'s':'')?> • <b>Comprovante:</b> <?=h($o['receipt']??'Não enviado')?></p>
<?php if(!empty($o['start_at'])||!empty($o['end_at'])):?><p><b>Programação:</b> <?=h($o['start_at']??'Imediato')?> a <?=h($o['end_at']??'Sem fim')?></p><?php endif;?>
<form method="post" class="actions"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=h($o['id']??'')?>">
<button class="btn secondary" name="action" value="paid">Marcar pago</button>
<button class="btn" name="action" value="approve">Aprovar</button>
<button class="btn orange" name="action" value="publish">Publicar</button>
<button class="btn secondary" name="action" value="pause">Pausar</button>
<button class="btn danger" name="action" value="delete" onclick="return confirm('Excluir este pedido?')">Excluir</button></form>
<?php if(($o['status']??'')!=='publicado'):?>
<form method="post" enctype="multipart/form-data" class="actions" style="margin-top:10px">
<?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=h($o['id']??'')?>"><input type="hidden" name="action" value="upload">
<label>Trocar mídia (exige nova aprovação)<input type="file" name="media" accept="image/jpeg,image/png,image/webp,video/mp4,.mp4" required></label>
<button class="btn secondary" type="submit">Enviar mídia</button></form>
<?php endif;?>
</article><?php endforeach;?></section>
</main></div></body></html>
