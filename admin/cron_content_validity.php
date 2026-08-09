<?php
// Verificador de validade editorial. Não remove conteúdo automaticamente.
require_once __DIR__.'/monitor_lib.php';
function cvc_read($name){$d=tvs_read_json_file(dirname(__DIR__).'/data/'.$name);return is_array($d)?$d:[];}
function cvc_write($name,$rows){return tvs_save_json_file(dirname(__DIR__).'/data/'.$name,array_values($rows));}
function cvc_date($n){foreach(['published_at','created_at','date'] as $k){if(!empty($n[$k])){$ts=strtotime((string)$n[$k]);if($ts)return $ts;}}return 0;}
function cvc_sensitive($n){$t=tvs_lower(tvs_clean_text(($n['category']??'').' '.($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'')));return preg_match('~\b(vagas?|empregos?|processo seletivo|concurso|inscri[cç][oõ]es|edital|evento|agenda|programa[cç][aã]o|interdi[cç][aã]o|tr[aâ]nsito|vacina[cç][aã]o|campanha|prazo|atendimento|curso|matr[ií]cula|feira|show|festival)\b~iu',$t)===1;}
$news=cvc_read('noticias.json');$alerts=[];$now=time();
foreach($news as $n){$ts=cvc_date($n);$age=$ts?max(0,(int)floor(($now-$ts)/86400)):null;$limit=cvc_sensitive($n)?7:30;$checked=strtotime((string)($n['validity_checked_at']??''));if($checked && ($now-$checked)<($limit*86400))continue;if($age===null||$age>=$limit||($n['validity_status']??'')==='revisao_solicitada'){$alerts[]=['id'=>'alert_'.substr(hash('sha256',(string)($n['id']??'').($n['title']??'')),0,16),'news_id'=>$n['id']??'','title'=>$n['title']??'Sem título','city'=>$n['city']??'Região','category'=>$n['category']??'Notícia','age_days'=>$age,'priority'=>cvc_sensitive($n)?'alta':'normal','reason'=>$age===null?'Data editorial não identificada.':"Conteúdo com {$age} dia(s) requer conferência editorial.",'status'=>'aguardando_revisao','notification_channels'=>['painel','email_quando_configurado','whatsapp_quando_configurado'],'detected_at'=>date('c')];}}
cvc_write('content_validity_alerts.json',$alerts);echo 'Alertas de validade: '.count($alerts).PHP_EOL;
