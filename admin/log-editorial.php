<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';
$activeAdmin='log_editorial';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function tvs_log_norm($s){$s=mb_strtolower(trim((string)$s),'UTF-8');return trim(preg_replace('~[^\p{L}\p{N}]+~u',' ',$s));}
function tvs_log_same_pauta($item,$title,$url){
  $itemUrl=trim((string)($item['source_url']??($item['url']??'')));
  if($url!=='' && $itemUrl!=='' && hash_equals($itemUrl,$url)) return true;
  $a=tvs_log_norm($item['title']??''); $b=tvs_log_norm($title);
  return $a!=='' && $b!=='' && hash_equals($a,$b);
}

$base=dirname(__DIR__).'/data';
$logFile=$base.'/radar_log.json';
$discardFile=$base.'/pautas_descartadas.json';
$queueFile=$base.'/materias_aprovacao.json';
$newsFile=$base.'/noticias.json';
$notice=''; $error='';

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='recover'){
  if(!tvs_csrf_is_valid()){
    $error='Sessão expirada. Recarregue a página e tente novamente.';
  } else {
    $idx=(int)($_POST['discard_index']??-1);
    $discard=tvs_read_json_file($discardFile); if(!is_array($discard)) $discard=[];
    if(!isset($discard[$idx])){
      $error='Pauta descartada não encontrada. Atualize a página e tente novamente.';
    } else {
      $d=$discard[$idx];
      $queue=tvs_read_json_file($queueFile); if(!is_array($queue)) $queue=[];
      $news=tvs_read_json_file($newsFile); if(!is_array($news)) $news=[];
      $title=trim((string)($d['title']??''));
      $url=trim((string)($d['source_url']??($d['url']??'')));
      $existsQueue=false; $existsNews=false;
      foreach($queue as $q){if(tvs_log_same_pauta($q,$title,$url)){ $existsQueue=true; break; }}
      foreach($news as $n){if(tvs_log_same_pauta($n,$title,$url)){ $existsNews=true; break; }}

      if($existsQueue){
        $error='Esta pauta já está na fila de aprovação.';
      } elseif($existsNews){
        $error='Esta pauta já foi publicada e não será duplicada.';
      } else {
        $description=trim((string)($d['description']??($d['summary']??($d['text']??''))));
        $body=trim((string)($d['body']??($d['text']??$description)));
        $image=trim((string)($d['image']??($d['image_url']??'')));
        $recoveredAt=date('c');
        $queue[]=[
          'id'=>uniqid('recover_'),
          'title'=>$title ?: 'Pauta recuperada',
          'subtitle'=>$d['subtitle']??'',
          'summary'=>$d['summary']??$description,
          'description'=>$description,
          'body'=>$body,
          'text'=>$body,
          'source'=>$d['source']??'Fonte',
          'source_url'=>$url,
          'url'=>$url,
          'city'=>$d['city']??'Região',
          'category'=>$d['category']??'Cidade',
          'image'=>$image,
          'image_credit'=>$d['image_credit']??'',
          'image_source_type'=>$d['image_source_type']??'',
          'image_review_required'=>$d['image_review_required']??0,
          'status'=>'aguardando',
          'review_level'=>'precisa_revisao',
          'editorial_status'=>'Recuperada para revisão',
          'editorial_mode'=>'RECUPERADA',
          'recovery_reason'=>$d['reason']??'Recuperada do Log Editorial',
          'recovered_from_discard_at'=>$d['created_at']??'',
          'recovered_at'=>$recoveredAt,
          'created_at'=>$recoveredAt
        ];
        if(tvs_save_json_file($queueFile,$queue)){
          array_splice($discard,$idx,1);
          tvs_save_json_file($discardFile,array_values($discard));
          $log=tvs_read_json_file($logFile); if(!is_array($log)) $log=[];
          $log[]=[
            'id'=>uniqid('log_'),'title'=>$title,'source'=>$d['source']??'Fonte',
            'city'=>$d['city']??'Região','status'=>'RECUPERADA',
            'reason'=>'Recuperada manualmente do descarte e enviada para revisão editorial.',
            'url'=>$url,'image'=>$image,
            'image_source_type'=>$d['image_source_type']??'',
            'image_credit'=>$d['image_credit']??'',
            'image_review_required'=>$d['image_review_required']??0,
            'image_reviewed_at'=>$d['image_reviewed_at']??'',
            'recovered_at'=>$recoveredAt,'created_at'=>$recoveredAt
          ];
          $log=array_slice($log,-500); tvs_save_json_file($logFile,$log);
          $notice='Pauta recuperada. Ela voltou para Aprovações como revisão editorial.';
        } else {
          $error='Não foi possível gravar a pauta na fila de aprovação.';
        }
      }
    }
  }
}

$log=tvs_read_json_file($logFile); $discard=tvs_read_json_file($discardFile);
if(!is_array($log)) $log=[]; if(!is_array($discard)) $discard=[];
$combined=$log;
foreach($discard as $i=>$d){
  $combined[]=['title'=>$d['title']??'','source'=>$d['source']??'Fonte','city'=>$d['city']??'Região','status'=>'DESCARTADA','reason'=>$d['reason']??'Descartada','url'=>$d['source_url']??($d['url']??''),'created_at'=>$d['created_at']??'','discard_index'=>$i,'recoverable'=>true,'image'=>$d['image']??($d['image_url']??''),'image_source_type'=>$d['image_source_type']??'','image_credit'=>$d['image_credit']??'','image_review_required'=>$d['image_review_required']??0,'image_reviewed_at'=>$d['image_reviewed_at']??'','rule'=>'DESCARTADA_LEGADO'];
}
usort($combined,function($a,$b){return strcmp((string)($b['created_at']??''),(string)($a['created_at']??''));});

// Métricas da Régua
$totalAnalyzed = count($combined);
$countApproved = 0;
$countDuplicated = 0;
$countZumbi = 0;
$countOutside = 0;
$countOther = 0;

foreach($combined as $r){
  $rule = $r['rule'] ?? '';
  $reason = mb_strtolower((string)($r['reason'] ?? ''), 'UTF-8');
  $st = strtoupper((string)($r['status'] ?? ''));
  if(strpos($st, 'APROV') !== false || $rule === 'APROVADA_FILA'){
    $countApproved++;
  } elseif($rule === 'DUPLICIDADE' || strpos($reason, 'duplicad') !== false || strpos($reason, 'memória histórica') !== false){
    $countDuplicated++;
  } elseif($rule === 'TEMPORAL_ZUMBI' || strpos($reason, 'eleiç') !== false || strpos($reason, 'campanha') !== false || strpos($reason, 'antig') !== false || strpos($reason, 'sazonal') !== false){
    $countZumbi++;
  } elseif($rule === 'FORA_RMC' || strpos($reason, 'fora da região') !== false || strpos($reason, 'fora da lista') !== false){
    $countOutside++;
  } else {
    $countOther++;
  }
}

// Filtro por aba na tela
$filter = $_GET['filter'] ?? 'all';
$filtered = [];
foreach($combined as $r){
  $rule = $r['rule'] ?? '';
  $reason = mb_strtolower((string)($r['reason'] ?? ''), 'UTF-8');
  $st = strtoupper((string)($r['status'] ?? ''));
  $isApp = (strpos($st, 'APROV') !== false || $rule === 'APROVADA_FILA');
  $isDup = ($rule === 'DUPLICIDADE' || strpos($reason, 'duplicad') !== false || strpos($reason, 'memória histórica') !== false);
  $isZum = ($rule === 'TEMPORAL_ZUMBI' || strpos($reason, 'eleiç') !== false || strpos($reason, 'campanha') !== false || strpos($reason, 'antig') !== false || strpos($reason, 'sazonal') !== false);
  $isOut = ($rule === 'FORA_RMC' || strpos($reason, 'fora da região') !== false || strpos($reason, 'fora da lista') !== false);

  if($filter === 'aprovadas' && !$isApp) continue;
  if($filter === 'duplicadas' && !$isDup) continue;
  if($filter === 'zumbi' && !$isZum) continue;
  if($filter === 'fora_rmc' && !$isOut) continue;
  $filtered[] = $r;
}

$itemsToShow = array_slice($filtered, 0, 300);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Monitor da Régua Editorial | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=95"><style>
.metrics-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:18px 0}
.metric-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;box-shadow:0 2px 8px rgba(0,0,0,.03)}
.metric-card span{display:block;font-size:12px;color:#64748b;font-weight:700}
.metric-card b{display:block;font-size:24px;color:#0f172a;margin-top:4px}
.metric-card.green b{color:#16a34a}
.metric-card.red b{color:#dc2626}
.metric-card.purple b{color:#9333ea}
.metric-card.amber b{color:#d97706}
.filter-tabs{display:flex;gap:8px;margin:16px 0;flex-wrap:wrap}
.filter-tab{display:inline-flex;align-items:center;padding:7px 14px;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}
.filter-tab.active{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
.tag{display:inline-block;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:800;background:#eef2ff;color:#1d4ed8}
.tag.desc{background:#fee2e2;color:#991b1b}
.tag.zumbi{background:#fef3c7;color:#92400e}
.tag.dup{background:#f3e8ff;color:#6b21a8}
.tag.rmc{background:#ffedd5;color:#9a3412}
.tag.pub{background:#dcfce7;color:#166534}
.reason-box{font-size:13px;line-height:1.4}
.badge-rule{display:inline-block;padding:2px 6px;border-radius:6px;font-size:10px;font-weight:800;margin-right:4px;text-transform:uppercase}
.recover-btn{border:0;border-radius:8px;padding:5px 9px;font-size:11px;font-weight:800;cursor:pointer;background:#fff7ed;color:#9a3412;margin-top:4px}
.thumb{width:64px;height:42px;object-fit:cover;border-radius:6px;display:block;margin-top:4px}
@media(max-width:900px){.metrics-grid{grid-template-columns:repeat(2,1fr)}}
</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Observabilidade & Qualidade Editorial</span><h1>📡 Monitor da Régua Editorial</h1><p class="muted">Acompanhe em tempo real cada decisão da Régua v3.2: o que foi aprovado para a fila, o que foi barrado pela trava anti-zumbi, duplicidades evitadas e descarte regional.</p></div><div class="actions"><a class="btn orange" href="radar-regional.php">Ir para Aprovações</a></div></div>
<?php if($notice): ?><div class="alert success"><?=h($notice)?></div><?php endif; ?><?php if($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<div class="metrics-grid">
  <div class="metric-card"><span>Total Analisadas</span><b><?=$totalAnalyzed?></b></div>
  <div class="metric-card green"><span>Aprovadas para a Fila</span><b><?=$countApproved?></b></div>
  <div class="metric-card red"><span>Barradas Anti-Zumbi / Antigas</span><b><?=$countZumbi?></b></div>
  <div class="metric-card purple"><span>Barradas por Duplicidade</span><b><?=$countDuplicated?></b></div>
  <div class="metric-card amber"><span>Barradas Fora da RMC</span><b><?=$countOutside?></b></div>
</div>

<div class="filter-tabs">
  <a class="filter-tab <?=$filter==='all'?'active':''?>" href="?filter=all">Todas (<?=$totalAnalyzed?>)</a>
  <a class="filter-tab <?=$filter==='aprovadas'?'active':''?>" href="?filter=aprovadas">🟢 Aprovadas Fila (<?=$countApproved?>)</a>
  <a class="filter-tab <?=$filter==='zumbi'?'active':''?>" href="?filter=zumbi">🛑 Anti-Zumbi / Antigas (<?=$countZumbi?>)</a>
  <a class="filter-tab <?=$filter==='duplicadas'?'active':''?>" href="?filter=duplicadas">🟣 Duplicadas (<?=$countDuplicated?>)</a>
  <a class="filter-tab <?=$filter==='fora_rmc'?'active':''?>" href="?filter=fora_rmc">🟠 Fora da RMC (<?=$countOutside?>)</a>
</div>

<section class="card"><table><thead><tr><th style="width:100px">Data/Hora</th><th style="width:130px">Decisão da Régua</th><th style="width:110px">Cidade</th><th style="width:140px">Fonte</th><th>Matéria & Imagem</th><th>Trilha da Régua / Motivo</th></tr></thead><tbody>
<?php if(!$itemsToShow): ?><tr><td colspan="6" class="muted" style="text-align:center;padding:24px">Nenhum registro encontrado para este filtro.</td></tr><?php endif; ?>
<?php foreach($itemsToShow as $r):
  $st=strtoupper((string)($r['status']??''));
  $rule=$r['rule']??'';
  $reason=(string)($r['reason']??'');
  $reasonLow=mb_strtolower($reason,'UTF-8');
  $isApp=(strpos($st,'APROV')!==false || $rule==='APROVADA_FILA');
  $isDup=($rule==='DUPLICIDADE' || strpos($reasonLow,'duplicad')!==false || strpos($reasonLow,'memória histórica')!==false);
  $isZum=($rule==='TEMPORAL_ZUMBI' || strpos($reasonLow,'eleiç')!==false || strpos($reasonLow,'campanha')!==false || strpos($reasonLow,'antig')!==false || strpos($reasonLow,'sazonal')!==false);
  $isOut=($rule==='FORA_RMC' || strpos($reasonLow,'fora da região')!==false || strpos($reasonLow,'fora da lista')!==false);

  $cls = $isApp ? 'pub' : ($isDup ? 'dup' : ($isZum ? 'zumbi' : ($isOut ? 'rmc' : 'desc')));
  $badgeLabel = $isApp ? 'APROVADA' : ($isDup ? 'DUPLICADA' : ($isZum ? 'ANTI-ZUMBI' : ($isOut ? 'FORA DA RMC' : $st)));
?>
<tr>
  <td><small><?=h(!empty($r['created_at'])?date('d/m H:i:s',strtotime($r['created_at'])):'')?></small></td>
  <td><span class="tag <?=$cls?>"><?=h($badgeLabel)?></span></td>
  <td><b><?=h($r['city']??'Região')?></b></td>
  <td><small class="muted"><?=h($r['source']??'Fonte')?></small></td>
  <td>
    <?php if(!empty($r['url'])): ?><a href="<?=h($r['url'])?>" target="_blank" rel="noopener" style="font-weight:700;color:#0f172a;text-decoration:none"><?=h($r['title']??'Sem título')?></a><?php else: ?><b><?=h($r['title']??'Sem título')?></b><?php endif; ?>
    <?php if(!empty($r['image'])): ?>
      <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
        <img class="thumb" src="<?=h($r['image'])?>" alt="">
        <small class="muted">Tipo: <?=h($r['image_type']?:'detectada')?></small>
      </div>
    <?php endif; ?>
  </td>
  <td class="reason-box">
    <div><?=h($reason)?></div>
    <?php if(!empty($r['category'])): ?><small class="muted">Categoria: <b><?=h($r['category'])?></b></small><?php endif; ?>
    <?php if(isset($r['score'])): ?> • <small class="muted">Score: <b><?=h($r['score'])?></b></small><?php endif; ?>
    <?php if(!empty($r['recoverable'])): ?>
      <form method="post" onsubmit="return confirm('Recuperar esta pauta para a fila de aprovação?')" style="margin-top:4px">
        <?=tvs_csrf_field()?>
        <input type="hidden" name="action" value="recover">
        <input type="hidden" name="discard_index" value="<?=h($r['discard_index'])?>">
        <button type="submit" class="recover-btn">↩ Recuperar para Fila</button>
      </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></section></main></div></body></html>
