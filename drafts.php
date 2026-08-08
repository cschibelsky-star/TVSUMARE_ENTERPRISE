<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/monitor_lib.php';

$df = dirname(__DIR__) . '/data/rascunhos.json';
$nf = dirname(__DIR__) . '/data/noticias.json';

function tvs_admin_clean_field($text){
  $text = (string)$text;
  if(function_exists('tvs_remove_editorial_artifacts')) $text = tvs_remove_editorial_artifacts($text);
  return trim($text);
}
function tvs_admin_find_draft_index($drafts, $id){
  foreach($drafts as $i=>$d){
    if((string)($d['id']??'') === (string)$id) return $i;
  }
  return -1;
}
function tvs_admin_slugify($text){
  if(function_exists('tvs_slug')) return tvs_slug($text);
  $text = strtolower(trim((string)$text));
  $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
  if($conv !== false) $text = $conv;
  $text = preg_replace('/[^a-z0-9]+/', '-', $text);
  return trim($text, '-') ?: uniqid('noticia');
}
function tvs_admin_normalize_drafts($drafts){
  $changed = false;
  foreach($drafts as $i=>$d){
    if(!is_array($d)){ unset($drafts[$i]); $changed = true; continue; }
    if(empty($drafts[$i]['id'])){ $drafts[$i]['id'] = uniqid('draft_'); $changed = true; }
    if(!isset($drafts[$i]['title'])) $drafts[$i]['title'] = 'Sem título';
    if(!isset($drafts[$i]['body']) && isset($drafts[$i]['content'])){ $drafts[$i]['body'] = $drafts[$i]['content']; $changed = true; }
    if(!isset($drafts[$i]['body'])) $drafts[$i]['body'] = '';
    if(!isset($drafts[$i]['created_at'])) $drafts[$i]['created_at'] = date('c');
  }
  return [array_values($drafts), $changed];
}

$drafts = tvs_read_json_file($df);
list($drafts, $normalizedChanged) = tvs_admin_normalize_drafts($drafts);
if($normalizedChanged) tvs_save_json_file($df, $drafts);

if(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'){
  $action = $_POST['action'] ?? '';
  $id = $_POST['id'] ?? '';
  $idx = tvs_admin_find_draft_index($drafts, $id);

  if($idx < 0){
    header('Location: drafts.php?erro=rascunho-nao-encontrado'); exit;
  }

  if($action === 'delete'){
    array_splice($drafts, $idx, 1);
    tvs_save_json_file($df, $drafts);
    header('Location: drafts.php?deleted=1'); exit;
  }

  if($action === 'save' || $action === 'publish'){
    // Campos enviados pela tela de edição. Se o botão for "Aprovar direto", mantém os valores já salvos no rascunho.
    $drafts[$idx]['title'] = tvs_admin_clean_field($_POST['title'] ?? ($drafts[$idx]['title'] ?? ''));
    $drafts[$idx]['subtitle'] = tvs_admin_clean_field($_POST['subtitle'] ?? ($drafts[$idx]['subtitle'] ?? ''));
    $drafts[$idx]['body'] = tvs_admin_clean_field($_POST['body'] ?? ($drafts[$idx]['body'] ?? ''));
    $drafts[$idx]['category'] = trim($_POST['category'] ?? ($drafts[$idx]['category'] ?? 'Cidades')) ?: 'Cidades';
    $drafts[$idx]['city'] = trim($_POST['city'] ?? ($drafts[$idx]['city'] ?? 'Região')) ?: 'Região';
    $drafts[$idx]['image'] = trim($_POST['image'] ?? ($drafts[$idx]['image'] ?? ''));
    $drafts[$idx]['editorial_style'] = trim($_POST['editorial_style'] ?? ($drafts[$idx]['editorial_style'] ?? 'Notícia padrão')) ?: 'Notícia padrão';
    $drafts[$idx]['approach'] = trim($_POST['approach'] ?? ($drafts[$idx]['approach'] ?? 'Informativa')) ?: 'Informativa';
    $drafts[$idx]['summary'] = tvs_admin_clean_field($_POST['summary'] ?? ($drafts[$idx]['summary'] ?? ''));
    $drafts[$idx]['seo_title'] = trim($_POST['seo_title'] ?? ($drafts[$idx]['seo_title'] ?? $drafts[$idx]['title']));
    $drafts[$idx]['meta_description'] = trim($_POST['meta_description'] ?? ($drafts[$idx]['meta_description'] ?? $drafts[$idx]['summary'] ?? ''));
    $drafts[$idx]['instagram_caption'] = trim($_POST['instagram_caption'] ?? ($drafts[$idx]['instagram_caption'] ?? ''));
    $drafts[$idx]['whatsapp_text'] = trim($_POST['whatsapp_text'] ?? ($drafts[$idx]['whatsapp_text'] ?? ''));
    $drafts[$idx]['updated_at'] = date('c');

    if($action === 'save'){
      tvs_save_json_file($df, $drafts);
      header('Location: drafts.php?edit='.urlencode($id).'&saved=1'); exit;
    }

    $title = trim($drafts[$idx]['title'] ?? '');
    $body = trim($drafts[$idx]['body'] ?? '');
    if($title === '' || $body === ''){
      tvs_save_json_file($df, $drafts);
      header('Location: drafts.php?edit='.urlencode($id).'&erro=preencha-titulo-texto'); exit;
    }

    $news = tvs_read_json_file($nf);
    $published = $drafts[$idx];
    $published['id'] = uniqid('news_');
    $published['old_draft_id'] = $id;
    $published['status'] = 'publicado';
    $published['slug'] = !empty($published['slug']) ? $published['slug'] : tvs_admin_slugify($published['title'] ?? 'noticia-tv-sumare');
    $published['created_at'] = $published['created_at'] ?? date('c');
    $published['published_at'] = date('c');
    $published['author'] = $published['author'] ?? 'Redação TV Sumaré';
    $published['source'] = $published['source'] ?? 'Fonte consultada';
    $published['source_url'] = $published['source_url'] ?? '';

    $news[] = $published;
    array_splice($drafts, $idx, 1);
    tvs_save_json_file($nf, $news);
    tvs_save_json_file($df, $drafts);
    header('Location: noticias.php?published=1'); exit;
  }

  header('Location: drafts.php?erro=acao-invalida'); exit;
}

$editId = $_GET['edit'] ?? '';
$edit = null;
foreach($drafts as $d){ if((string)($d['id']??'') === (string)$editId){ $edit = $d; break; } }
$styles = ['Notícia padrão','Última hora','Esporte','Política','Segurança','Saúde','Educação','Cultura','Empregos','Utilidade pública','Release institucional','Guia comercial / publieditorial'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rascunhos</title><link rel="stylesheet" href="admin.css?v=12"><style>.inline-form{display:inline}.btn.danger{background:#b42318;color:#fff}.btn.secondary{background:#eef2f7;color:#111}.actions{display:flex;gap:8px;flex-wrap:wrap}.textarea-large{min-height:360px}.draft-list .box{margin-bottom:16px}</style></head><body><div class="admin"><aside class="side"><div class="logo"><img src="../assets/logo-tv-sumare.jpeg"><div><b>TV SUMARÉ</b><br><small>Painel Administrativo</small></div></div><nav class="menu"><a href="index.php">Dashboard</a><a href="nova-noticia.php">Nova notícia</a><a href="noticias.php">Notícias publicadas</a><a href="monitor.php">Monitor Regional</a><a href="editor-ia.php">Editor IA</a><a class="active" href="drafts.php">Rascunhos</a><a href="lixeira.php">Lixeira</a><a href="logout.php">Sair</a></nav></aside><main class="main"><h1>Rascunhos para aprovação</h1>
<?php if(isset($_GET['saved'])): ?><div class="notice">Rascunho salvo com sucesso.</div><?php endif; ?>
<?php if(isset($_GET['deleted'])): ?><div class="notice">Rascunho excluído com sucesso.</div><?php endif; ?>
<?php if(isset($_GET['erro'])): ?><div class="notice" style="background:#fff3cd;color:#7a4b00">Não foi possível concluir a ação: <?=htmlspecialchars($_GET['erro'])?></div><?php endif; ?>

<?php if($edit): ?><div class="box"><h2>Revisar matéria antes de publicar</h2><form method="post" class="form"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=htmlspecialchars($edit['id']??'')?>"><label>Título</label><input name="title" value="<?=htmlspecialchars($edit['title']??'')?>" required><label>Subtítulo</label><input name="subtitle" value="<?=htmlspecialchars($edit['subtitle']??'')?>"><label>Resumo curto</label><textarea name="summary"><?=htmlspecialchars($edit['summary']??'')?></textarea><div class="grid2"><div><label>Cidade</label><input name="city" value="<?=htmlspecialchars($edit['city']??'')?>"></div><div><label>Categoria</label><input name="category" value="<?=htmlspecialchars($edit['category']??'Cidades')?>"></div></div><label>Estilo editorial</label><select name="editorial_style"><?php foreach($styles as $st): ?><option value="<?=htmlspecialchars($st)?>" <?=($edit['editorial_style']??'Notícia padrão')===$st?'selected':''?>><?=htmlspecialchars($st)?></option><?php endforeach; ?></select><label>Imagem da matéria</label><input name="image" value="<?=htmlspecialchars($edit['image']??'')?>"><div class="grid2"><div><label>SEO title</label><input name="seo_title" value="<?=htmlspecialchars($edit['seo_title']??($edit['title']??''))?>"></div><div><label>Meta description</label><input name="meta_description" value="<?=htmlspecialchars($edit['meta_description']??'')?>"></div></div><label>Texto completo da matéria</label><textarea class="textarea-large" name="body" required><?=htmlspecialchars($edit['body']??'')?></textarea><label>Legenda Instagram</label><textarea name="instagram_caption"><?=htmlspecialchars($edit['instagram_caption']??'')?></textarea><label>Texto WhatsApp</label><textarea name="whatsapp_text"><?=htmlspecialchars($edit['whatsapp_text']??'')?></textarea><p><small>Fonte: <a target="_blank" href="<?=htmlspecialchars($edit['source_url']??'#')?>"><?=htmlspecialchars($edit['source']??'Conferir fonte')?></a></small></p><div class="actions"><button type="submit" class="btn secondary" name="action" value="save">Salvar alterações</button><button type="submit" class="btn orange" name="action" value="publish" onclick="return confirm('Aprovar e publicar esta matéria completa no site?')">Aprovar e publicar</button><a class="btn secondary" href="drafts.php">Voltar</a></div></form></div><?php endif; ?>

<?php if(!$drafts): ?><div class="box">Nenhum rascunho. Rode o Monitor Regional.</div><?php endif; ?>
<div class="draft-list"><?php foreach(array_reverse($drafts) as $d): $body=$d['body']??''; ?><div class="box"><small><?=htmlspecialchars($d['city']??'Região')?> • <?=htmlspecialchars($d['source']??'Fonte')?> • <?=htmlspecialchars($d['status']??'rascunho')?></small><h2><?=htmlspecialchars($d['title']??'Sem título')?></h2><p><b><?=htmlspecialchars($d['subtitle']??'')?></b></p><p><?=nl2br(htmlspecialchars(tvs_substr($body,0,700)))?><?=tvs_strlen($body)>700?'...':''?></p><div class="actions"><a class="btn orange" href="drafts.php?edit=<?=urlencode($d['id']??'')?>">Revisar / editar</a><form method="post" class="inline-form"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=htmlspecialchars($d['id']??'')?>"><button type="submit" class="btn" name="action" value="publish" onclick="return confirm('Aprovar direto sem editar?')">Aprovar direto</button></form><a class="btn secondary" target="_blank" href="<?=htmlspecialchars($d['source_url']??'#')?>">Conferir fonte</a><form method="post" class="inline-form"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=htmlspecialchars($d['id']??'')?>"><button type="submit" class="btn danger" name="action" value="delete" onclick="return confirm('Excluir este rascunho?')">Excluir</button></form></div></div><?php endforeach; ?></div>
</main></div></body></html>
