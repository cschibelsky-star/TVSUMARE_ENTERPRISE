<?php
require_once __DIR__.'/auth.php'; require_login(); $activeAdmin='destaques';
require_once dirname(__DIR__).'/includes/home_feature_ads.php';
$file=dirname(__DIR__).'/data/home_destaques.json';
$config=is_file($file)?json_decode((string)@file_get_contents($file),true):[];
if(!is_array($config))$config=[];
$error='';$saved=false;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $mode=(string)($_POST['mode']??'eleicoes');
  if(!in_array($mode,['eleicoes','noticia','personalizado'],true))$mode='eleicoes';
  $title=trim(strip_tags((string)($_POST['title']??'')));
  $description=trim(strip_tags((string)($_POST['description']??'')));
  $url=trim((string)($_POST['url']??''));
  if($mode==='personalizado' && ($title===''||mb_strlen($title)>110))$error='Título é obrigatório e deve ter até 110 caracteres.';
  elseif(mb_strlen($description)>350)$error='Descrição deve ter no máximo 350 caracteres.';
  elseif($url!=='' && tvs_premium_link($url)==='')$error='Informe uma URL https:// válida ou um caminho interno iniciado por /.';
  if(!$error){
    $config=['mode'=>$mode,'title'=>$title,'description'=>$description,'url'=>$url,'updated_at'=>date('c')];
    $encoded=json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if($encoded===false || file_put_contents($file,$encoded,LOCK_EX)===false)$error='Falha ao salvar as configurações.';
    else $saved=true;
  }
}
function hd($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$mode=$config['mode']??'eleicoes';
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Destaques da Home | TV Sumaré</title><link rel="stylesheet" href="admin.css"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><h1>Destaque editorial da Home</h1><p class="muted">Metade esquerda do painel premium. A metade direita é gerenciada na Área Comercial. Alterar esta configuração não altera as matérias publicadas.</p>
<?php if($error):?><div class="notice error"><?=hd($error)?></div><?php endif;?>
<?php if($saved):?><div class="notice">Destaque editorial salvo.</div><?php endif;?>
<form method="post" class="box" style="display:grid;gap:16px;max-width:850px">
<?=tvs_csrf_field()?>
<label>Conteúdo em destaque<select name="mode" required>
<option value="eleicoes" <?=$mode==='eleicoes'?'selected':''?>>Eleições 2026 (painel TSE atual)</option>
<option value="noticia" <?=$mode==='noticia'?'selected':''?>>Notícia principal aprovada</option>
<option value="personalizado" <?=$mode==='personalizado'?'selected':''?>>Destaque especial (manual)</option></select></label>
<label>Título para destaque especial<input type="text" name="title" maxlength="110" value="<?=hd($config['title']??'')?>"></label>
<label>Descrição para destaque especial<textarea name="description" maxlength="350" rows="4"><?=hd($config['description']??'')?></textarea></label>
<label>Link do destaque especial<input type="text" name="url" value="<?=hd($config['url']??'')?>" placeholder="/noticias.php ou https://exemplo.com.br"></label>
<p class="muted">O modo Eleições mantém o painel de resultados vigente; posteriormente é possível selecionar notícia ou destaque especial sem alterações no código.</p>
<button class="btn orange" type="submit">Salvar destaque</button></form></main></div></body></html>
