<?php
require_once __DIR__.'/auth.php'; require_login();
$activeAdmin='monetizacao';
$file=dirname(__DIR__).'/data/monetizacao.json';
$data=file_exists($file)?json_decode(file_get_contents($file),true):[]; if(!is_array($data)) $data=[];
$defaults=['adsense'=>'','banner_home'=>'','banner_materia'=>'','publieditorial_info'=>'Conteúdo patrocinado identificado conforme política editorial da TV Sumaré.'];
$data=array_merge($defaults,$data); $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  foreach($defaults as $k=>$v){ $data[$k]=trim($_POST[$k]??''); }
  if(!is_dir(dirname($file))) @mkdir(dirname($file),0775,true);
  file_put_contents($file,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); $msg='Configurações salvas.';
}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Monetização</title><link rel="stylesheet" href="admin.css"></head><body class="admin"><div class="layout"><?php include '_menu.php'; ?><main class="main"><h1>💰 Monetização</h1><p class="muted">Espaços preparados para AdSense, banners premium, publieditoriais e patrocinadores. Nada é ativado automaticamente.</p><?php if($msg):?><div class="notice"><?=h($msg)?></div><?php endif;?><form method="post" class="card form-grid"><?=tvs_csrf_field()?><label>Código Google AdSense / script autorizado<textarea name="adsense" rows="5"><?=h($data['adsense'])?></textarea></label><label>Banner Home premium / HTML autorizado<textarea name="banner_home" rows="4"><?=h($data['banner_home'])?></textarea></label><label>Banner matéria / HTML autorizado<textarea name="banner_materia" rows="4"><?=h($data['banner_materia'])?></textarea></label><label>Texto padrão publieditorial<textarea name="publieditorial_info" rows="3"><?=h($data['publieditorial_info'])?></textarea></label><button class="btn primary">Salvar monetização</button></form></main></div></body></html>
