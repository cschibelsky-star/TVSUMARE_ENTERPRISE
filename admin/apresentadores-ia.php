<?php
require_once __DIR__.'/auth.php';
require_login();
$activeAdmin='apresentadores_ia';

function pai_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function pai_path(){ return dirname(__DIR__).'/data/presenter_catalog.json'; }
function pai_defaults(){
  $voice='cbdcad7a79e44262b4f4dad0a1b1fba9';
  return [
    ['id'=>'noticias','name'=>'TV Sumaré Agora','role'=>'Notícias & Serviço','tone'=>'objetivo, próximo e confiável','avatar_id'=>'a3953e35ec7a43948caa515d9d5f3e89','voice_id'=>$voice,'style_id'=>'','status'=>'nao_testado','criteria'=>[],'notes'=>''],
    ['id'=>'cidade','name'=>'TV Sumaré Cidade','role'=>'Cidade, Cultura & Agenda','tone'=>'leve, espontâneo, próximo e simpático','avatar_id'=>'22fdb234baf14540ae4e01a805b2d2a6','voice_id'=>$voice,'style_id'=>'','status'=>'nao_testado','criteria'=>[],'notes'=>''],
    ['id'=>'giro','name'=>'TV Sumaré Giro','role'=>'Giro Rápido & Destaques','tone'=>'dinâmico, humano, energético e conversacional','avatar_id'=>'ecc586fbd3e94cd4a07347c098364fc8','voice_id'=>$voice,'style_id'=>'','status'=>'nao_testado','criteria'=>[],'notes'=>''],
  ];
}
function pai_read(){
  $p=pai_path();
  if(!is_file($p)) return pai_defaults();
  $d=json_decode(file_get_contents($p),true);
  return is_array($d)&&$d?$d:pai_defaults();
}
function pai_write($rows){
  $p=pai_path(); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true);
  return file_put_contents($p,json_encode(array_values($rows),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)!==false;
}
function pai_status_label($s){ return ['nao_testado'=>'Não testado','teste_gerado'=>'Teste gerado','em_avaliacao'=>'Em avaliação','homologado'=>'Homologado'][$s]??'Não testado'; }
function pai_required_criteria(){ return ['rosto'=>'Rosto/fotorealismo','labios'=>'Sincronização labial','movimento'=>'Movimentos e gestos','voz'=>'Voz e prosódia','ritmo'=>'Ritmo/entonação','credibilidade'=>'Credibilidade jornalística']; }

$rows=pai_read(); $msg=''; $err='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $id=trim((string)($_POST['id']??''));
  foreach($rows as &$r){
    if((string)($r['id']??'')!==$id) continue;
    $r['name']=trim((string)($_POST['name']??$r['name']??''));
    $r['tone']=trim((string)($_POST['tone']??$r['tone']??''));
    $r['avatar_id']=trim((string)($_POST['avatar_id']??$r['avatar_id']??''));
    $r['voice_id']=trim((string)($_POST['voice_id']??$r['voice_id']??''));
    $r['style_id']=trim((string)($_POST['style_id']??$r['style_id']??''));
    $r['notes']=trim((string)($_POST['notes']??''));
    $criteria=[]; foreach(pai_required_criteria() as $k=>$label){ $criteria[$k]=isset($_POST['crit_'.$k]); }
    $r['criteria']=$criteria;
    $requested=trim((string)($_POST['status']??$r['status']??'nao_testado'));
    $allowed=['nao_testado','teste_gerado','em_avaliacao','homologado']; if(!in_array($requested,$allowed,true)) $requested='nao_testado';
    if($requested==='homologado' && in_array(false,array_values($criteria),true)){ $requested='em_avaliacao'; $err='Homologação bloqueada: todos os critérios precisam estar aprovados.'; }
    if($requested==='homologado' && (trim((string)$r['avatar_id'])==='' || trim((string)$r['voice_id'])==='')){ $requested='em_avaliacao'; $err='Homologação bloqueada: Avatar ID e Voice ID são obrigatórios.'; }
    $r['status']=$requested; $r['updated_at']=date('c');
    if($requested==='homologado') $r['homologated_at']=date('c');
    break;
  } unset($r);
  if(pai_write($rows)){ if($err==='') $msg='Perfil atualizado com sucesso.'; } else $err='Falha ao salvar o catálogo persistente.';
}
$homologated=count(array_filter($rows,fn($r)=>($r['status']??'')==='homologado'));
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Apresentadores IA | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=16"><style>.status{display:inline-block;padding:5px 9px;border-radius:999px;background:#eef2ff;font-weight:800;font-size:12px}.criteria{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:8px;margin:10px 0}.criteria label{display:flex;gap:8px;align-items:center}.meta{font-size:12px;color:#64748b}.card{margin-top:14px}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Repórter IA</span><h1>Catálogo de Apresentadores IA</h1><p class="muted" style="text-align:left">A IA só deve usar automaticamente perfis homologados em produção. Homologação exige avaliação humana de aparência, voz e credibilidade.</p></div><a class="btn secondary" href="heygen-diagnostico.php">Diagnóstico HeyGen</a></div>
<div class="box"><strong><?=count($rows)?> perfis cadastrados • <?=$homologated?> homologado(s)</strong><br><span class="meta">Fluxo: Não testado → Teste gerado → Em avaliação → Homologado.</span></div>
<?php if($msg): ?><div class="box" style="margin-top:12px"><strong><?=pai_h($msg)?></strong></div><?php endif; ?><?php if($err): ?><div class="box" style="margin-top:12px"><strong><?=pai_h($err)?></strong></div><?php endif; ?>
<?php foreach($rows as $r): $criteria=is_array($r['criteria']??null)?$r['criteria']:[]; ?><section class="box card"><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=pai_h($r['id']??'')?>"><div class="top"><div><h2 style="margin-bottom:4px"><?=pai_h($r['role']??'Apresentador')?></h2><span class="status"><?=pai_h(pai_status_label($r['status']??''))?></span></div></div><div class="grid2"><div><label>Nome/persona</label><input name="name" value="<?=pai_h($r['name']??'')?>"></div><div><label>Tom editorial</label><input name="tone" value="<?=pai_h($r['tone']??'')?>"></div><div><label>HeyGen Avatar ID</label><input name="avatar_id" value="<?=pai_h($r['avatar_id']??'')?>"></div><div><label>HeyGen Voice ID</label><input name="voice_id" value="<?=pai_h($r['voice_id']??'')?>"></div><div><label>HeyGen Style ID</label><input name="style_id" value="<?=pai_h($r['style_id']??'')?>" placeholder="opcional"></div><div><label>Status</label><select name="status"><?php foreach(['nao_testado','teste_gerado','em_avaliacao','homologado'] as $s): ?><option value="<?=$s?>" <?=($r['status']??'')===$s?'selected':''?>><?=pai_h(pai_status_label($s))?></option><?php endforeach; ?></select></div></div><h3>Critérios de homologação</h3><div class="criteria"><?php foreach(pai_required_criteria() as $k=>$label): ?><label><input type="checkbox" name="crit_<?=$k?>" value="1" <?=!empty($criteria[$k])?'checked':''?>> <?=pai_h($label)?></label><?php endforeach; ?></div><label>Observações da avaliação</label><textarea name="notes" rows="3"><?=pai_h($r['notes']??'')?></textarea><p class="meta">Um perfil não pode ser marcado como Homologado se algum critério estiver pendente ou se Avatar/Voice ID estiverem ausentes.</p><button class="btn orange" type="submit">Salvar avaliação</button></form></section><?php endforeach; ?>
<p style="margin-top:16px"><a class="btn secondary" href="reporter-ia.php">Repórter IA</a> <a class="btn secondary" href="boletim-ia.php">Boletim IA</a></p></main></div></body></html>
