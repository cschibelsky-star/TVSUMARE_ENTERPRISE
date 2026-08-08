<?php
require_once __DIR__.'/auth.php';
require_login();
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once __DIR__.'/monitor_lib.php';
$activeAdmin='boletim_ia';

function bia_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function bia_path($name){ return dirname(__DIR__).'/data/'.$name; }
function bia_read($name){ $p=bia_path($name); if(!is_file($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function bia_write($name,$rows){ $p=bia_path($name); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true); file_put_contents($p,json_encode(array_values($rows),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
function bia_config_read(){ $p=bia_path('boletim_ia_config.json'); if(!is_file($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function bia_config_write($cfg){ $p=bia_path('boletim_ia_config.json'); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true); file_put_contents($p,json_encode($cfg,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
function bia_clean($v){ return trim(preg_replace('/\s+/u',' ',strip_tags((string)$v))); }
function bia_excerpt($v,$max=420){ $v=bia_clean($v); if(function_exists('mb_strlen') && mb_strlen($v,'UTF-8')>$max) return rtrim(mb_substr($v,0,$max,'UTF-8')).'…'; return strlen($v)>$max?rtrim(substr($v,0,$max)).'…':$v; }
function bia_find_news($rows,$id){ foreach($rows as $r){ if((string)($r['id']??'')===(string)$id) return $r; } return null; }
function bia_sentence($news){ $base=$news['summary']??($news['subtitle']??($news['body']??'')); $text=bia_excerpt($base,520); return $text!==''?$text:'Confira esta atualização acompanhada pela redação da TV Sumaré.'; }
function bia_script_one($news){ $title=bia_clean($news['title']??'Atualização regional'); $city=bia_clean($news['city']??'Sumaré'); $summary=bia_sentence($news); $source=bia_clean($news['source']??'fonte consultada'); return "TV Sumaré em um minuto. {$title}. {$summary} A informação é referente a {$city}. Fonte: {$source}. Continue acompanhando a TV Sumaré para novas atualizações."; }
function bia_script_service($news){ $title=bia_clean($news['title']??'Serviço TV Sumaré'); $summary=bia_sentence($news); $city=bia_clean($news['city']??'Sumaré'); $source=bia_clean($news['source']??'fonte consultada'); return "Serviço TV Sumaré. Atenção, {$city}. {$title}. {$summary} Antes de se deslocar ou tomar qualquer providência, confira as orientações e canais oficiais citados na matéria. Fonte: {$source}. Informação rápida é na TV Sumaré."; }
function bia_script_agenda($news){ $title=bia_clean($news['title']??'Agenda da Cidade'); $summary=bia_sentence($news); $city=bia_clean($news['city']??'Sumaré'); $source=bia_clean($news['source']??'fonte consultada'); return "Agenda da Cidade, na TV Sumaré. Tem programação em {$city}. {$title}. {$summary} Consulte a matéria completa para confirmar horários, local e eventuais alterações. Fonte: {$source}. Acompanhe a TV Sumaré."; }
function bia_script_giro($selected,$all){ $pool=[$selected]; foreach($all as $n){ if(($n['id']??'')===($selected['id']??'')) continue; $pool[]=$n; if(count($pool)>=3) break; } $parts=[]; foreach($pool as $i=>$n){ $num=$i+1; $parts[]="Notícia {$num}: ".bia_clean($n['title']??'Atualização').'. '.bia_excerpt($n['summary']??($n['subtitle']??''),180); } return "Giro Rápido TV Sumaré. As principais informações da região em poucos segundos. ".implode(' ',$parts)." Confira os detalhes no portal TV Sumaré e acompanhe nossas próximas atualizações."; }
function bia_format_meta($format){ $map=['noticia_1_minuto'=>['label'=>'Notícia em 1 Minuto','target'=>'45–60 s'],'giro_rapido'=>['label'=>'Giro Rápido','target'=>'45–60 s'],'servico'=>['label'=>'Serviço TV Sumaré','target'=>'30–45 s'],'agenda'=>['label'=>'Agenda da Cidade','target'=>'30–60 s']]; return $map[$format]??$map['noticia_1_minuto']; }
function bia_presenter_labels(){ return ['noticias'=>'Notícias & Serviço','cidade'=>'Cidade, Cultura & Agenda','giro'=>'Giro Rápido & Destaques']; }
function bia_presenter_result($theme,$cfg,$reason,$mode){
  $labels=bia_presenter_labels(); if(!isset($labels[$theme])) $theme='noticias'; $p=$cfg['presenters'][$theme]??[];
  return ['theme'=>$theme,'theme_label'=>$labels[$theme],'name'=>$p['name']??'Apresentador do Boletim','tone'=>$p['tone']??'informal, próximo, ágil e confiável','avatar_id'=>$p['avatar_id']??'','voice_id'=>$p['voice_id']??'','style_id'=>$p['style_id']??'','selection_reason'=>$reason,'selection_mode'=>$mode];
}
function bia_choose_presenter_fallback($news,$format,$cfg){
  $text=mb_strtolower(bia_clean(($news['category']??'').' '.($news['title']??'').' '.($news['subtitle']??'').' '.($news['summary']??'')),'UTF-8');
  $cidade=['cultura','evento','agenda','show','festival','turismo','gastronomia','lazer','teatro','música','musica','exposição','exposicao','feira','cinema','esporte','esportes'];
  $noticias=['saúde','saude','segurança','seguranca','trânsito','transito','prefeitura','serviço','servico','emprego','educação','educacao','polícia','policia','chuva','alerta','obras','transporte'];
  foreach($cidade as $k){ if(strpos($text,$k)!==false) return bia_presenter_result('cidade',$cfg,'Tema identificado como cidade, cultura, agenda ou entretenimento.','fallback'); }
  foreach($noticias as $k){ if(strpos($text,$k)!==false) return bia_presenter_result('noticias',$cfg,'Tema identificado como notícia de serviço, utilidade pública ou informação objetiva.','fallback'); }
  if($format==='giro_rapido') return bia_presenter_result('giro',$cfg,'Formato com múltiplos destaques e ritmo mais dinâmico.','fallback');
  return bia_presenter_result('giro',$cfg,'Tema geral escolhido para variar o ambiente visual do boletim.','fallback');
}
function bia_choose_presenter_ai($news,$format,$cfg){
  global $gemini_api_key;
  $fallback=bia_choose_presenter_fallback($news,$format,$cfg);
  if(trim((string)($gemini_api_key??''))==='' || !function_exists('tvs_gemini_generate_text')) return $fallback;
  $prompt="Você é diretor editorial de vídeos curtos da TV Sumaré. Escolha UM dos três apresentadores para esta pauta, pensando em aderência ao assunto, naturalidade e variedade visual.\n\nAPRESENTADORES:\nnoticias = Notícias & Serviço: informação objetiva, utilidade pública, saúde, segurança, trânsito, emprego, prefeitura, educação e fatos urgentes.\ncidade = Cidade, Cultura & Agenda: cultura, eventos, turismo, gastronomia, lazer, esportes, histórias locais e agenda.\ngiro = Giro Rápido & Destaques: pautas de grande apelo, curiosidade, tendência, destaques gerais ou conteúdo que se beneficia de ritmo mais energético.\n\nFormato pedido: {$format}\nCategoria: ".($news['category']??'')."\nTítulo: ".($news['title']??'')."\nSubtítulo: ".($news['subtitle']??'')."\nResumo: ".bia_excerpt($news['summary']??($news['body']??''),700)."\n\nResponda SOMENTE em JSON válido, exatamente assim: {\"theme\":\"noticias|cidade|giro\",\"reason\":\"motivo curto\"}. Não use markdown.";
  $r=tvs_gemini_generate_text($gemini_api_key,$prompt,['temperature'=>0.1,'maxOutputTokens'=>120],18);
  if(empty($r['ok'])) return $fallback;
  $raw=trim((string)($r['text']??'')); $raw=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$raw); $j=json_decode($raw,true);
  $theme=$j['theme']??''; if(!in_array($theme,['noticias','cidade','giro'],true)) return $fallback;
  $reason=bia_clean($j['reason']??'Escolha editorial automática conforme o tema da pauta.');
  return bia_presenter_result($theme,$cfg,$reason!==''?$reason:'Escolha editorial automática conforme o tema da pauta.','ia');
}

$news=bia_read('noticias.json'); usort($news,function($a,$b){ return strcmp((string)($b['published_at']??$b['created_at']??''),(string)($a['published_at']??$a['created_at']??'')); }); $news=array_slice($news,0,40);
$jobs=bia_read('videos_ia.json');
$cfg=bia_config_read();
$cfg['presenters']=$cfg['presenters']??[];
$cfg['presenters']['noticias']=array_merge(['name'=>'TV Sumaré Agora','tone'=>'informal, próximo, ágil e confiável','avatar_id'=>'a3953e35ec7a43948caa515d9d5f3e89','voice_id'=>'','style_id'=>''],$cfg['presenters']['noticias']??[]);
$cfg['presenters']['cidade']=array_merge(['name'=>'TV Sumaré Cidade','tone'=>'leve, espontâneo, próximo, simpático e descontraído','avatar_id'=>'22fdb234baf14540ae4e01a805b2d2a6','voice_id'=>'','style_id'=>''],$cfg['presenters']['cidade']??[]);
$cfg['presenters']['giro']=array_merge(['name'=>'TV Sumaré Giro','tone'=>'dinâmico, humano, energético, natural e conversacional','avatar_id'=>'ecc586fbd3e94cd4a07347c098364fc8','voice_id'=>'','style_id'=>''],$cfg['presenters']['giro']??[]);
$msg=''; $err='';

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($_POST['save_presenters'])){
  tvs_verify_csrf();
  foreach(['noticias','cidade','giro'] as $key){
    $cfg['presenters'][$key]['name']=bia_clean($_POST[$key.'_name']??''); $cfg['presenters'][$key]['tone']=bia_clean($_POST[$key.'_tone']??''); $cfg['presenters'][$key]['avatar_id']=trim((string)($_POST[$key.'_avatar_id']??'')); $cfg['presenters'][$key]['voice_id']=trim((string)($_POST[$key.'_voice_id']??'')); $cfg['presenters'][$key]['style_id']=trim((string)($_POST[$key.'_style_id']??''));
  }
  bia_config_write($cfg); $msg='Três perfis de apresentadores dos boletins foram salvos.';
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($_POST['create_boletim'])){
  tvs_verify_csrf(); $newsId=trim((string)($_POST['news_id']??'')); $format=trim((string)($_POST['format']??'noticia_1_minuto')); $selected=bia_find_news($news,$newsId);
  if(!$selected){ $err='Selecione uma matéria publicada.'; }
  else {
    $meta=bia_format_meta($format); if($format==='giro_rapido') $script=bia_script_giro($selected,$news); elseif($format==='servico') $script=bia_script_service($selected); elseif($format==='agenda') $script=bia_script_agenda($selected); else $script=bia_script_one($selected);
    $presenter=bia_choose_presenter_ai($selected,$format,$cfg);
    $job=['id'=>'job_boletim_'.date('YmdHis').'_'.bin2hex(random_bytes(2)),'news_id'=>$selected['id']??'','title'=>$meta['label'].' — '.bia_clean($selected['title']??'TV Sumaré'),'city'=>$selected['city']??'Sumaré','category'=>'Boletim TV Sumaré','source'=>$selected['source']??'Fonte consultada','image'=>$selected['image']??'assets/cat-cidade.svg','script'=>$script,'status'=>'roteiro_pronto','created_at'=>date('c'),'boletim'=>true,'boletim_format'=>$format,'boletim_format_label'=>$meta['label'],'boletim_theme'=>$presenter['theme'],'boletim_theme_label'=>$presenter['theme_label'],'avatar_selection_mode'=>$presenter['selection_mode'],'avatar_selection_reason'=>$presenter['selection_reason'],'duration_target'=>$meta['target'],'orientation'=>'portrait','aspect_ratio'=>'9:16','social_ready'=>true,'distribution_targets'=>['instagram','tiktok'],'presenter_name'=>$presenter['name'],'presenter_tone'=>$presenter['tone'],'heygen_avatar_id'=>$presenter['avatar_id'],'heygen_voice_id'=>$presenter['voice_id'],'heygen_style_id'=>$presenter['style_id']];
    array_unshift($jobs,$job); bia_write('videos_ia.json',$jobs); $msg='IA selecionou “'.$presenter['theme_label'].'” para esta pauta. Motivo: '.$presenter['selection_reason'];
  }
}
$boletins=array_values(array_filter($jobs,function($j){ return !empty($j['boletim']); }));
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Boletim TV Sumaré IA</title><link rel="stylesheet" href="admin.css?v=14"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><h1>Boletim TV Sumaré IA</h1><p>A IA analisa a pauta e escolhe automaticamente o apresentador mais adequado entre três ambientes editoriais, mantendo variedade visual e coerência com o tema.</p>
<?php if($msg): ?><div class="box"><strong><?=bia_h($msg)?></strong></div><?php endif; ?><?php if($err): ?><div class="box"><strong><?=bia_h($err)?></strong></div><?php endif; ?>
<div class="box" style="margin-top:14px"><h2>Apresentadores disponíveis para a IA</h2><form method="post" class="form"><?=tvs_csrf_field()?><?php foreach(bia_presenter_labels() as $key=>$label): $p=$cfg['presenters'][$key]; ?><h3><?=bia_h($label)?></h3><div class="grid2"><div><label>Nome/persona</label><input name="<?=$key?>_name" value="<?=bia_h($p['name'])?>"></div><div><label>Tom</label><input name="<?=$key?>_tone" value="<?=bia_h($p['tone'])?>"></div><div><label>HeyGen Avatar ID</label><input name="<?=$key?>_avatar_id" value="<?=bia_h($p['avatar_id'])?>"></div><div><label>HeyGen Voice ID</label><input name="<?=$key?>_voice_id" value="<?=bia_h($p['voice_id'])?>" placeholder="adicionar depois da clonagem da voz"></div><div><label>HeyGen Style ID</label><input name="<?=$key?>_style_id" value="<?=bia_h($p['style_id'])?>"></div></div><?php endforeach; ?><button class="btn" name="save_presenters" value="1">Salvar apresentadores</button></form></div>
<div class="box" style="margin-top:14px"><h2>Novo boletim vertical</h2><?php if(!$news): ?><p>Nenhuma matéria publicada disponível.</p><?php else: ?><form method="post" class="form"><?=tvs_csrf_field()?><div class="grid2"><div><label>Formato do boletim</label><select name="format"><option value="noticia_1_minuto">Notícia em 1 Minuto</option><option value="servico">Serviço TV Sumaré</option><option value="agenda">Agenda da Cidade</option><option value="giro_rapido">Giro Rápido</option></select><small>O formato não fixa mais o avatar. A IA escolhe pelo conteúdo da pauta.</small></div><div><label>Matéria principal</label><select name="news_id" required><?php foreach($news as $n): ?><option value="<?=bia_h($n['id']??'')?>"><?=bia_h(($n['city']??'Região').' — '.($n['title']??'Sem título'))?></option><?php endforeach; ?></select></div></div><p><strong>Seleção automática:</strong> a IA avalia categoria, título, resumo e formato; se a IA externa estiver indisponível, entra uma classificação local segura como fallback.<br><strong>Saída:</strong> vertical 9:16 • revisão obrigatória • Instagram Reels + TikTok.</p><button class="btn orange" name="create_boletim" value="1">IA: criar boletim e escolher apresentador</button> <a class="btn" href="reporter-ia.php">Abrir Repórter IA</a></form><?php endif; ?></div>
<div class="box" style="margin-top:14px"><h2>Boletins criados</h2><?php if(!$boletins): ?><p>Nenhum boletim criado nesta fila.</p><?php else: ?><?php foreach(array_slice($boletins,0,12) as $b): ?><div style="padding:12px 0;border-top:1px solid #e5e7eb"><strong><?=bia_h($b['title']??'Boletim')?></strong><br><small><?=bia_h(($b['presenter_name']??'Apresentador').' • '.($b['boletim_theme_label']??'').' • seleção: '.($b['avatar_selection_mode']??'manual').' • '.($b['aspect_ratio']??'9:16').' • status: '.($b['status']??''))?></small><?php if(!empty($b['avatar_selection_reason'])): ?><p><strong>Por que este avatar:</strong> <?=bia_h($b['avatar_selection_reason'])?></p><?php endif; ?><p><?=bia_h(bia_excerpt($b['script']??'',260))?></p></div><?php endforeach; ?><?php endif; ?></div>
</main></div></body></html>
