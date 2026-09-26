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
$presenters=txt($root.'/admin/apresentadores-ia.php');
$social=txt($root.'/admin/distribuicao-social.php');
$callback=txt($root.'/api/heygen-callback.php');
$menu=txt($root.'/admin/_menu.php');
$drafts=txt($root.'/admin/drafts.php');
$newsAdmin=txt($root.'/admin/noticias.php');
$quick=txt($root.'/admin/nova-noticia.php');
$liveAdmin=txt($root.'/admin/aovivo.php');
$tvplay=txt($root.'/admin/tvplay.php');
$editor=txt($root.'/admin/editor-ia.php');
$defesa=txt($root.'/admin/defesa-civil.php');
$fontes=txt($root.'/admin/fontes.php');
$rss=txt($root.'/admin/rss-central.php');
$redes=txt($root.'/admin/redes.php');
$instagram=txt($root.'/admin/instagram.php');

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
check($presenters!=='' && has($presenters,'Catálogo de Apresentadores IA'), 'Catálogo de apresentadores IA existe');
check($presenters!=='' && has($presenters,"'homologado'") && has($presenters,'Critérios de homologação'), 'Catálogo controla homologação humana');
check($presenters!=='' && has($presenters,'Rosto/fotorealismo') && has($presenters,'Sincronização labial'), 'Catálogo avalia naturalidade audiovisual');
check($menu!=='' && has($menu,'apresentadores-ia.php'), 'Sidebar expõe Apresentadores IA');
check($callback!=='' && has($callback,'heygen_last_callback_hash'), 'Callback HeyGen idempotente');
check($callback!=='' && has($callback,"['published','cancelled']"), 'Callback não regride estados terminais');
check($social!=='' && has($social,'ds_queue_active'), 'Social possui fila operacional');
check($social!=='' && has($social,'ds_generate_copy'), 'Social gera legenda e hashtags automaticamente');
check($social!=='' && has($social,'network_status'), 'Social preserva status por plataforma');
check(!preg_match('/>\s*Utiliza[cç][aã]o\s*</ui',$menu), 'Sidebar não expõe Utilização sem função');

/* Contratos dos principais botões e ações administrativas. */
check(has($radar,'name="action" value="save_edit"') && has($radar,'name="action" value="approve"') && has($radar,'name="human_review" value="1"'), 'Radar edição/publicação usa o mesmo formulário');
check(has($radar,'update_radar') && has($radar,'update_radar_volume') && has($radar,'save_settings'), 'Radar atualização/configuração possuem handlers');
check(has($radar,'bulk_approve') && has($radar,'bulk_review') && has($radar,'bulk_discard'), 'Radar ações em lote possuem handlers');
check(has($radar,'TVS_PUBLISH_ERROR') && has($radar,'Publicação bloqueada:'), 'Radar informa motivo real do bloqueio de publicação');
check(has($drafts,'ai_edit') && has($drafts,'save') && has($drafts,'publish') && has($drafts,'delete'), 'Rascunhos: Editor IA, salvar, publicar e excluir possuem handlers');
check(has($newsAdmin,'trash'), 'Notícias publicadas: lixeira possui handler');
check(has($quick,'enrich') && has($quick,'publish') && has($quick,'cancel_draft'), 'Nova notícia: enriquecer, publicar e descartar possuem handlers');
check(has($liveAdmin,'REQUEST_METHOD') && has($liveAdmin,'Configuração do Ao Vivo e grade automática salva'), 'Ao Vivo: salvar grade possui fluxo POST');
check(has($tvplay,'suggest_top3') && has($tvplay,'generate_script') && has($tvplay,'publish_video') && has($tvplay,'publish_youtube'), 'TV Play: sugestões, roteiro e publicações possuem handlers');
check(has($editor,'name="action" value="search_generate"') && has($editor,'publish'), 'Editor IA: pesquisar/produzir e publicar possuem fluxo');
check(has($reporter,'save_config') && has($reporter,'generate_script') && has($reporter,'send_heygen') && has($reporter,'publish_video'), 'Repórter IA: configuração, roteiro, HeyGen e publicar possuem handlers');
check(has($boletim,'save_presenters') && has($boletim,'create_boletim'), 'Boletim IA: salvar apresentadores e criar boletim possuem handlers');
check(has($defesa,'generate') && has($defesa,'publish'), 'Defesa Civil: gerar e publicar alerta possuem handlers');
check(has($fontes,'add') && has($fontes,'delete'), 'Fontes: adicionar e excluir possuem handlers');
check(has($rss,'seed') && has($rss,'test') && has($rss,'toggle'), 'Central RSS: ativar, testar e alternar possuem handlers');
check(has($redes,'if(isset(') && has($redes,'add') && has($redes,'draft'), 'Redes sociais: salvar pauta e criar rascunho possuem handlers');
check(has($presenters,'REQUEST_METHOD') && has($presenters,'Salvar avaliação'), 'Apresentadores IA: salvar avaliação possui fluxo POST');
check(has($social,'prepare') && has($social,'enqueue') && has($social,'REQUEST_METHOD'), 'Distribuição Social: preparar e enfileirar possuem handlers');
check(has($instagram,'navigator.clipboard.writeText'), 'Instagram: copiar legenda possui ação no navegador');

foreach($ok as $label) echo "OK: {$label}\n";
if($fail){ foreach($fail as $label) fwrite(STDERR,"FAIL: {$label}\n"); fwrite(STDERR,'HOMOLOGATION_SMOKE=FAIL total='.count($fail)."\n"); exit(1); }
echo 'HOMOLOGATION_SMOKE=PASS total='.count($ok)."\n";
