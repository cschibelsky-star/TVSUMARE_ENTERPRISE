<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';
$activeAdmin='boletim_ia';

function bia_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function bia_path($name){ return dirname(__DIR__).'/data/'.$name; }
function bia_read($name){ $p=bia_path($name); if(!is_file($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function bia_write($name,$rows){ $p=bia_path($name); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true); file_put_contents($p,json_encode(array_values($rows),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
function bia_clean($v){ return trim(preg_replace('/\s+/u',' ',strip_tags((string)$v))); }
function bia_excerpt($v,$max=420){ $v=bia_clean($v); if(function_exists('mb_strlen') && mb_strlen($v,'UTF-8')>$max) return rtrim(mb_substr($v,0,$max,'UTF-8')).'…'; return strlen($v)>$max?rtrim(substr($v,0,$max)).'…':$v; }
function bia_find_news($rows,$id){ foreach($rows as $r){ if((string)($r['id']??'')===(string)$id) return $r; } return null; }
function bia_sentence($news){ $base=$news['summary']??($news['subtitle']??($news['body']??'')); $text=bia_excerpt($base,520); return $text!==''?$text:'Confira esta atualização acompanhada pela redação da TV Sumaré.'; }
function bia_script_one($news){
  $title=bia_clean($news['title']??'Atualização regional'); $city=bia_clean($news['city']??'Sumaré'); $summary=bia_sentence($news); $source=bia_clean($news['source']??'fonte consultada');
  return "TV Sumaré em um minuto. {$title}. {$summary} A informação é referente a {$city}. Fonte: {$source}. Continue acompanhando a TV Sumaré para novas atualizações.";
}
function bia_script_service($news){
  $title=bia_clean($news['title']??'Serviço TV Sumaré'); $summary=bia_sentence($news); $city=bia_clean($news['city']??'Sumaré'); $source=bia_clean($news['source']??'fonte consultada');
  return "Serviço TV Sumaré. Atenção, {$city}. {$title}. {$summary} Antes de se deslocar ou tomar qualquer providência, confira as orientações e canais oficiais citados na matéria. Fonte: {$source}. Informação rápida é na TV Sumaré.";
}
function bia_script_agenda($news){
  $title=bia_clean($news['title']??'Agenda da Cidade'); $summary=bia_sentence($news); $city=bia_clean($news['city']??'Sumaré'); $source=bia_clean($news['source']??'fonte consultada');
  return "Agenda da Cidade, na TV Sumaré. Tem programação em {$city}. {$title}. {$summary} Consulte a matéria completa para confirmar horários, local e eventuais alterações. Fonte: {$source}. Acompanhe a TV Sumaré.";
}
function bia_script_giro($selected,$all){
  $pool=[$selected]; foreach($all as $n){ if(($n['id']??'')===($selected['id']??'')) continue; $pool[]=$n; if(count($pool)>=3) break; }
  $parts=[]; foreach($pool as $i=>$n){ $num=$i+1; $parts[]="Notícia {$num}: ".bia_clean($n['title']??'Atualização').'. '.bia_excerpt($n['summary']??($n['subtitle']??''),180); }
  return "Giro Rápido TV Sumaré. As principais informações da região em poucos segundos. ".implode(' ',$parts)." Confira os detalhes no portal TV Sumaré e acompanhe nossas próximas atualizações.";
}
function bia_format_meta($format){
  $map=[
    'noticia_1_minuto'=>['label'=>'Notícia em 1 Minuto','target'=>'45–60 s'],
    'giro_rapido'=>['label'=>'Giro Rápido','target'=>'45–60 s'],
    'servico'=>['label'=>'Serviço TV Sumaré','target'=>'30–45 s'],
    'agenda'=>['label'=>'Agenda da Cidade','target'=>'30–60 s'],
  ]; return $map[$format]??$map['noticia_1_minuto'];
}

$news=bia_read('noticias.json');
usort($news,function($a,$b){ return strcmp((string)($b['published_at']??$b['created_at']??''),(string)($a['published_at']??$a['created_at']??'')); });
$news=array_slice($news,0,40);
$jobs=bia_read('videos_ia.json');
$msg=''; $err='';

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($_POST['create_boletim'])){
  tvs_verify_csrf();
  $newsId=trim((string)($_POST['news_id']??'')); $format=trim((string)($_POST['format']??'noticia_1_minuto'));
  $selected=bia_find_news($news,$newsId);
  if(!$selected){ $err='Selecione uma matéria publicada.'; }
  else {
    $meta=bia_format_meta($format);
    if($format==='giro_rapido') $script=bia_script_giro($selected,$news);
    elseif($format==='servico') $script=bia_script_service($selected);
    elseif($format==='agenda') $script=bia_script_agenda($selected);
    else $script=bia_script_one($selected);
    $job=[
      'id'=>'job_boletim_'.date('YmdHis').'_'.bin2hex(random_bytes(2)),
      'news_id'=>$selected['id']??'',
      'title'=>$meta['label'].' — '.bia_clean($selected['title']??'TV Sumaré'),
      'city'=>$selected['city']??'Sumaré','category'=>'Boletim TV Sumaré',
      'source'=>$selected['source']??'Fonte consultada','image'=>$selected['image']??'assets/cat-cidade.svg',
      'script'=>$script,'status'=>'roteiro_pronto','created_at'=>date('c'),
      'boletim'=>true,'boletim_format'=>$format,'boletim_format_label'=>$meta['label'],
      'duration_target'=>$meta['target'],'orientation'=>'portrait','aspect_ratio'=>'9:16',
      'social_ready'=>true,'distribution_targets'=>['instagram','tiktok']
    ];
    array_unshift($jobs,$job); bia_write('videos_ia.json',$jobs);
    $msg='Boletim vertical criado. Revise o roteiro no Repórter IA antes de enviar para geração do vídeo.';
  }
}
$boletins=array_values(array_filter($jobs,function($j){ return !empty($j['boletim']); }));
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Boletim TV Sumaré IA</title><link rel="stylesheet" href="admin.css?v=10"></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><h1>Boletim TV Sumaré IA</h1><p>Crie roteiros curtos para vídeos verticais 9:16. O roteiro entra na fila existente do Repórter IA para revisão humana, geração do vídeo e posterior distribuição em Instagram Reels e TikTok.</p>
<?php if($msg): ?><div class="box"><strong><?=bia_h($msg)?></strong></div><?php endif; ?><?php if($err): ?><div class="box"><strong><?=bia_h($err)?></strong></div><?php endif; ?>
<div class="box" style="margin-top:14px"><h2>Novo boletim vertical</h2><?php if(!$news): ?><p>Nenhuma matéria publicada disponível.</p><?php else: ?><form method="post" class="form"><?=tvs_csrf_field()?><div class="grid2"><div><label>Formato</label><select name="format"><option value="noticia_1_minuto">Notícia em 1 Minuto — 45 a 60 s</option><option value="giro_rapido">Giro Rápido — até 3 notícias</option><option value="servico">Serviço TV Sumaré — 30 a 45 s</option><option value="agenda">Agenda da Cidade — 30 a 60 s</option></select></div><div><label>Matéria principal</label><select name="news_id" required><?php foreach($news as $n): ?><option value="<?=bia_h($n['id']??'')?>"><?=bia_h(($n['city']??'Região').' — '.($n['title']??'Sem título'))?></option><?php endforeach; ?></select></div></div><p><strong>Saída:</strong> vertical 9:16 • revisão obrigatória • destinos preparados: Instagram Reels + TikTok.</p><button class="btn orange" name="create_boletim" value="1">Criar roteiro do boletim</button> <a class="btn" href="reporter-ia.php">Abrir Repórter IA</a></form><?php endif; ?></div>
<div class="box" style="margin-top:14px"><h2>Boletins criados</h2><?php if(!$boletins): ?><p>Nenhum boletim criado nesta fila.</p><?php else: ?><?php foreach(array_slice($boletins,0,12) as $b): ?><div style="padding:12px 0;border-top:1px solid #e5e7eb"><strong><?=bia_h($b['title']??'Boletim')?></strong><br><small><?=bia_h(($b['boletim_format_label']??'').' • '.($b['aspect_ratio']??'9:16').' • '.($b['duration_target']??'').' • status: '.($b['status']??''))?></small><p><?=bia_h(bia_excerpt($b['script']??'',260))?></p></div><?php endforeach; ?><?php endif; ?></div>
</main></div></body></html>
