<?php
$root='/var/www/html';
$fail=[]; $ok=[];
function check($cond,$label){ global $fail,$ok; if($cond)$ok[]=$label; else $fail[]=$label; }
function txt($p){ $v=@file_get_contents($p); return is_string($v)?$v:''; }
function has($s,$needle){ return strpos($s,$needle)!==false; }

$presenterPolicy=$root.'/includes/reporter_presenter_policy.php';
if(is_file($presenterPolicy)) require_once $presenterPolicy;

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
$radarMobileCss=txt($root.'/admin/radar-mobile.css');
check(has($radar,'radar-mobile.css?v=20261015-1') && has($radar,'class="radar-overview"'), 'Radar carrega estilo mobile isolado');
check(has($radar,'<details class="settings-box radar-metrics-details"><summary>Diagnóstico do gargalo</summary>') && has($radar,'<summary>Último lote do backlog 1.3</summary>'), 'Radar recolhe diagnosticos sem esconder dados do servidor');
check(has($radarMobileCss,'@media(max-width:700px)') && has($radarMobileCss,'.radar-command-bar') && has($radarMobileCss,'.cards .stat'), 'Radar possui cards e comandos compactos no celular');

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
$heygenHelper=txt($root.'/includes/heygen_helper.php');
$reporterPublic=txt($root.'/reporter-ia.php');
$adminIndex=txt($root.'/admin/index.php');
check($heygenHelper!=='' && has($heygenHelper,"getenv('HEYGEN_API_KEY')"), 'HeyGen usa HEYGEN_API_KEY do runtime oficial');
check(!has($heygenHelper,"'heygen_api_key' => ['heygen_key'"), 'Helper não aceita aliases legados para secret HeyGen');
check(!has($reporter,'name="heygen_api_key"') && !has($reporterPublic,'name="heygen_api_key"'), 'Painéis não permitem gravar secret HeyGen');
check(!has($adminIndex,'$rep[\'heygen_api_key\']'), 'Dashboard não lê secret HeyGen de JSON');
$conflictingCfg=[
  'heygen_avatar_id'=>'cfg-avatar',
  'heygen_voice_id'=>'cfg-voice',
  'heygen_style_id'=>'cfg-style',
  'heygen_brand_kit_id'=>'cfg-brand',
];
$conflictingJob=[
  'heygen_avatar_id'=>'job-avatar',
  'heygen_voice_id'=>'job-voice',
  'heygen_style_id'=>'job-style',
  'heygen_brand_kit_id'=>'job-brand',
];
$primaryPresenter=function_exists('tvs_reporter_presenter_payload') ? tvs_reporter_presenter_payload($conflictingJob,$conflictingCfg) : [];
$boletimPresenter=function_exists('tvs_reporter_presenter_payload') ? tvs_reporter_presenter_payload($conflictingJob+['boletim'=>['id'=>'smoke']],$conflictingCfg) : [];
check(
  ($primaryPresenter['avatar_id']??null)==='cfg-avatar'
  && ($primaryPresenter['voice_id']??null)==='cfg-voice'
  && ($primaryPresenter['style_id']??null)==='cfg-style'
  && ($primaryPresenter['brand_kit_id']??null)==='cfg-brand',
  'Notícia principal ignora IDs conflitantes do job e usa configuração fixa do Repórter'
);
check(
  ($boletimPresenter['avatar_id']??null)==='job-avatar'
  && ($boletimPresenter['voice_id']??null)==='job-voice'
  && ($boletimPresenter['style_id']??null)==='job-style'
  && ($boletimPresenter['brand_kit_id']??null)==='job-brand',
  'Boletim preserva IDs do catálogo/job diante de configuração conflitante'
);
check($reporter!=='' && has($reporter,'tvs_reporter_presenter_payload($job,$cfg)'), 'Repórter IA usa a política testada no payload HeyGen');
check($presenters!=='' && has($presenters,'Catálogo de Apresentadores IA'), 'Catálogo de apresentadores IA existe');
check($presenters!=='' && has($presenters,"'homologado'") && has($presenters,'Critérios de homologação'), 'Catálogo controla homologação humana');
check($presenters!=='' && has($presenters,'Rosto/fotorealismo') && has($presenters,'Sincronização labial'), 'Catálogo avalia naturalidade audiovisual');
check($menu!=='' && has($menu,'apresentadores-ia.php'), 'Sidebar expõe Apresentadores IA');
$navCss=txt($root.'/admin/admin-navigation.css');
$navJs=txt($root.'/admin/admin-navigation.js');
check($menu!=='' && has($menu,'admin-navigation.css') && has($menu,'admin-navigation.js'), 'Menu compartilha camada responsiva versionada');
check(has($menu,'data-nav-toggle') && has($menu,'aria-controls="tvs-admin-nav"') && has($menu,'id="tvs-admin-nav"'), 'Menu mobile tem controle acessivel');
check(has($navCss,'@media(max-width:1100px)') && has($navCss,'@media(max-width:640px)') && has($navCss,'data-nav-ready'), 'Menu tem layout responsivo e fallback sem JavaScript');
check(has($navJs,"event.key === 'Escape'") && has($navJs,"aria-expanded"), 'Menu mobile suporta Escape e estado acessivel');
check(has($menu,'editor-ia.php') && has($menu,'boletim-ia.php') && has($menu,'reporter-ia.php') && has($menu,'revisao-videos.php') && has($menu,'distribuicao-social.php') && has($menu,'tvplay.php'), 'Menu preserva fluxo Editor, Estudio, Conferencia e Distribuicao');
check(has($menu,'class="side admin-card-navigation"') && has($menu,'class="admin-nav-card"') && has($menu,'<summary>')===false && has($menu,'<summary><span class="admin-nav-card-title">'), 'Menu renderiza cards expansíveis em vez da sidebar extensa');
check(has($menu,"'title'=>'Operação'") && has($menu,"'title'=>'Redação'") && has($menu,"'title'=>'Central Editorial IA'") && has($menu,"'title'=>'Comercial'"), 'Cards agrupam as áreas administrativas');
check(has($menu,'destaques.php') && has($menu,'fontes-status.php') && has($menu,'logout.php') && has($menu,'log-editorial.php'), 'Cards preservam destaques, fontes, auditoria e saída');
check(has($navCss,'.admin-nav-card-links a.active') && has($navCss,'grid-template-columns:repeat(2,minmax(0,1fr))') && has($navCss,'.admin-card-navigation[data-nav-ready="1"]'), 'Cards possuem seleção ativa, mobile em duas colunas e menu recolhível');
check(has($navJs,"setOpen(side.getAttribute('data-dashboard') === '1')") && has($navJs,'aria-expanded'), 'Dashboard inicia com cards visíveis e páginas internas usam expansão acessível');


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
check(has($radar,'function tvs_radar_liberal_city') && has($radar,"'/cidades/'"), 'Radar possui coletor municipal dedicado do Liberal');
check(has($radar,'function tvs_radar_portal_cidade_sumare') && has($radar,'sumare.portaldacidade.com'), 'Radar possui coletor do Portal da Cidade Sumaré');
check(has($radar,'function tvs_radar_history_duplicate') && has($radar,'Duplicata de pauta já aprovada/publicada'), 'Radar bloqueia reentrada de pauta já aprovada/publicada');
check(has($radar,'layered_eligible') && has($radar,'content_usable') && has($radar,'editorial_interest'), 'Radar usa elegibilidade editorial em camadas antes do score');
check(has($radar,'ponte|pontes|viaduto|viadutos') && has($radar,'ordem de servi'), 'Radar reconhece mobilidade/obras como interesse jornalístico');

$registry=txt($root.'/includes/radar_source_registry.php');
$monitor=txt($root.'/monitor_lib.php');
$sourceStatus=txt($root.'/admin/fontes-status.php');
$discoveryCron=txt($root.'/cron_radar.php');
check(has($registry,'function tvs_radar_seed_hml_sources') && has($registry,"getenv('TVSUMARE_ENV')") && has($registry,'@fopen($file,'), 'Cadastro de fontes HML nao sobrescreve producao nem arquivos existentes');
check(has($registry,'tvs_radar_curated_sources_for_city') && has($registry,'Novo Momento') && has($registry,'Portal Hortolândia'), 'Fontes regionais diretas disponiveis no fallback');
check(has($monitor,'// Prioriza RSS direto: URL original') && has($monitor,'if(count($items)>=12)'), 'RSS direto priorizado e amostra ampliada');
check(has($radar,'radar_source_health.json') && has($radar,'$usableDirect') && has($radar,'$sourceMetrics'), 'Radar mede rendimento por fonte e decide complemento pela relevancia local');
check(has($sourceStatus,'radar_source_health.json') && has($sourceStatus,'Sem medição') && has($discoveryCron,'SOURCE_REGISTRY_HML'), 'Painel e cron registram saude de fontes e bootstrap isolado');

check(has($radar,'Sorteio nacional sem fato municipal') && has($radar,'Artigo de opiniao, nao noticia factual'), 'Radar filtra loteria nacional e opiniao antes da fila');
check(has($radar,'tvs_radar_obvious_false_positive($it,$city,$falseReason)'), 'Filtro de falsos positivos permanece no caminho de descoberta');

foreach($ok as $label) echo "OK: {$label}\n";
if($fail){ foreach($fail as $label) fwrite(STDERR,"FAIL: {$label}\n"); fwrite(STDERR,'HOMOLOGATION_SMOKE=FAIL total='.count($fail)."\n"); exit(1); }
echo 'HOMOLOGATION_SMOKE=PASS total='.count($ok)."\n";