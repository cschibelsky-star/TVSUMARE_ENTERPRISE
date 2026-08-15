<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';

$activeAdmin='drafts';
$df=dirname(__DIR__).'/data/rascunhos.json';
$nf=dirname(__DIR__).'/data/noticias.json';

function tvs_admin_clean_field($text){
  $text=(string)$text;
  if(function_exists('tvs_remove_editorial_artifacts')) $text=tvs_remove_editorial_artifacts($text);
  return trim($text);
}
function tvs_admin_find_draft_index($drafts,$id){
  foreach($drafts as $i=>$d){ if((string)($d['id']??'')===(string)$id) return $i; }
  return -1;
}
function tvs_admin_slugify($text){
  if(function_exists('tvs_slug')) return tvs_slug($text);
  $text=strtolower(trim((string)$text));
  $conv=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text); if($conv!==false) $text=$conv;
  $text=preg_replace('/[^a-z0-9]+/','-',$text);
  return trim($text,'-') ?: uniqid('noticia');
}
function tvs_admin_normalize_drafts($drafts){
  if(!is_array($drafts)) $drafts=[];
  $changed=false;
  foreach($drafts as $i=>$d){
    if(!is_array($d)){ unset($drafts[$i]); $changed=true; continue; }
    if(empty($drafts[$i]['id'])){ $drafts[$i]['id']=uniqid('draft_'); $changed=true; }
    if(!isset($drafts[$i]['title'])) $drafts[$i]['title']='Sem título';
    if(!isset($drafts[$i]['body']) && isset($drafts[$i]['content'])){ $drafts[$i]['body']=$drafts[$i]['content']; $changed=true; }
    if(!isset($drafts[$i]['body'])) $drafts[$i]['body']='';
    if(!isset($drafts[$i]['created_at'])) $drafts[$i]['created_at']=date('c');
  }
  return [array_values($drafts),$changed];
}
function tvs_admin_draft_already_published($news,$draftId){
  foreach($news as $n){ if((string)($n['old_draft_id']??'')===(string)$draftId) return true; }
  return false;
}
function tvs_admin_invalid_draft($draft,&$reason=''){
  $title=trim((string)($draft['title']??''));
  $body=trim((string)($draft['body']??($draft['content']??'')));
  $source=trim((string)($draft['source']??''));
  $combined=$title.' '.$body;
  if(preg_match('/^google\s+news$/iu',$title)){
    $reason='Título genérico do agregador Google News, sem pauta jornalística identificada.';
    return true;
  }
  if(stripos($combined,'Comprehensive up-to-date news coverage, aggregated from sources all over the world by Google News')!==false){
    $reason='Texto padrão do Google News detectado em vez do conteúdo da matéria.';
    return true;
  }
  if(stripos($source,'Google News')!==false && preg_match('/\bcomprehensive up-to-date news coverage\b/iu',$body)){
    $reason='Rascunho de agregador sem conteúdo editorial aproveitável.';
    return true;
  }
  if(function_exists('tvs_is_boilerplate') && tvs_is_boilerplate($title.' '.$body)){
    $reason='Conteúdo de menu, boilerplate ou página genérica detectado.';
    return true;
  }
  return false;
}

$drafts=tvs_read_json_file($df);
[$drafts,$normalizedChanged]=tvs_admin_normalize_drafts($drafts);
if($normalizedChanged) tvs_save_json_file($df,$drafts);

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $action=(string)($_POST['action']??'');
  $id=(string)($_POST['id']??'');
  $idx=tvs_admin_find_draft_index($drafts,$id);

  if($idx<0){ header('Location: drafts.php?erro=rascunho-nao-encontrado'); exit; }

  if($action==='delete'){
    array_splice($drafts,$idx,1);
    if(!tvs_save_json_file($df,$drafts)){ header('Location: drafts.php?erro=falha-ao-excluir'); exit; }
    header('Location: drafts.php?deleted=1'); exit;
  }

  if($action==='save' || $action==='publish'){
    $drafts[$idx]['title']=tvs_admin_clean_field($_POST['title']??($drafts[$idx]['title']??''));
    $drafts[$idx]['subtitle']=tvs_admin_clean_field($_POST['subtitle']??($drafts[$idx]['subtitle']??''));
    $drafts[$idx]['body']=tvs_admin_clean_field($_POST['body']??($drafts[$idx]['body']??''));
    $drafts[$idx]['category']=trim((string)($_POST['category']??($drafts[$idx]['category']??'Cidades'))) ?: 'Cidades';
    $drafts[$idx]['city']=trim((string)($_POST['city']??($drafts[$idx]['city']??'Região'))) ?: 'Região';
    $previousImage=trim((string)($drafts[$idx]['image']??''));
    $drafts[$idx]['image']=trim((string)($_POST['image']??$previousImage));
    $imageChanged=$drafts[$idx]['image']!==$previousImage;
    if($imageChanged && $drafts[$idx]['image']!==''){
      $drafts[$idx]['image_source_type']='manual_review';
      $drafts[$idx]['image_credit']=function_exists('tvs_image_credit_from_source')
        ? tvs_image_credit_from_source($drafts[$idx]['source']??'Fonte consultada',$drafts[$idx]['image'])
        : ($drafts[$idx]['image_credit']??'');
      $drafts[$idx]['image_review_required']=0;
      $drafts[$idx]['image_reviewed_at']=date('c');
    }
    $drafts[$idx]['editorial_style']=trim((string)($_POST['editorial_style']??($drafts[$idx]['editorial_style']??'Notícia padrão'))) ?: 'Notícia padrão';
    $drafts[$idx]['approach']=trim((string)($_POST['approach']??($drafts[$idx]['approach']??'Informativa'))) ?: 'Informativa';
    $drafts[$idx]['summary']=tvs_admin_clean_field($_POST['summary']??($drafts[$idx]['summary']??''));
    $drafts[$idx]['seo_title']=trim((string)($_POST['seo_title']??($drafts[$idx]['seo_title']??$drafts[$idx]['title'])));
    $drafts[$idx]['meta_description']=trim((string)($_POST['meta_description']??($drafts[$idx]['meta_description']??$drafts[$idx]['summary']??'')));
    $drafts[$idx]['instagram_caption']=trim((string)($_POST['instagram_caption']??($drafts[$idx]['instagram_caption']??'')));
    $drafts[$idx]['whatsapp_text']=trim((string)($_POST['whatsapp_text']??($drafts[$idx]['whatsapp_text']??'')));
    $drafts[$idx]['updated_at']=date('c');

    if($action==='save'){
      $drafts[$idx]['reviewed_at']=date('c');
      if(!tvs_save_json_file($df,$drafts)){ header('Location: drafts.php?edit='.urlencode($id).'&erro=falha-ao-salvar'); exit; }
      header('Location: drafts.php?edit='.urlencode($id).'&saved=1'); exit;
    }

    $invalidReason='';
    if(tvs_admin_invalid_draft($drafts[$idx],$invalidReason)){
      tvs_save_json_file($df,$drafts);
      header('Location: drafts.php?edit='.urlencode($id).'&erro='.urlencode('conteudo-invalido: '.$invalidReason)); exit;
    }
    if(($_POST['review_confirm']??'')!=='1'){
      header('Location: drafts.php?edit='.urlencode($id).'&erro=confirme-a-revisao'); exit;
    }
    if(!empty($drafts[$idx]['image_review_required'])){
      if(($_POST['image_review_confirm']??'')!=='1'){
        header('Location: drafts.php?edit='.urlencode($id).'&erro=confirme-a-imagem-padrao'); exit;
      }
      $drafts[$idx]['image_review_required']=0;
      $drafts[$idx]['image_source_type']=$drafts[$idx]['image_source_type']??'default_reviewed';
      $drafts[$idx]['image_reviewed_at']=date('c');
    }

    $title=trim((string)($drafts[$idx]['title']??''));
    $body=trim((string)($drafts[$idx]['body']??''));
    if($title==='' || $body===''){
      tvs_save_json_file($df,$drafts);
      header('Location: drafts.php?edit='.urlencode($id).'&erro=preencha-titulo-texto'); exit;
    }

    $news=tvs_read_json_file($nf); if(!is_array($news)) $news=[];
    if(tvs_admin_draft_already_published($news,$id)){
      array_splice($drafts,$idx,1);
      tvs_save_json_file($df,$drafts);
      header('Location: noticias.php?published=existing'); exit;
    }

    $published=$drafts[$idx];
    $published['id']=uniqid('news_');
    $published['old_draft_id']=$id;
    $published['status']='publicado';
    $published['editorial_mode']='REVISAO_HUMANA';
    $published['reviewed_at']=date('c');
    $published['slug']=!empty($published['slug'])?$published['slug']:tvs_admin_slugify($published['title']??'noticia-tv-sumare');
    $published['created_at']=$published['created_at']??date('c');
    $published['published_at']=date('c');
    $published['author']=$published['author']??'Redação TV Sumaré';
    $published['source']=$published['source']??'Fonte consultada';
    $published['source_url']=$published['source_url']??'';

    $news[]=$published;
    if(!tvs_save_json_file($nf,$news)){
      header('Location: drafts.php?edit='.urlencode($id).'&erro=falha-ao-publicar'); exit;
    }
    array_splice($drafts,$idx,1);
    if(!tvs_save_json_file($df,$drafts)){
      header('Location: noticias.php?published=1&warning=rascunho-nao-removido'); exit;
    }
    header('Location: noticias.php?published=1'); exit;
  }

  header('Location: drafts.php?erro=acao-invalida'); exit;
}

$invalidCount=0;
$visibleDrafts=[];
foreach($drafts as $d){ $r=''; if(tvs_admin_invalid_draft($d,$r)){ $invalidCount++; continue; } $visibleDrafts[]=$d; }
$editId=(string)($_GET['edit']??'');
$edit=null;
foreach($drafts as $d){ if((string)($d['id']??'')===$editId){ $edit=$d; break; } }
$editInvalidReason=''; $editInvalid=$edit ? tvs_admin_invalid_draft($edit,$editInvalidReason) : false;
$styles=['Notícia padrão','Última hora','Esporte','Política','Segurança','Saúde','Educação','Cultura','Empregos','Utilidade pública','Release institucional','Guia comercial / publieditorial'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Revisões Pendentes | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=2.0.5"><style>.inline-form{display:inline}.btn.danger{background:#b42318;color:#fff}.actions{display:flex;gap:8px;flex-wrap:wrap}.textarea-large{min-height:360px}.draft-list .box{margin-bottom:16px}.review-confirm{padding:12px;border:1px solid #fed7aa;background:#fff7ed;border-radius:12px;margin:14px 0}</style></head>
<body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main">
<div class="top"><div><span class="eyebrow">Redação</span><h1>Revisões Pendentes</h1><p class="muted">Todo rascunho deve ser aberto e conferido antes da publicação. Conteúdo genérico de agregadores é bloqueado automaticamente.</p></div><div class="actions"><a class="btn secondary" href="radar-regional.php">Aprovações</a><a class="btn secondary" href="noticias.php">Publicadas</a></div></div>
<?php if($invalidCount>0): ?><div class="notice">Proteção editorial ativa: <?=htmlspecialchars((string)$invalidCount,ENT_QUOTES,'UTF-8')?> rascunho(s) inválido(s) foram ocultados desta fila sem apagar os dados.</div><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><div class="notice">Revisão salva com sucesso.</div><?php endif; ?><?php if(isset($_GET['deleted'])): ?><div class="notice">Rascunho excluído com sucesso.</div><?php endif; ?><?php if(isset($_GET['erro'])): ?><div class="notice error">Não foi possível concluir a ação: <?=htmlspecialchars((string)$_GET['erro'],ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
<?php if($edit): ?><div class="box"><h2>Revisar matéria antes de publicar</h2><?php if($editInvalid): ?><div class="notice error">Conteúdo bloqueado para publicação: <?=htmlspecialchars($editInvalidReason,ENT_QUOTES,'UTF-8')?>. Edite e salve um conteúdo jornalístico válido antes de publicar.</div><?php endif; ?><form method="post" class="form"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=htmlspecialchars((string)($edit['id']??''),ENT_QUOTES,'UTF-8')?>"><label>Título</label><input name="title" value="<?=htmlspecialchars((string)($edit['title']??''),ENT_QUOTES,'UTF-8')?>" required><label>Subtítulo</label><input name="subtitle" value="<?=htmlspecialchars((string)($edit['subtitle']??''),ENT_QUOTES,'UTF-8')?>"><label>Resumo curto</label><textarea name="summary"><?=htmlspecialchars((string)($edit['summary']??''),ENT_QUOTES,'UTF-8')?></textarea><div class="grid2"><div><label>Cidade</label><input name="city" value="<?=htmlspecialchars((string)($edit['city']??''),ENT_QUOTES,'UTF-8')?>"></div><div><label>Categoria</label><input name="category" value="<?=htmlspecialchars((string)($edit['category']??'Cidades'),ENT_QUOTES,'UTF-8')?>"></div></div><label>Estilo editorial</label><select name="editorial_style"><?php foreach($styles as $st): ?><option value="<?=htmlspecialchars($st,ENT_QUOTES,'UTF-8')?>" <?=($edit['editorial_style']??'Notícia padrão')===$st?'selected':''?>><?=htmlspecialchars($st,ENT_QUOTES,'UTF-8')?></option><?php endforeach; ?></select><label>Imagem da matéria</label><input name="image" value="<?=htmlspecialchars((string)($edit['image']??''),ENT_QUOTES,'UTF-8')?>"><?php if(!empty($edit['image_review_required'])): ?><div class="notice error"><strong>Imagem pendente de revisão.</strong> Substitua por uma imagem adequada ou confirme explicitamente o uso da imagem atual.</div><div class="review-confirm"><label><input type="checkbox" name="image_review_confirm" value="1"> Confirmo que revisei e autorizo o uso desta imagem.</label></div><?php endif; ?><div class="grid2"><div><label>SEO title</label><input name="seo_title" value="<?=htmlspecialchars((string)($edit['seo_title']??($edit['title']??'')),ENT_QUOTES,'UTF-8')?>"></div><div><label>Meta description</label><input name="meta_description" value="<?=htmlspecialchars((string)($edit['meta_description']??''),ENT_QUOTES,'UTF-8')?>"></div></div><label>Texto completo da matéria</label><textarea class="textarea-large" name="body" required><?=htmlspecialchars((string)($edit['body']??''),ENT_QUOTES,'UTF-8')?></textarea><label>Legenda Instagram</label><textarea name="instagram_caption"><?=htmlspecialchars((string)($edit['instagram_caption']??''),ENT_QUOTES,'UTF-8')?></textarea><label>Texto WhatsApp</label><textarea name="whatsapp_text"><?=htmlspecialchars((string)($edit['whatsapp_text']??''),ENT_QUOTES,'UTF-8')?></textarea><p><small>Fonte: <?php if(!empty($edit['source_url'])): ?><a target="_blank" rel="noopener" href="<?=htmlspecialchars((string)$edit['source_url'],ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars((string)($edit['source']??'Conferir fonte'),ENT_QUOTES,'UTF-8')?></a><?php else: ?><?=htmlspecialchars((string)($edit['source']??'Fonte não informada'),ENT_QUOTES,'UTF-8')?><?php endif; ?></small></p><div class="review-confirm"><label><input type="checkbox" name="review_confirm" value="1"> Confirmo que conferi fatos, nomes, datas, fonte, imagem e texto desta matéria.</label></div><div class="actions"><button type="submit" class="btn secondary" name="action" value="save">Salvar revisão</button><button type="submit" class="btn orange" name="action" value="publish" onclick="return confirm('Publicar a matéria após a revisão?')">Aprovar e publicar</button><a class="btn secondary" href="drafts.php">Voltar</a></div></form></div><?php endif; ?>
<?php if(!$visibleDrafts): ?><div class="box">Nenhuma revisão pendente válida.</div><?php endif; ?><div class="draft-list"><?php foreach(array_reverse($visibleDrafts) as $d): $body=$d['body']??''; ?><div class="box"><small><?=htmlspecialchars((string)($d['city']??'Região'),ENT_QUOTES,'UTF-8')?> • <?=htmlspecialchars((string)($d['source']??'Fonte'),ENT_QUOTES,'UTF-8')?> • <?=htmlspecialchars((string)($d['status']??'rascunho'),ENT_QUOTES,'UTF-8')?></small><h2><?=htmlspecialchars((string)($d['title']??'Sem título'),ENT_QUOTES,'UTF-8')?></h2><p><b><?=htmlspecialchars((string)($d['subtitle']??''),ENT_QUOTES,'UTF-8')?></b></p><p><?=nl2br(htmlspecialchars(tvs_substr($body,0,700),ENT_QUOTES,'UTF-8'))?><?=tvs_strlen($body)>700?'...':''?></p><div class="actions"><a class="btn orange" href="drafts.php?edit=<?=urlencode((string)($d['id']??''))?>">Revisar / editar</a><?php if(!empty($d['source_url'])): ?><a class="btn secondary" target="_blank" rel="noopener" href="<?=htmlspecialchars((string)$d['source_url'],ENT_QUOTES,'UTF-8')?>">Conferir fonte</a><?php endif; ?><form method="post" class="inline-form"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=htmlspecialchars((string)($d['id']??''),ENT_QUOTES,'UTF-8')?>"><button type="submit" class="btn danger" name="action" value="delete" onclick="return confirm('Excluir este rascunho?')">Excluir</button></form></div></div><?php endforeach; ?></div>
</main></div></body></html>