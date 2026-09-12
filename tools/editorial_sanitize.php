<?php
/**
 * Auditoria/saneamento reversivel dos dados editoriais da TV Sumare.
 * Padrao: dry-run. Para aplicar: TVSUMARE_EDITORIAL_APPLY=YES php tools/editorial_sanitize.php --apply --root=/caminho/site
 */
$toolRoot=dirname(__DIR__);
$root=$toolRoot;
$apply=in_array('--apply',$argv,true);
foreach($argv as $arg){ if(strpos($arg,'--root=')===0) $root=rtrim(substr($arg,7),'/'); }
if($apply && getenv('TVSUMARE_EDITORIAL_APPLY')!=='YES'){
  fwrite(STDERR,"APPLY bloqueado: defina TVSUMARE_EDITORIAL_APPLY=YES.\n"); exit(2);
}
$policy=$root.'/includes/tvs_editorial_policy_v3.php';
if(!is_file($policy)) $policy=$toolRoot.'/includes/tvs_editorial_policy_v3.php';
if(!is_file($policy)){ fwrite(STDERR,"Politica V3 nao encontrada no alvo nem no release.\n"); exit(3); }
require_once $policy;

function es_read_json($path){
  if(!is_file($path)) return [];
  $raw=file_get_contents($path); $data=json_decode((string)$raw,true);
  if(!is_array($data)){ throw new RuntimeException("JSON invalido: {$path}"); }
  return $data;
}
function es_write_json_atomic($path,$data){
  $dir=dirname($path); if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException("Falha mkdir: {$dir}");
  $tmp=$path.'.tmp.'.getmypid();
  $json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if(file_put_contents($tmp,$json."\n",LOCK_EX)===false) throw new RuntimeException("Falha write: {$tmp}");
  if(!rename($tmp,$path)){ @unlink($tmp); throw new RuntimeException("Falha rename: {$path}"); }
}
function es_date_ts($item){
  foreach(['published_at','created_at','date','datetime','timestamp'] as $k){
    $v=trim((string)($item[$k]??'')); if($v==='') continue;
    $ts=strtotime($v); if($ts!==false) return $ts;
  }
  return null;
}
function es_label($item){ return trim((string)($item['title']??$item['titulo']??$item['name']??'sem titulo')); }

$newsPath=$root.'/data/noticias.json';
$breakingPath=$root.'/data/ultimahora.json';
$news=es_read_json($newsPath); $breaking=es_read_json($breakingPath);
$keepNews=[]; $keepBreaking=[]; $quarantine=[]; $review=[];

foreach($news as $item){
  $outside=tvs_v3_external_municipal_source($item);
  if($outside!==''){
    $quarantine[]=['dataset'=>'noticias','reason'=>'external_municipal_source:'.$outside,'item'=>$item];
    continue;
  }
  if(tvs_v3_region_city_detect($item)==='' && trim((string)($item['city']??''))!==''){
    $review[]=['dataset'=>'noticias','reason'=>'city_without_content_evidence','title'=>es_label($item),'city'=>$item['city']??''];
  }
  $keepNews[]=$item;
}

$cutoff=time()-(7*86400);
foreach($breaking as $item){
  $ts=es_date_ts($item);
  if($ts!==null && $ts<$cutoff){
    $quarantine[]=['dataset'=>'ultimahora','reason'=>'breaking_older_than_7_days','item'=>$item];
    continue;
  }
  if($ts===null) $review[]=['dataset'=>'ultimahora','reason'=>'breaking_without_parseable_date','title'=>es_label($item)];
  $keepBreaking[]=$item;
}

$summary=[
  'mode'=>$apply?'apply':'dry-run',
  'news_total'=>count($news),'news_keep'=>count($keepNews),
  'breaking_total'=>count($breaking),'breaking_keep'=>count($keepBreaking),
  'quarantine'=>count($quarantine),'review'=>count($review)
];
echo json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
foreach($quarantine as $q) echo "QUARANTINE {$q['dataset']} {$q['reason']} :: ".es_label($q['item'])."\n";
foreach(array_slice($review,0,50) as $r) echo "REVIEW {$r['dataset']} {$r['reason']} :: {$r['title']}\n";

if(!$apply){ echo "EDITORIAL_SANITIZE=DRY_RUN\n"; exit(0); }

$stamp=date('Ymd-His');
$backupDir=$root.'/data/editorial-backups/'.$stamp;
if(!is_dir($backupDir) && !mkdir($backupDir,0770,true) && !is_dir($backupDir)) throw new RuntimeException("Falha backup dir");
if(is_file($newsPath) && !copy($newsPath,$backupDir.'/noticias.json')) throw new RuntimeException('Falha backup noticias');
if(is_file($breakingPath) && !copy($breakingPath,$backupDir.'/ultimahora.json')) throw new RuntimeException('Falha backup ultimahora');
es_write_json_atomic($root.'/data/editorial-quarantine/'.$stamp.'.json',['created_at'=>date('c'),'summary'=>$summary,'items'=>$quarantine,'review'=>$review]);
es_write_json_atomic($newsPath,$keepNews);
es_write_json_atomic($breakingPath,$keepBreaking);
echo "BACKUP_DIR={$backupDir}\n";
echo "EDITORIAL_SANITIZE=APPLIED\n";
