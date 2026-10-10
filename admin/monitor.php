<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once dirname(__DIR__) . '/config.php'; require_once __DIR__ . '/gemini.php'; require_once __DIR__ . '/monitor_lib.php';
$activeAdmin='radar';
$sources=tvs_get_sources(); $created=[]; $breaking=[];
if(isset($_POST['run'])){
  tvs_verify_csrf();
  $editorialStyle=$_POST['editorial_style'] ?? 'Notícia padrão';
  $approach=$_POST['approach'] ?? 'Informativa';
  $size=$_POST['size'] ?? 'Média';
  $draftFile=dirname(__DIR__) . '/data/rascunhos.json'; $drafts=tvs_read_json_file($draftFile);
  $news=tvs_read_json_file(dirname(__DIR__) . '/data/noticias.json');
  $seen=tvs_monitor_seen_keys($drafts,$news);
  foreach($sources as $src){
    $items=tvs_capture_source_items($src); $breaking=array_merge($breaking,$items);
    foreach($items as $item){
      if(empty($item['url'])) continue;
      if(tvs_monitor_is_old($item,14)){ tvs_monitor_discard($item,'Matéria antiga acima de 14 dias'); continue; }
      if(tvs_monitor_item_is_duplicate($item,$seen)){ tvs_monitor_discard($item,'Duplicada por URL ou título similar'); continue; }
      $article=tvs_extract_article($item['url'],$item['title']);
      if(empty($article['description']) && !empty($item['description'])) $article['description']=$item['description'];
      $material="Estilo editorial solicitado: {$editorialStyle}\nCidade: ".($src['city']??'Região')."\nTipo de fonte: ".($src['type']??'regional')."\nFonte: ".($src['name']??'Fonte')."\nLink da fonte: {$article['url']}\nTítulo original: {$article['title']}\nResumo/meta: {$article['description']}\nTexto extraído:\n{$article['body']}";
      $ai=gemini_rewrite($gemini_api_key ?? '', $material, ['style'=>$editorialStyle,'approach'=>$approach,'size'=>$size,'city'=>$src['city']??'Região','source'=>$src['name']??'Fonte','source_url'=>$article['url']??'']);
      if(!$ai) $ai=tvs_local_fallback_article($src,$article);
      $ai=tvs_sanitize_ai_article($ai,$src,$article);
      $drafts[]=[
        'id'=>uniqid('draft_'),'city'=>$src['city']??'Região','source'=>$src['name']??'Fonte regional','source_type'=>$src['type']??'regional','source_url'=>$article['url'],
        'title'=>tvs_clean_text($ai['title']??$article['title']),'subtitle'=>tvs_clean_text($ai['subtitle']??($article['description']?:'Pauta regional captada pelo monitor TV Sumaré')),
        'body'=>$ai['body']??($article['body']?:'Revise a fonte antes de publicar.'),'category'=>$ai['category']??'Cidades','tags'=>$ai['tags']??[],
        'image'=>$article['image']??'','editorial_style'=>$editorialStyle,'approach'=>$approach,'size'=>$size,'summary'=>$ai['summary']??'','seo_title'=>$ai['seo_title']??'','meta_description'=>$ai['meta_description']??'','instagram_caption'=>$ai['instagram_caption']??'','whatsapp_text'=>$ai['whatsapp_text']??'','status'=>'rascunho_completo','created_at'=>date('c')
      ];
      $created[]=$item; $seen['url:'.$item['url']]=true; $seen['title:'.md5(tvs_lower($src['city']??'').'|'.tvs_monitor_title_key($item['title']??''))]=true; break;
    }
  }
  tvs_save_json_file($draftFile,$drafts); tvs_update_breaking($breaking);
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Monitor Regional</title><link rel="stylesheet" href="admin.css?v=2.0.3"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">RSS + fontes oficiais + portais regionais</span><h1>Monitor Regional</h1></div><a class="btn" href="radar-regional.php">Abrir Aprovações</a></div><?php if($created): ?><div class="notice"><?=count($created)?> nova(s) matéria(s) completa(s) gerada(s) como rascunho e Última Hora atualizada.</div><?php endif; ?><div class="box"><h2>Buscar pautas e gerar matérias completas</h2><p>O monitor consulta RSS quando disponível, sites oficiais e portais regionais. A IA gera uma matéria completa, mas nada é publicado sem aprovação manual.</p><form method="post"><?=tvs_csrf_field()?><div class="grid2"><div><label><b>Estilo da matéria</b></label><select name="editorial_style"><option>Notícia padrão</option><option>Última hora</option><option>Esporte</option><option>Política</option><option>Segurança</option><option>Saúde</option><option>Educação</option><option>Cultura</option><option>Empregos</option><option>Utilidade pública</option><option>Release institucional</option><option>Guia comercial / publieditorial</option></select></div><div><label><b>Abordagem</b></label><select name="approach"><option>Informativa</option><option>Urgente</option><option>Comunitária</option><option>Serviço público</option><option>Fiscalização / cobrança</option><option>Comercial</option></select></div></div><label><b>Tamanho</b></label><select name="size"><option>Média</option><option>Curta</option><option>Completa</option></select><button class="btn orange" name="run" value="1">Rodar monitor e gerar matérias profissionais</button></form><?php if(empty($gemini_api_key)): ?><p class="muted"><b>Aviso:</b> sem Gemini configurado, o sistema usa texto-base local.</p><?php endif; ?></div><div class="box" style="margin-top:18px"><h2>Fontes cadastradas</h2><div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Cidade</th><th>Fonte</th><th>RSS/Site</th></tr></thead><tbody><?php foreach($sources as $s): ?><tr><td><?=htmlspecialchars($s['type']??'regional')?></td><td><?=htmlspecialchars($s['city']??'Região')?></td><td><?=htmlspecialchars($s['name']??'')?></td><td><a target="_blank" rel="noopener" href="<?=htmlspecialchars(($s['rss']?:$s['url'])??'#')?>"><?=htmlspecialchars(($s['rss']?:$s['url'])??'')?></a></td></tr><?php endforeach; ?></tbody></table></div></div></main></div></body></html>
