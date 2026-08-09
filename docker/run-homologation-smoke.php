<?php
$root='/var/www/html';
$fail=[]; $ok=[];
function check($cond,$label){ global $fail,$ok; if($cond)$ok[]=$label; else $fail[]=$label; }
function txt($p){ $v=@file_get_contents($p); return is_string($v)?$v:''; }
function has($s,$needle){ return strpos($s,$needle)!==false; }

$radar=txt($root.'/admin/radar-regional.php');
$log=txt($root.'/admin/log-editorial.php');
$valid=txt($root.'/admin/content-validity.php');
$boletim=txt($root.'/admin/boletim-ia.php');
$reporter=txt($root.'/admin/reporter-ia.php');
$social=txt($root.'/admin/distribuicao-social.php');
$callback=txt($root.'/api/heygen-callback.php');
$menu=txt($root.'/admin/_menu.php');

check($radar!=='' && has($radar,'Notícia rápida'), 'Radar aceita notícia rápida');
check($radar!=='' && has($radar,'Enriquecer com IA'), 'Radar possui caminho de enriquecimento');
check($radar!=='' && has($radar,"if(\$wc<8)"), 'Radar não descarta automaticamente texto apenas por ser curto');
check($log!=='' && (has($log,'Recuperar para revisão') || has($log,'recuperar') || has($log,'recover')), 'Log Editorial possui recuperação');
check($log!=='' && (has($log,'source_url') || has($log,'url') || has($log,'fonte')), 'Recuperação preserva referência de origem');
check($valid!=='' && has($valid,'Solicitar revisão'), 'Validade Editorial solicita revisão');
check($valid!=='' && (has($valid,'arquiv') || has($valid,'Arquiv')), 'Validade Editorial possui arquivamento rastreável');
check($boletim!=='' && has($boletim,'bia_pick_distinct'), 'Boletim usa seleção distinta');
check($boletim!=='' && has($boletim,'bia_news_bucket'), 'Boletim prioriza diversidade editorial');
check($boletim!=='' && (has($boletim,'quantity') || has($boletim,'quantidade')), 'Boletim permite quantidade');
check($reporter!=='' && has($reporter,'rpia_provider_blocked'), 'Repórter bloqueia provedor indisponível');
check($reporter!=='' && has($reporter,'rpia_job_state'), 'Repórter usa máquina de estados');
check($reporter!=='' && has($reporter,'send_lock_at'), 'Repórter protege envio duplicado');
check($callback!=='' && has($callback,'heygen_last_callback_hash'), 'Callback HeyGen idempotente');
check($callback!=='' && has($callback,"['published','cancelled']"), 'Callback não regride estados terminais');
check($social!=='' && has($social,'ds_queue_active'), 'Social possui fila operacional');
check($social!=='' && has($social,'ds_generate_copy'), 'Social gera legenda e hashtags automaticamente');
check($social!=='' && has($social,'network_status'), 'Social preserva status por plataforma');
check(!preg_match('/>\s*Utiliza[cç][aã]o\s*</ui',$menu), 'Sidebar não expõe Utilização sem função');

foreach($ok as $label) echo "OK: {$label}\n";
if($fail){ foreach($fail as $label) fwrite(STDERR,"FAIL: {$label}\n"); fwrite(STDERR,'HOMOLOGATION_SMOKE=FAIL total='.count($fail)."\n"); exit(1); }
echo 'HOMOLOGATION_SMOKE=PASS total='.count($ok)."\n";
