<?php
// Cron opcional: capta RSS/sites, atualiza Última Hora e gera rascunhos. Nunca publica automaticamente.
require_once dirname(__DIR__) . '/config.php'; require_once __DIR__ . '/gemini.php'; require_once __DIR__ . '/monitor_lib.php';
$sources=tvs_get_sources(); $draftFile=dirname(__DIR__) . '/data/rascunhos.json'; $drafts=tvs_read_json_file($draftFile); $news=tvs_read_json_file(dirname(__DIR__) . '/data/noticias.json');
$seen=tvs_monitor_seen_keys($drafts,$news); $count=0; $breaking=[];
foreach($sources as $src){
  $items=tvs_capture_source_items($src); $breaking=array_merge($breaking,$items);
  foreach($items as $item){
    if(empty($item['url'])) continue;
    if(tvs_monitor_is_old($item,14)){ tvs_monitor_discard($item,'Matéria antiga acima de 14 dias'); continue; }
    if(tvs_monitor_item_is_duplicate($item,$seen)){ tvs_monitor_discard($item,'Duplicada por URL ou título similar'); continue; }
    $article=tvs_extract_article($item['url'],$item['title']); if(empty($article['description']) && !empty($item['description'])) $article['description']=$item['description'];
    $invalidReason='';
    if(tvs_monitor_invalid_extraction($item,$article,$invalidReason)){
      $discardItem=$item;
      $discardItem['city']=$src['city']??($item['city']??'Região');
      $discardItem['source']=$src['name']??($item['source']??'Fonte regional');
      $discardItem['description']=$article['description']??($item['description']??'');
      $discardItem['body']=$article['body']??'';
      $discardItem['url']=$article['url']??($item['url']??'');
      tvs_monitor_discard($discardItem,$invalidReason);
      continue;
    }
    $material="Cidade: ".($src['city']??'Região')."\nFonte: ".($src['name']??'Fonte')."\nLink da fonte: {$article['url']}\nTítulo original: {$article['title']}\nResumo/meta: {$article['description']}\nTexto extraído:\n{$article['body']}";
    $ai=gemini_rewrite($gemini_api_key ?? '',$material); if(!$ai) $ai=tvs_local_fallback_article($src,$article); $ai=tvs_sanitize_ai_article($ai,$src,$article);
    $drafts[]=['id'=>uniqid('draft_'),'city'=>$src['city']??'Região','source'=>$src['name']??'Fonte regional','source_type'=>$src['type']??'regional','source_url'=>$article['url'],'title'=>$ai['title']??$article['title'],'subtitle'=>$ai['subtitle']??($article['description']?:'Pauta regional monitorada'),'body'=>$ai['body']??$article['body'],'category'=>$ai['category']??'Cidades','tags'=>$ai['tags']??[],'image'=>$article['image']??'','status'=>'rascunho_completo','created_at'=>date('c')];
    $seen['url:'.$item['url']]=true; $seen['title:'.md5(tvs_lower($src['city']??'').'|'.tvs_monitor_title_key($item['title']??''))]=true; $count++; break;
  }
}
tvs_save_json_file($draftFile,$drafts); tvs_update_breaking($breaking);
echo "Rascunhos gerados: {$count} | Última Hora atualizada".PHP_EOL;
