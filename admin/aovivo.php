<?php
require_once __DIR__.'/auth.php';
require_login();
$activeAdmin='aovivo';
$dataFile=dirname(__DIR__).'/data/site_settings.json';

function tvs_live_admin_json($file){
  if(!file_exists($file)) return [];
  $d=json_decode(file_get_contents($file),true);
  return is_array($d)?$d:[];
}
function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function tvs_admin_default_channels($settings){
  return [
    'tvsenado'=>['name'=>'TV Senado','source'=>'Senado Federal','embed'=>'','url'=>'https://www.youtube.com/@tvsenado','enabled'=>true],
    'tvcamara'=>['name'=>'TV Câmara','source'=>'Câmara dos Deputados','embed'=>'','url'=>'','enabled'=>true],
    'tvalesp'=>['name'=>'TV Alesp','source'=>'Assembleia Legislativa do Estado de São Paulo','embed'=>'','url'=>'','enabled'=>true],
    'tvsumare'=>['name'=>'TV Sumaré','source'=>'TV Sumaré','embed'=>(string)($settings['live_embed']??''),'url'=>(string)($settings['youtube_url']??'https://www.youtube.com/@tvsumare'),'enabled'=>true],
  ];
}
function tvs_admin_default_schedule(){
  return [
    ['start'=>'00:00','end'=>'06:00','channel'=>'tvsenado'],
    ['start'=>'06:00','end'=>'12:00','channel'=>'tvcamara'],
    ['start'=>'12:00','end'=>'18:00','channel'=>'tvalesp'],
    ['start'=>'18:00','end'=>'24:00','channel'=>'tvsumare'],
  ];
}

$settings=tvs_live_admin_json($dataFile);
$msg='';
$channels=tvs_admin_default_channels($settings);
if(isset($settings['live_channels']) && is_array($settings['live_channels'])){
  foreach($settings['live_channels'] as $key=>$channel){
    if(is_array($channel)) $channels[$key]=array_merge($channels[$key]??[],$channel);
  }
}
$schedule=(isset($settings['live_schedule']) && is_array($settings['live_schedule']) && $settings['live_schedule'])
  ? $settings['live_schedule']
  : tvs_admin_default_schedule();

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  $settings['live_title']=trim($_POST['live_title']??'TV Sumaré Ao Vivo');
  $settings['live_description']=trim($_POST['live_description']??'Acompanhe a programação ao vivo e canais públicos selecionados pela TV Sumaré.');
  $settings['live_status']=($_POST['live_status']??'offline')==='online'?'online':'offline';
  $settings['live_next']=trim($_POST['live_next']??'Programação ao vivo em breve');
  $settings['youtube_url']=trim($_POST['youtube_url']??'');
  $settings['live_embed']=trim($_POST['live_embed']??'');

  $channelKeys=['tvsenado','tvcamara','tvalesp','tvsumare'];
  $newChannels=[];
  foreach($channelKeys as $key){
    $newChannels[$key]=[
      'name'=>trim($_POST['channel_name'][$key]??($channels[$key]['name']??$key)),
      'source'=>trim($_POST['channel_source'][$key]??($channels[$key]['source']??'')),
      'embed'=>trim($_POST['channel_embed'][$key]??''),
      'url'=>trim($_POST['channel_url'][$key]??''),
      'enabled'=>isset($_POST['channel_enabled'][$key]),
    ];
  }
  $newChannels['tvsumare']['embed']=$settings['live_embed'];
  $newChannels['tvsumare']['url']=$settings['youtube_url'];

  $newSchedule=[];
  for($i=0;$i<4;$i++){
    $start=trim($_POST['schedule_start'][$i]??'');
    $end=trim($_POST['schedule_end'][$i]??'');
    $channel=trim($_POST['schedule_channel'][$i]??'');
    if($start!=='' && $end!=='' && isset($newChannels[$channel])){
      $newSchedule[]=['start'=>$start,'end'=>$end,'channel'=>$channel];
    }
  }
  if(!$newSchedule) $newSchedule=tvs_admin_default_schedule();

  $settings['live_channels']=$newChannels;
  $settings['live_schedule']=$newSchedule;
  $settings['live_override_enabled']=isset($_POST['live_override_enabled']);
  $settings['live_override_channel']=trim($_POST['live_override_channel']??'tvsumare');

  if(!is_dir(dirname($dataFile))) mkdir(dirname($dataFile),0755,true);
  file_put_contents($dataFile,json_encode($settings,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

  $channels=$newChannels;
  $schedule=$newSchedule;
  $msg='Configuração do Ao Vivo e grade automática salva.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ao Vivo | Admin TV Sumaré</title>
<link rel="stylesheet" href="admin.css">
<style>
.grid-live{display:grid;grid-template-columns:1fr 1fr;gap:16px}.live-section{margin-top:18px}.channel-card{border:1px solid #e5e7eb;border-radius:14px;padding:14px;margin:12px 0}.channel-card h3{margin:0 0 12px}.schedule-line{display:grid;grid-template-columns:120px 120px 1fr;gap:10px;align-items:end;margin:10px 0}.checkline{display:flex;align-items:center;gap:8px;margin:10px 0}.checkline input{width:auto}@media(max-width:760px){.grid-live,.schedule-line{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="admin">
<?php include __DIR__.'/_menu.php'; ?>
<main class="main">
  <div class="top">
    <div>
      <span class="eyebrow">TV Digital</span>
      <h1>Ao Vivo</h1>
      <p class="muted" style="text-align:left">Controle a transmissão própria, os canais externos e a rotação automática da grade.</p>
    </div>
    <a class="btn secondary" href="../aovivo.php" target="_blank">Ver página pública</a>
  </div>

  <?php if($msg): ?><div class="ok" style="margin:12px 0;padding:12px;border-radius:12px;background:#dcfce7;color:#166534;font-weight:800"><?=h($msg)?></div><?php endif; ?>

  <form class="box" method="post">
    <?=tvs_csrf_field()?>

    <h2>TV Sumaré</h2>
    <label>Título</label>
    <input name="live_title" value="<?=h($settings['live_title']??'TV Sumaré Ao Vivo')?>">

    <label>Descrição</label>
    <textarea name="live_description" rows="3"><?=h($settings['live_description']??'Acompanhe a programação ao vivo e canais públicos selecionados pela TV Sumaré.')?></textarea>

    <label>Status da transmissão própria</label>
    <select name="live_status">
      <option value="offline" <?=($settings['live_status']??'offline')!=='online'?'selected':''?>>Offline / seguir grade</option>
      <option value="online" <?=($settings['live_status']??'')==='online'?'selected':''?>>Online / prioridade máxima</option>
    </select>

    <label>Mensagem próxima transmissão</label>
    <input name="live_next" value="<?=h($settings['live_next']??'Programação ao vivo em breve')?>">

    <label>URL YouTube da TV Sumaré</label>
    <input name="youtube_url" value="<?=h($settings['youtube_url']??'https://www.youtube.com/@tvsumare')?>">

    <label>Embed iframe da TV Sumaré</label>
    <textarea name="live_embed" rows="5" placeholder="Cole aqui o iframe oficial do YouTube."><?=h($settings['live_embed']??'')?></textarea>

    <div class="live-section">
      <h2>Canais da grade</h2>
      <p class="muted" style="text-align:left">Use somente players/fontes oficiais que permitam incorporação.</p>

      <?php foreach($channels as $key=>$channel): if($key==='tvsumare') continue; ?>
      <div class="channel-card">
        <h3><?=h($channel['name']??$key)?></h3>
        <div class="grid-live">
          <div><label>Nome</label><input name="channel_name[<?=h($key)?>]" value="<?=h($channel['name']??'')?>"></div>
          <div><label>Fonte</label><input name="channel_source[<?=h($key)?>]" value="<?=h($channel['source']??'')?>"></div>
        </div>
        <label>URL oficial</label>
        <input name="channel_url[<?=h($key)?>]" value="<?=h($channel['url']??'')?>">
        <label>Embed iframe oficial</label>
        <textarea name="channel_embed[<?=h($key)?>]" rows="4"><?=h($channel['embed']??'')?></textarea>
        <label class="checkline"><input type="checkbox" name="channel_enabled[<?=h($key)?>]" value="1" <?=!empty($channel['enabled'])?'checked':''?>> Canal ativo</label>
      </div>
      <?php endforeach; ?>

      <input type="hidden" name="channel_name[tvsumare]" value="TV Sumaré">
      <input type="hidden" name="channel_source[tvsumare]" value="TV Sumaré">
      <input type="hidden" name="channel_enabled[tvsumare]" value="1">
    </div>

    <div class="live-section">
      <h2>Grade automática</h2>
      <?php for($i=0;$i<4;$i++):
        $row=$schedule[$i]??tvs_admin_default_schedule()[$i];
      ?>
      <div class="schedule-line">
        <div><label>Início</label><input name="schedule_start[<?=$i?>]" value="<?=h($row['start']??'')?>"></div>
        <div><label>Fim</label><input name="schedule_end[<?=$i?>]" value="<?=h($row['end']??'')?>"></div>
        <div>
          <label>Canal</label>
          <select name="schedule_channel[<?=$i?>]">
            <?php foreach($channels as $key=>$channel): ?>
              <option value="<?=h($key)?>" <?=($row['channel']??'')===$key?'selected':''?>><?=h($channel['name']??$key)?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endfor; ?>
    </div>

    <div class="live-section">
      <h2>Override manual</h2>
      <label class="checkline"><input type="checkbox" name="live_override_enabled" value="1" <?=!empty($settings['live_override_enabled'])?'checked':''?>> Ignorar temporariamente a grade automática</label>
      <label>Canal selecionado manualmente</label>
      <select name="live_override_channel">
        <?php foreach($channels as $key=>$channel): ?>
          <option value="<?=h($key)?>" <?=($settings['live_override_channel']??'tvsumare')===$key?'selected':''?>><?=h($channel['name']??$key)?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <button class="btn orange" type="submit">Salvar Ao Vivo e Grade</button>
  </form>
</main>
</div>
</body>
</html>
