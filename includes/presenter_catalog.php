<?php
if(!function_exists('tvs_presenter_catalog_path')){
function tvs_presenter_catalog_path(){ return dirname(__DIR__).'/data/presenter_catalog.json'; }
function tvs_presenter_catalog_defaults(){
  $voice='cbdcad7a79e44262b4f4dad0a1b1fba9';
  return [
    ['id'=>'noticias','name'=>'TV Sumaré Agora','role'=>'Notícias & Serviço','tone'=>'objetivo, próximo e confiável','avatar_id'=>'a3953e35ec7a43948caa515d9d5f3e89','voice_id'=>$voice,'style_id'=>'','status'=>'nao_testado'],
    ['id'=>'cidade','name'=>'TV Sumaré Cidade','role'=>'Cidade, Cultura & Agenda','tone'=>'leve, espontâneo, próximo e simpático','avatar_id'=>'22fdb234baf14540ae4e01a805b2d2a6','voice_id'=>$voice,'style_id'=>'','status'=>'nao_testado'],
    ['id'=>'giro','name'=>'TV Sumaré Giro','role'=>'Giro Rápido & Destaques','tone'=>'dinâmico, humano, energético e conversacional','avatar_id'=>'ecc586fbd3e94cd4a07347c098364fc8','voice_id'=>$voice,'style_id'=>'','status'=>'nao_testado'],
  ];
}
function tvs_presenter_catalog(){
  $p=tvs_presenter_catalog_path();
  if(!is_file($p)) return tvs_presenter_catalog_defaults();
  $d=json_decode((string)@file_get_contents($p),true);
  return is_array($d)&&$d?$d:tvs_presenter_catalog_defaults();
}
function tvs_presenter_get($id){ foreach(tvs_presenter_catalog() as $p){ if((string)($p['id']??'')===(string)$id) return $p; } return null; }
function tvs_presenter_find_by_avatar($avatarId){ $avatarId=trim((string)$avatarId); if($avatarId==='') return null; foreach(tvs_presenter_catalog() as $p){ if(trim((string)($p['avatar_id']??''))===$avatarId) return $p; } return null; }
function tvs_presenter_is_hml(){ $host=strtolower((string)($_SERVER['HTTP_HOST']??getenv('TVSUMARE_PUBLIC_HOST')?:'')); return str_contains($host,'-hml.') || str_contains($host,'hml.'); }
function tvs_presenter_status_label($s){ return ['nao_testado'=>'Não testado','teste_gerado'=>'Teste gerado','em_avaliacao'=>'Em avaliação','homologado'=>'Homologado'][$s]??'Não testado'; }
function tvs_presenter_allowed($profile){
  if(!is_array($profile)) return ['allowed'=>false,'reason'=>'Perfil de apresentador não encontrado.','environment'=>tvs_presenter_is_hml()?'hml':'producao'];
  $status=(string)($profile['status']??'nao_testado');
  if($status==='homologado') return ['allowed'=>true,'reason'=>'Perfil homologado.','environment'=>tvs_presenter_is_hml()?'hml':'producao'];
  if(tvs_presenter_is_hml()) return ['allowed'=>true,'reason'=>'Perfil liberado somente para homologação visual. Status: '.tvs_presenter_status_label($status).'.','environment'=>'hml'];
  return ['allowed'=>false,'reason'=>'Produção bloqueada: o apresentador ainda não está homologado. Status: '.tvs_presenter_status_label($status).'.','environment'=>'producao'];
}
function tvs_presenter_for_theme($theme){
  $requested=tvs_presenter_get($theme);
  $policy=tvs_presenter_allowed($requested);
  if($policy['allowed']) return ['profile'=>$requested,'policy'=>$policy,'fallback'=>false];
  foreach(tvs_presenter_catalog() as $p){ if(($p['status']??'')==='homologado') return ['profile'=>$p,'policy'=>tvs_presenter_allowed($p),'fallback'=>true]; }
  return ['profile'=>$requested,'policy'=>$policy,'fallback'=>false];
}
}
