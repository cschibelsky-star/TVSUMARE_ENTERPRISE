<?php
$active='aovivo';
date_default_timezone_set('America/Sao_Paulo');

function tvs_live_json($file){
  if(!file_exists($file)) return [];
  $d=json_decode(file_get_contents($file),true);
  return is_array($d)?$d:[];
}
function tvs_live_h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function tvs_live_embed_safe($html){
  $html=trim((string)$html);
  if($html==='' || stripos($html,'<iframe')===false) return '';
  if(preg_match('~src=["\']https://(www\.)?(youtube\.com|youtu\.be|www\.youtube-nocookie\.com)/[^"\']+["\']~i',$html)) return $html;
  return '';
}
function tvs_live_minutes($hhmm){
  if(!preg_match('/^(\d{2}):(\d{2})$/',(string)$hhmm,$m)) return null;
  return ((int)$m[1])*60+(int)$m[2];
}
function tvs_live_default_channels($settings){
  return [
    'tvsenado'=>[
      'name'=>'TV Senado',
      'source'=>'Senado Federal',
      'embed'=>'',
      'url'=>'https://www.youtube.com/@tvsenado',
      'enabled'=>true,
    ],
    'tvcamara'=>[
      'name'=>'TV Câmara',
      'source'=>'Câmara dos Deputados',
      'embed'=>'',
      'url'=>'',
      'enabled'=>true,
    ],
    'tvalesp'=>[
      'name'=>'TV Alesp',
      'source'=>'Assembleia Legislativa do Estado de São Paulo',
      'embed'=>'',
      'url'=>'',
      'enabled'=>true,
    ],
    'tvsumare'=>[
      'name'=>'TV Sumaré',
      'source'=>'TV Sumaré',
      'embed'=>(string)($settings['live_embed']??''),
      'url'=>(string)($settings['youtube_url']??'https://www.youtube.com/@tvsumare'),
      'enabled'=>true,
    ],
  ];
}
function tvs_live_default_schedule(){
  return [
    ['start'=>'00:00','end'=>'06:00','channel'=>'tvsenado'],
    ['start'=>'06:00','end'=>'12:00','channel'=>'tvcamara'],
    ['start'=>'12:00','end'=>'18:00','channel'=>'tvalesp'],
    ['start'=>'18:00','end'=>'24:00','channel'=>'tvsumare'],
  ];
}
function tvs_live_pick_schedule($schedule,$nowMinutes){
  foreach($schedule as $idx=>$slot){
    $start=tvs_live_minutes($slot['start']??'');
    $endRaw=(string)($slot['end']??'');
    $end=$endRaw==='24:00'?1440:tvs_live_minutes($endRaw);
    if($start===null || $end===null) continue;
    if($nowMinutes >= $start && $nowMinutes < $end) return [$slot,$idx,$end];
  }
  return [null,null,null];
}
function tvs_live_next_slot($schedule,$currentIndex){
  if(!$schedule) return null;
  if($currentIndex===null) return $schedule[0]??null;
  $next=$currentIndex+1;
  return $schedule[$next]??($schedule[0]??null);
}

$settings=tvs_live_json('data/site_settings.json');
$channels=tvs_live_default_channels($settings);
if(isset($settings['live_channels']) && is_array($settings['live_channels'])){
  foreach($settings['live_channels'] as $key=>$channel){
    if(!is_array($channel)) continue;
    $channels[$key]=array_merge($channels[$key]??[], $channel);
  }
}
$schedule=(isset($settings['live_schedule']) && is_array($settings['live_schedule']) && $settings['live_schedule'])
  ? $settings['live_schedule']
  : tvs_live_default_schedule();

$nowMinutes=((int)date('G'))*60+(int)date('i');
[$slot,$slotIndex,$slotEnd]=tvs_live_pick_schedule($schedule,$nowMinutes);
$selectedKey=(string)($slot['channel']??'tvsumare');
$selectionReason='grade automática';

$sumareOnline=strtolower((string)($settings['live_status']??'offline'))==='online';
$sumareEmbed=tvs_live_embed_safe($channels['tvsumare']['embed']??'');
if($sumareOnline && $sumareEmbed!==''){
  $selectedKey='tvsumare';
  $selectionReason='transmissão própria prioritária';
} elseif(!empty($settings['live_override_enabled'])){
  $overrideKey=(string)($settings['live_override_channel']??'');
  if(isset($channels[$overrideKey]) && !empty($channels[$overrideKey]['enabled'])){
    $selectedKey=$overrideKey;
    $selectionReason='seleção manual';
  }
}

$selected=$channels[$selectedKey]??$channels['tvsumare'];
if(empty($selected['enabled'])){
  $selectedKey='tvsumare';
  $selected=$channels['tvsumare'];
  $selectionReason='fallback';
}
$embed=tvs_live_embed_safe($selected['embed']??'');
if($embed==='' && $selectedKey!=='tvsumare' && $sumareEmbed!==''){
  $selectedKey='tvsumare';
  $selected=$channels['tvsumare'];
  $embed=$sumareEmbed;
  $selectionReason='fallback TV Sumaré';
}

$nextSlot=tvs_live_next_slot($schedule,$slotIndex);
$nextKey=(string)($nextSlot['channel']??'');
$nextChannel=$channels[$nextKey]??null;

$secondsToReload=0;
if($slotEnd!==null && $selectionReason==='grade automática'){
  $secondsToReload=max(15,(($slotEnd-$nowMinutes)*60)-(int)date('s')+2);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ao Vivo | TV Sumaré</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=homologacao-final">
<link rel="stylesheet" href="assets/tvsumare-final-fixes.css?v=3">
<style>
.live-sourcebar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:0 0 12px;padding:12px 14px;border-radius:14px;background:#0f172a;color:#fff}
.live-sourcebar strong{font-size:15px}.live-sourcebar small{display:block;opacity:.72;margin-top:2px}
.live-badge{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;background:#ef4444;font-size:12px;font-weight:900}
.live-next{margin-top:14px;padding:12px 14px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
.live-next strong{display:block;margin-bottom:4px}.live-attribution{margin-top:10px;font-size:12px;color:#64748b}
</style>
</head>
<body>
<?php include 'header.php'; ?>
<main class="container page-shell">
  <div class="page-title">
    <span>Transmissão</span>
    <h1><?=tvs_live_h($settings['live_title']??'TV Sumaré Ao Vivo')?></h1>
    <p><?=tvs_live_h($settings['live_description']??'Acompanhe a programação ao vivo e canais públicos selecionados pela TV Sumaré.')?></p>
  </div>

  <div class="live-layout">
    <div>
      <div class="live-sourcebar">
        <div>
          <strong><?=tvs_live_h($selected['name']??'TV Sumaré')?></strong>
          <small>Fonte: <?=tvs_live_h($selected['source']??($selected['name']??'TV Sumaré'))?> · <?=tvs_live_h($selectionReason)?></small>
        </div>
        <span class="live-badge">● AO VIVO</span>
      </div>

      <div class="live-player">
        <?php if($embed): ?>
          <div class="live-embed-full"><?=$embed?></div>
        <?php else: ?>
          <div class="pulse-dot"></div>
          <h2>CANAL PROGRAMADO</h2>
          <p>O player deste canal ainda não foi configurado. Use a fonte oficial abaixo.</p>
          <?php if(!empty($selected['url'])): ?>
            <a class="btn btn-primary" href="<?=tvs_live_h($selected['url'])?>" target="_blank" rel="noopener">▶ Abrir fonte oficial</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="live-attribution">A TV Sumaré incorpora fontes oficiais quando disponíveis. O conteúdo externo permanece identificado pela emissora de origem.</div>
    </div>

    <aside class="schedule-card">
      <h3>Grade automática</h3>
      <?php foreach($schedule as $row):
        $key=(string)($row['channel']??'');
        $channel=$channels[$key]??[];
      ?>
        <div class="schedule-row">
          <strong><?=tvs_live_h(($row['start']??'--:--').'–'.($row['end']??'--:--'))?></strong>
          <span><?=tvs_live_h($channel['name']??$key)?></span>
        </div>
      <?php endforeach; ?>

      <?php if($nextChannel): ?>
        <div class="live-next">
          <strong>Próximo canal</strong>
          <?=tvs_live_h($nextChannel['name']??'')?> às <?=tvs_live_h($nextSlot['start']??'--:--')?>
        </div>
      <?php endif; ?>

      <a class="btn btn-outline" href="videos.php" style="margin-top:16px">Ver vídeos</a>
    </aside>
  </div>
</main>
<?php include 'rodape.php'; ?>
<?php if($secondsToReload>0): ?>
<script>
setTimeout(function(){ window.location.reload(); }, <?=((int)$secondsToReload)*1000?>);
</script>
<?php endif; ?>
</body>
</html>
