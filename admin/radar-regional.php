<?php
require_once __DIR__.'/../includes/outbound_guard.php';
$TVS_RADAR_IS_CLI_CRON = defined('TVS_RADAR_CRON') && TVS_RADAR_CRON && PHP_SAPI === 'cli';
if(!$TVS_RADAR_IS_CLI_CRON){
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
  require_once __DIR__.'/auth.php';
  require_login();
}
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once __DIR__.'/monitor_lib.php';
require_once __DIR__.'/radar_queue_rules.php';
$activeAdmin='radar';
if($TVS_RADAR_IS_CLI_CRON){
  $notice='';
  $error='';
} else {
  $notice=$_SESSION['tvs_flash_notice'] ?? '';
  $error=$_SESSION['tvs_flash_error'] ?? '';
  unset($_SESSION['tvs_flash_notice'], $_SESSION['tvs_flash_error']);
}
$TVS_RADAR_MODE='normal';

$queueFile=dirname(__DIR__).'/data/materias_aprovacao.json';
$newsFile=dirname(__DIR__).'/data/noticias.json';
$fontesFile=dirname(__DIR__).'/data/fontes.json';
$radarConfigFile=dirname(__DIR__).'/data/radar_config.json';
$radarStatusFile=dirname(__DIR__).'/data/radar_status.json';
$radarLogFile=dirname(__DIR__).'/data/radar_log.json';
$radarDiscoveryFile=dirname(__DIR__).'/data/radar_discovery_queue.json';
$radarCursorFile=dirname(__DIR__).'/data/radar_processing_cursor.json';
$cities=['Sumaré','Hortolândia','Paulínia','Nova Odessa','Americana','Campinas'];
$categories=['Cidade','Política','Saúde','Segurança','Educação','Esportes','Cultura','Empregos','Economia','Brasil'];

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function tvs_admin_img($img,$category='Cidade'){
  $img=trim((string)$img);
  if($img==='' || preg_match('~logo-tv-sumare|placeholder|sprite|icon|icone~i',$img)) $img=tvs_category_image($category);
  if(preg_match('~^https?://~i',$img)) return $img;
  if(strpos($img,'../')===0) return $img;
  return '../'.ltrim($img,'/');
}
function tvs_radar_config(){
  global $radarConfigFile;
  $default=['auto_daily'=>true,'per_city'=>20,'last_auto_date'=>''];
  $cfg=tvs_read_json_file($radarConfigFile);
  if(!is_array($cfg)) $cfg=[];
  return array_merge($default,$cfg);
}
function tvs_radar_save_config($cfg){
  global $radarConfigFile;
  $cfg['auto_daily']=!empty($cfg['auto_daily']);
  $cfg['per_city']=max(1,min(40,(int)($cfg['per_city']??20)));
  $dir=dirname($radarConfigFile); if(!is_dir($dir)) @mkdir($dir,0775,true);
  file_put_contents($radarConfigFile,json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
function tvs_radar_status(){
  global $radarStatusFile;
  $st=tvs_read_json_file($radarStatusFile);
  return is_array($st)?$st:[];
}
function tvs_radar_save_status($st){
  global $radarStatusFile;
  $dir=dirname($radarStatusFile); if(!is_dir($dir)) @mkdir($dir,0775,true);
  file_put_contents($radarStatusFile,json_encode($st, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
function tvs_radar_log_event($title,$source,$city,$status,$reason,$url=''){
  global $radarLogFile;
  $items=tvs_read_json_file($radarLogFile);
  if(!is_array($items)) $items=[];
  $items[]=[
    'id'=>uniqid('log_'),
    'title'=>(string)$title,
    'source'=>(string)$source,
    'city'=>(string)$city,
    'status'=>(string)$status,
    'reason'=>(string)$reason,
    'url'=>(string)$url,
    'created_at'=>date('c')
  ];
  $items=array_slice($items,-500);
  tvs_save_json_file($radarLogFile,$items);
}

function tvs_radar_is_volume_mode($mode=null){
  global $TVS_RADAR_MODE;
  $m=$mode!==null ? $mode : ($TVS_RADAR_MODE ?? 'normal');
  return $m==='volume';
}

function tvs_radar_detect_city_from_text($text,$fallback=''){
  $text=tvs_clean_text((string)$text);
  if($text==='') return $fallback;
  $patterns=[
    'Sumaré'=>'~\bSumar[eé]\b~iu',
    'Hortolândia'=>'~\bHortol[aâ]ndia\b~iu',
    'Paulínia'=>'~\bPaul[ií]nia\b~iu',
    'Nova Odessa'=>'~\bNova\s+Odessa\b~iu',
    'Americana'=>'~\bAmericana\b~iu',
    'Campinas'=>'~\bCampinas\b~iu',
  ];
  $hits=[];
  foreach($patterns as $city=>$rx){
    if(preg_match_all($rx,$text,$m)) $hits[$city]=count($m[0]);
  }
  if(!$hits) return $fallback;
  arsort($hits);
  $top=array_key_first($hits);
  // Se houver empate real, mantém a cidade do loop para não trocar indevidamente matérias regionais.
  $vals=array_values($hits);
  if(count($vals)>1 && $vals[0]===$vals[1]) return $fallback ?: $top;
  return $top ?: $fallback;
}

function tvs_is_commercial_candidate($title,$text='',$url=''){
  $all=tvs_lower(tvs_clean_text($title.' '.$text.' '.$url));
  if(preg_match('~\b(buffet|sal[aã]o de festas|eventos privados|conforto e eleg[aâ]ncia|or[cç]amento|loca[cç][aã]o|contrate|fa[cç]a sua reserva|delivery|promo[cç][aã]o|desconto|card[aá]pio|loja|cl[ií]nica|empresa especializada)\b~iu',$all)) return true;
  return false;
}
function tvs_queue_read(){ global $queueFile; return tvs_read_json_file($queueFile); }
function tvs_queue_save($data){ global $queueFile; tvs_save_json_file($queueFile,array_values($data)); }
function tvs_category_from_text($text){
  $t=tvs_lower($text);
  $map=[
    'Saúde'=>['saúde','ubs','hospital','vacina','vacinação','médico','atendimento','dengue','farmácia'],
    'Educação'=>['educação','escola','creche','aluno','matrícula','curso','professor','ensino'],
    'Segurança'=>['segurança','polícia','guarda','prisão','roubo','furto','operação','violência'],
    'Esportes'=>['esporte','futebol','campeonato','atleta','jogos','competição','torneio'],
    'Empregos'=>['emprego','empregos','vaga','vagas','trabalho','processo seletivo','qualificação','qualificacao','pat','mercado livre','contratação','contratacao','oportunidade','currículo','curriculo','rh'],
    'Política'=>['câmara','vereador','prefeito','projeto de lei','sessão','lei','secretário'],
    'Cultura'=>['cultura','show','teatro','evento','festival','música','turismo','feira'],
    'Economia'=>['economia','comércio','empresa','indústria','investimento','negócio','replan','refinaria','poupança','bancos','crédito'],
    'Brasil'=>['agência brasil','governo federal','tse','fies','desenrola','inmet','ministério','brasileiros','copa','argentina','peru'],
  ];
  foreach($map as $cat=>$terms){ foreach($terms as $term){ if(strpos($t,$term)!==false) return $cat; } }
  return 'Cidade';
}

function tvs_is_non_news_candidate($title,$url='',$description=''){
  $t=tvs_lower(tvs_clean_text($title.' '.$description));
  $u=tvs_lower((string)$url);
  if($t==='') return true;
  if(function_exists('tvs_is_skip_or_navigation_title') && tvs_is_skip_or_navigation_title($title)) return true;

  $hasNewsSignal = (function_exists('tvs_has_news_action_signal') && tvs_has_news_action_signal($title.' '.$description))
    || (function_exists('tvs_has_temporal_or_service_signal') && tvs_has_temporal_or_service_signal($title.' '.$description));

  if(function_exists('tvs_is_institutional_profile_text') && tvs_is_institutional_profile_text($title,$url,$description)) return true;
  $badTitle='~^(ir para o conte[uú]do|pular para o conte[uú]do|quem somos|contato|hist[oó]ria|organograma|estrutura administrativa|secretarias?|departamentos?)\b~iu';
  if(preg_match($badTitle, trim((string)$title)) && !$hasNewsSignal) return true;

  // Não bloqueia automaticamente rotas de secretaria/serviços quando houver sinal de notícia,
  // porque muitos sites oficiais publicam campanhas, eventos, editais e serviços temporários nessas áreas.
  if(preg_match('~/(portal/)?(secretarias|secretaria|departamentos|departamento|estrutura|organograma|quem-somos|quem_somos|contato|historia|historia-do-municipio|gabinete|expediente|telefones|enderecos|servicos)(/|$|\?)~iu',$u) && !$hasNewsSignal) return true;

  if(preg_match('~(coordena o planejamento|respons[aá]vel por planejar|planejar e executar|execu[cç][aã]o das pol[ií]ticas p[uú]blicas|atribui[cç][oõ]es da secretaria|compet[eê]ncia da secretaria|estrutura administrativa|hor[aá]rio de atendimento|endere[cç]o|telefone institucional)~iu',$t) && !$hasNewsSignal) return true;
  return false;
}

function tvs_news_detector_score($title,$url='',$text=''){
  $title=tvs_clean_text((string)$title); $text=tvs_clean_text((string)$text); $url=(string)$url;
  $all=tvs_lower($title.' '.$text.' '.$url);
  $score=0;
  if(preg_match('~\b(hoje|amanh[aã]|ontem|segunda|terça|terca|quarta|quinta|sexta|sábado|sabado|domingo|202[0-9]|janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)\b~iu',$all)) $score+=2;
  if(preg_match('~\b(abre|lan[cç]a|inicia|realiza|divulga|anuncia|entrega|aprova|recebe|promove|oferece|inscri[cç][oõ]es|vagas|mutir[aã]o|opera[cç][aã]o|campanha|evento|obra|curso|programa|edital|atendimento|interdi[cç][aã]o|calend[aá]rio|programa[cç][aã]o|sele[cç][aã]o|processo seletivo|vacina[cç][aã]o|matr[ií]cula|feira|show|festival)\b~iu',$all)) $score+=4;
  if(function_exists('tvs_has_temporal_or_service_signal') && tvs_has_temporal_or_service_signal($all)) $score+=2;
  if(preg_match('~\b(Sumar[eé]|Hortol[aâ]ndia|Paul[ií]nia|Nova Odessa|Americana|Campinas)\b~iu',$all)) $score+=2;
  if(preg_match('~\b(prefeitura|c[aâ]mara|governo|ag[eê]ncia brasil|portal|not[ií]cia|jornal)\b~iu',$all)) $score+=1;
  if(preg_match('~/(noticia|noticias|news|imprensa|comunicacao|materia|post|ultimas|cidade)/~iu',$url)) $score+=2;
  if(tvs_strlen($text)>220) $score+=2;
  if(tvs_strlen($text)>800) $score+=2;
  if(tvs_is_non_news_candidate($title,$url,$text)) $score-=8;
  if(preg_match('~\b(PAT|vagas?|empregos?|Mercado Livre|curso|capacita[cç][aã]o|Fies|festival|evento|obra|tr[aâ]nsito|Replan|Paul[ií]nia|Sumar[eé]|Hortol[aâ]ndia|Nova Odessa|Americana|Campinas)\b~iu',$title.' '.$text)) $score+=5;
  if(preg_match('~\b(quem somos|contato|hist[oó]ria|estrutura administrativa|organograma|compet[eê]ncias|atribui[cç][oõ]es)\b~iu',$all)) $score-=4;
  return $score;
}
function tvs_is_real_news_candidate($title,$url='',$text=''){
  return tvs_news_detector_score($title,$url,$text) >= 1;
}
function tvs_extract_facts_block($title,$city,$category,$text,$source,$url){
  $text=tvs_normalize_article_body($text);
  $sent=preg_split('/(?<=[.!?])\s+/u',tvs_clean_text($text));
  $sent=array_values(array_filter(array_map('trim',$sent)));
  $facts=[]; foreach($sent as $s){ if(tvs_strlen($s)>55 && !tvs_is_boilerplate($s)){ $facts[]=$s; if(count($facts)>=8) break; } }
  return "PAUTA ESTRUTURADA\nCidade: {$city}\nCategoria: {$category}\nFonte: {$source}\nURL: {$url}\nTítulo original: {$title}\nFatos extraídos:\n- ".implode("\n- ",$facts);
}

function tvs_source_priority($src){
  $s=tvs_lower((string)$src);
  if(preg_match('/prefeitura|câmara|camara|governo|secretaria|estado|defesa|oficial/u',$s)) return 1;
  if(preg_match('/portal|jornal|notícias|noticias|g1|uol|terra/u',$s)) return 2;
  return 3;
}

function tvs_radar_trusted_source($cand,$url=''){
  $url=trim((string)($url!==''?$url:($cand['url']??$cand['source_url']??'')));
  $host=tvs_radar_source_host($url);
  $trustedHosts=[
    'sumare.sp.gov.br','hortolandia.sp.gov.br','paulinia.sp.gov.br',
    'novaodessa.sp.gov.br','americana.sp.gov.br','campinas.sp.gov.br',
    'saopaulo.sp.gov.br','agenciabrasil.ebc.com.br',
    'g1.globo.com','ge.globo.com','portalhortolandia.com.br',
    'horacampinas.com.br','sbnoticias.com.br','portaldesumare.com.br',
    'noticiasumare.com.br','portalon.com.br','noticiafm.com','novomomento.com.br',
    'tribunaliberal.com.br','liberal.com.br','tododia.com.br','hortonews.com.br','portalporque.com.br',
    'sumare.portaldacidade.com'
  ];
  if($host!=='' && in_array($host,$trustedHosts,true)) return true;

  $source=(string)($cand['source']??'').' '.(string)($cand['source_type']??'');
  return tvs_source_priority($source)===1;
}

function tvs_radar_freshness_gate($cand){
  $status=tvs_radar_temporal_status($cand);
  return !empty($status['ok']);
}

function tvs_radar_age_days($cand){
  $raw=''; foreach(['published_at','pubDate','date','data','created_at'] as $k){ if(!empty($cand[$k])){ $raw=(string)$cand[$k]; break; } }
  if($raw==='') return null;
  $ts=strtotime($raw); if(!$ts) return null;
  return max(0,(int)floor((time()-$ts)/86400));
}
function tvs_radar_temporal_exception($title,$text=''){
  $all=tvs_lower(tvs_clean_text($title.' '.$text));
  return (bool)preg_match('~\b(vagas?|empregos?|PAT|processo seletivo|concurso|inscri[cç][oõ]es abertas|evento futuro|programa[cç][aã]o|calend[aá]rio|vacina[cç][aã]o|campanha|licita[cç][aã]o|edital|obra em andamento|recrutamento|curso|capacita[cç][aã]o)\b~iu',$all);
}
function tvs_radar_temporal_status($cand){
  $age=tvs_radar_age_days($cand);
  // Data da matéria original é um hard gate. Sem data confirmável a pauta não entra
  // automaticamente na redação; evita reciclar conteúdo antigo como notícia nova.
  if($age===null) return ['ok'=>false,'age'=>null,'label'=>'Data da fonte não confirmada','force_review'=>true];

  $isException=tvs_radar_temporal_exception($cand['title']??'', ($cand['description']??'').' '.($cand['text']??''));
  if($isException){
    if($age>7) return ['ok'=>false,'age'=>$age,'label'=>'Serviço/evento antigo: validar manualmente na fonte','force_review'=>true];
    return ['ok'=>true,'age'=>$age,'label'=>$age<=3?'Atual':'Serviço/evento ainda dentro da janela editorial','force_review'=>$age>3];
  }

  if($age>3) return ['ok'=>false,'age'=>$age,'label'=>'Notícia fora da janela de 72 horas','force_review'=>true];
  return ['ok'=>true,'age'=>$age,'label'=>'Atual','force_review'=>false];
}
function tvs_radar_sensitive_topic($title,$text=''){
  $all=tvs_lower(tvs_clean_text($title.' '.$text));
  // Sensível editorialmente: exige revisão humana. Não bloqueia automaticamente toda matéria com "criança" ou "bebê";
  // bloqueia quando há violência, morte, investigação criminal ou exposição de menor.
  if(preg_match('~\b(suic[ií]dio|feminic[ií]dio|homic[ií]dio|assassinato|latroc[ií]nio|estupro|abuso\s+sexual|viol[eê]ncia\s+dom[eé]stica|opera[cç][aã]o\s+policial|crime\s+organizado|tr[aá]fico|pris[aã]o|preso)\b~iu',$all)) return true;
  if(preg_match('~\b(morte|morre|morreu|morto|morta|agress[aã]o|mordidas?|viol[eê]ncia|investiga[cç][aã]o)\b.*\b(crian[cç]a|beb[eê]|adolescente|menor)\b~iu',$all)) return true;
  if(preg_match('~\b(crian[cç]a|beb[eê]|adolescente|menor)\b.*\b(morte|morre|morreu|morto|morta|agress[aã]o|mordidas?|viol[eê]ncia|investiga[cç][aã]o)\b~iu',$all)) return true;
  return false;
}
function tvs_radar_allowed_cities(){ return ['Sumaré','Hortolândia','Paulínia','Nova Odessa','Americana','Campinas']; }
function tvs_radar_city_regex(){ return '~\b(Sumar[eé]|Hortol[aâ]ndia|Paul[ií]nia|Nova\s+Odessa|Americana|Campinas)\b~iu'; }
function tvs_radar_text_mentions_allowed_city($text){ return preg_match(tvs_radar_city_regex(), (string)$text)===1; }
function tvs_radar_fact_text($cand){
  // Texto do fato = título + resumo/descrição. Não usa source/url para não aprovar pauta porque a busca era por "Sumaré".
  return tvs_clean_text(($cand['title']??'').' '.($cand['description']??'').' '.($cand['text']??'').' '.($cand['body']??''));
}
function tvs_radar_source_matches_city($cand,$city){
  $all=tvs_clean_text(($cand['source']??'').' '.($cand['source_type']??'').' '.($cand['url']??'').' '.($cand['city']??''));
  if($city==='Sumaré') return preg_match('~Sumar[eé]~iu',$all)===1;
  if($city==='Hortolândia') return preg_match('~Hortol[aâ]ndia~iu',$all)===1;
  if($city==='Paulínia') return preg_match('~Paul[ií]nia~iu',$all)===1;
  if($city==='Nova Odessa') return preg_match('~Nova\s+Odessa~iu',$all)===1;
  if($city==='Americana') return preg_match('~Americana~iu',$all)===1;
  if($city==='Campinas') return preg_match('~Campinas~iu',$all)===1;
  return false;
}
function tvs_radar_has_outside_city_signal($text){
  $t=tvs_clean_text((string)$text);
  // Lista de contenção: cidades fora do recorte Sumaré/RMC que contaminaram o Google News e portais agregadores.
  return preg_match('~\b(Imperatriz|Jundia[ií]|Limeira|Cosm[oó]polis|Piracicaba|Ribeir[aã]o\s+Preto|S[ãa]o\s+Paulo|Sorocaba|Indaiatuba|Valinhos|Vinhedo|Mogi|Osasco|Guarulhos|Santos|Caraguatatuba|Ubatuba|S[ãa]o\s+Sebasti[ãa]o|Ilhabela|Carapicu[ií]ba|S[ãa]o\s+Bernardo|Rio\s+de\s+Janeiro|Bras[ií]lia|Curitiba|Belo\s+Horizonte)\b~iu',$t)===1;
}
function tvs_radar_stale_event_signal($title,$text=''){
  $all=tvs_lower(tvs_clean_text($title.' '.$text));
  // Conteúdo sazonal/antigo não deve voltar ao Radar como notícia atual.
  if(preg_match('~\b(carnaval|natal|ano novo|r[eé]veillon|elei[cç][oõ]es?\s+20[0-9]{2}|campanha eleitoral|segundo turno|retrospectiva|arquivo)\b~iu',$all)) return true;
  return false;
}
function tvs_radar_text_mentions_city($text,$city){
  $patterns=[
    'Sumaré'=>'~\bSumar[eé]\b~iu',
    'Hortolândia'=>'~\bHortol[aâ]ndia\b~iu',
    'Paulínia'=>'~\bPaul[ií]nia\b~iu',
    'Nova Odessa'=>'~\bNova\s+Odessa\b~iu',
    'Americana'=>'~\bAmericana\b~iu',
    'Campinas'=>'~\bCampinas\b~iu'
  ];
  return isset($patterns[$city]) && preg_match($patterns[$city],(string)$text)===1;
}
function tvs_radar_candidate_region_ok($cand,$requestedCity,&$reason=''){
  $title=$cand['title']??''; $desc=$cand['description']??'';
  $fact=tvs_radar_fact_text($cand);
  if(tvs_radar_stale_event_signal($title,$desc)){ $reason='Evento antigo/sazonal detectado'; return false; }

  // Primeiro confirma a cidade-alvo. Uma menção secundária a São Paulo ou outra
  // cidade não pode apagar uma pauta cujo fato é claramente de Americana,
  // Campinas, Hortolândia etc.
  $cityConfirmed=tvs_radar_text_mentions_city($fact,(string)$requestedCity);
  if(!$cityConfirmed && !empty($cand['source_city_confirmed'])){
    $cityConfirmed=in_array((string)$requestedCity,tvs_radar_allowed_cities(),true);
  }
  if(!$cityConfirmed){
    if(tvs_radar_has_outside_city_signal($fact)){
      $reason='Fora da região monitorada';
      return false;
    }
    $reason='Sem evidência da cidade-alvo no fato ou na editoria municipal confirmada da fonte';
    return false;
  }

  return true;
}
function tvs_radar_extract_vagas_number($text){
  $t=tvs_lower(tvs_clean_text((string)$text));
  if(preg_match('~(\d+(?:[\.,]\d+)?)\s*(mil)\s+vagas~iu',$t,$m)) return (int)(floatval(str_replace(',','.',$m[1]))*1000);
  if(preg_match('~(\d+)\s+vagas~iu',$t,$m)) return (int)$m[1];
  return 0;
}
function tvs_radar_editorial_score($title,$city,$category,$source,$text,$url='',$ageDays=null){
  $fact=tvs_lower(tvs_clean_text($title.' '.$category.' '.$text));
  $all=tvs_lower(tvs_clean_text($title.' '.$category.' '.$source.' '.$text.' '.$url));
  if(tvs_radar_stale_event_signal($title,$text)) return 0;
  if(tvs_radar_has_outside_city_signal($fact) && !tvs_radar_text_mentions_allowed_city($fact)) return 0;

  $score=0;
  // 1) Recorte regional: precisa haver cidade no fato ou fonte oficial local.
  if(tvs_radar_text_mentions_allowed_city($fact)) $score+=25;
  elseif(in_array($city,tvs_radar_allowed_cities(),true)) $score+=10;

  // 2) Qualidade da fonte.
  if(preg_match('~\b(prefeitura|c[aâ]mara|governo\s+sp|defesa\s+civil|secretaria|hospital|ubs|pat)\b~iu',$all)) $score+=14;
  elseif(preg_match('~\b(g1|eptv|cbn|correio|rac|jornal|portal|liberal)\b~iu',$all)) $score+=9;

  // 3) Interesse público por editoria.
  // Empregos continuam relevantes, mas não dominam toda a régua.
  if(preg_match('~\b(empregos?|vagas?|pat|recrutamento|processo\s+seletivo|concurso|capacita[cç][aã]o)\b~iu',$fact)) $score+=12;
  if(preg_match('~\b(sa[uú]de|hospital|upa|ubs|vacina[cç][aã]o|dengue|atendimento|mutir[aã]o)\b~iu',$fact)) $score+=22;
  if(preg_match('~\b(educa[cç][aã]o|escola|creche|matr[ií]cula|alunos?|curso|unicamp|fies)\b~iu',$fact)) $score+=22;
  if(preg_match('~\b(investimento|empresa|ind[uú]stria|com[eé]rcio|economia|mercado\s+livre|replan|petrobras|desenvolvimento\s+econ[oô]mico|empreendedorismo|neg[oó]cios)\b~iu',$fact)) $score+=18;
  if(preg_match('~\b(obras?|mobilidade|tr[aâ]nsito|interdi[cç][aã]o|transporte|[aá]gua|energia|defesa\s+civil|servi[cç]os?\s+p[uú]blicos?|estiagem)\b~iu',$fact)) $score+=20;
  // Cultura, eventos e esportes passam a ter peso de interesse público.
  if(preg_match('~\b(cultura|cultural|evento|eventos|festival|show|shows|feira|agenda|programa[cç][aã]o|teatro|cinema|exposi[cç][aã]o|m[uú]sica|turismo|lazer)\b~iu',$fact)) $score+=24;
  if(preg_match('~\b(esporte|esportes|campeonato|torneio|jogos?|atleta|competi[cç][aã]o|corrida|futebol|v[oô]lei|basquete)\b~iu',$fact)) $score+=21;
  if(preg_match('~\b(pol[ií]cia|pris[aã]o|preso|opera[cç][aã]o|acidente|homic[ií]dio|assassinato|tr[aá]fico)\b~iu',$fact)) $score+=24;

  // 4) Magnitude real: 100 vagas é bom, mas não pode virar 100 automático; 2 mil vagas sim é prioridade.
  $vagas=tvs_radar_extract_vagas_number($fact);
  if($vagas>=1500) $score+=10; elseif($vagas>=500) $score+=7; elseif($vagas>=100) $score+=4; elseif($vagas>0) $score+=2;

  // 5) Ação/serviço concreto.
  if(preg_match('~\b(abre|anuncia|oferece|realiza|lan[cç]a|entrega|divulga|inscri[cç][oõ]es|recrutamento|feir[aã]o|mutir[aã]o|campanha|programa|edital|atendimento)\b~iu',$fact)) $score+=8;

  // 6) Atualidade.
  if($ageDays!==null){
    if($ageDays<=1) $score+=14;
    elseif($ageDays<=3) $score+=10;
    elseif($ageDays<=7) $score+=4;
    elseif($ageDays<=15) $score-=15;
    else $score-=45;
  }

  // 7) Sensibilidade define revisão humana, não reduz a relevância editorial.
  return max(0,min(100,$score));
}
function tvs_radar_status_from_score($score,$sensitive=false){
  if($score>=85) $status='Prioridade máxima';
  elseif($score>=70) $status='Destaque';
  elseif($score>=50) $status='Publicável';
  elseif($score>=30) $status='Revisão';
  else $status='Descartar';

  if($sensitive && $status!=='Descartar'){
    return ['review_level'=>'revisao_obrigatoria','editorial_status'=>$status,'sensitive'=>true];
  }
  if($status==='Descartar') return ['review_level'=>'descartar','editorial_status'=>'Descartar','sensitive'=>$sensitive];
  if($status==='Revisão') return ['review_level'=>'precisa_revisao','editorial_status'=>'Revisão','sensitive'=>false];
  return ['review_level'=>'normal','editorial_status'=>$status,'sensitive'=>false];
}
function tvs_radar_can_direct_approve($m){
  // Aprovação direta exige matéria jornalística minimamente completa
  // e passagem confirmada pelo Editor de Matéria IA.
  if(empty($m['ai_editor_processed'])) return false;
  if(!empty($m['sensitive_review_required'])) return false;
  if(function_exists('tvs_radar_queue_item_readiness')){
    $readiness=tvs_radar_queue_item_readiness($m);
    if(empty($readiness['ready'])) return false;
  }
  return ($m['review_level']??'')!=='revisao_obrigatoria'
    && ($m['review_level']??'')!=='precisa_revisao'
    && ($m['editorial_status']??'')!=='Descartar'
    && ($m['editorial_status']??'')!=='Revisão'
    && ($m['editorial_status']??'')!=='Nota curta';
}
function tvs_radar_infer_queue_age_days($q){
  if(is_numeric($q['age_days']??null)) return max(0,(int)$q['age_days']);

  foreach(['published_at','pubDate','date','data','source_published_at','created_at'] as $k){
    $raw=trim((string)($q[$k]??''));
    if($raw==='') continue;
    $ts=strtotime($raw);
    if($ts!==false && $ts<=time()+86400) return max(0,(int)floor((time()-$ts)/86400));
  }

  $text=tvs_clean_text(
    ($q['title']??'').' '.
    ($q['subtitle']??'').' '.
    ($q['summary']??'').' '.
    ($q['body']??'')
  );

  if(preg_match_all('~\\b([0-3]?\\d)[/\\-]([01]?\\d)(?:[/\\-](20\\d{2}))?\\b~u',$text,$m,PREG_SET_ORDER)){
    $best=null;
    $now=time();
    foreach($m as $hit){
      $day=(int)$hit[1]; $month=(int)$hit[2]; $year=!empty($hit[3])?(int)$hit[3]:(int)date('Y');
      if(!checkdate($month,$day,$year)) continue;
      $ts=mktime(12,0,0,$month,$day,$year);
      if($ts>$now+86400) continue;
      $age=max(0,(int)floor(($now-$ts)/86400));
      if($best===null || $age<$best) $best=$age;
    }
    if($best!==null) return $best;
  }

  return null;
}

function tvs_radar_enforce_queue_rules($save=true){
  $queue=tvs_queue_read(); $new=[]; $removed=0; $changed=0;
  foreach($queue as $q){
    $reason='';
    $cand=[
      'title'=>$q['title']??'',
      'description'=>($q['subtitle']??'').' '.($q['summary']??'').' '.($q['body']??''),
      'url'=>$q['source_url']??'',
      'source'=>$q['source']??'',
      'source_type'=>$q['source']??'',
      'city'=>$q['city']??''
    ];
    $city=$q['city']??'';

    if(!in_array($city,tvs_radar_allowed_cities(),true)){
      $removed++;
      tvs_radar_log_event($q['title']??'', $q['source']??'', $city, 'DESCARTADA', 'Cidade fora da lista monitorada', $q['source_url']??'');
      continue;
    }

    if(!tvs_radar_candidate_region_ok($cand,$city,$reason)){
      $removed++;
      tvs_radar_log_event($q['title']??'', $q['source']??'', $city, 'DESCARTADA', $reason, $q['source_url']??'');
      continue;
    }

    $sourceUrl=trim((string)($q['source_url']??''));
    if($sourceUrl==='' || tvs_radar_is_google_news_url($sourceUrl)){
      $removed++;
      tvs_radar_log_event($q['title']??'', $q['source']??'', $city, 'DESCARTADA', 'Fonte original não confirmada', $sourceUrl);
      continue;
    }

    $age=tvs_radar_infer_queue_age_days($q);
    $queueText=($q['subtitle']??'').' '.($q['summary']??'').' '.($q['body']??'');
    $isTemporalException=tvs_radar_temporal_exception($q['title']??'', $queueText);

    if(!is_numeric($age)){
      $removed++;
      tvs_radar_discard($q,$city,'Data da matéria original não confirmada');
      continue;
    }

    $maxAge=$isTemporalException?7:3;
    if((int)$age>$maxAge){
      $removed++;
      tvs_radar_discard($q,$city,'Fora da janela editorial: '.(int)$age.' dias; limite '.$maxAge.' dias');
      continue;
    }

    $score=tvs_radar_editorial_score(
      $q['title']??'',
      $city,
      $q['category']??'',
      $q['source']??'',
      $queueText,
      $sourceUrl,
      (int)$age
    );
    $sensitive=tvs_radar_sensitive_topic($q['title']??'', $queueText);
    $st=tvs_radar_status_from_score($score,$sensitive);

    if($st['review_level']==='descartar'){
      $removed++;
      tvs_radar_log_event($q['title']??'', $q['source']??'', $city, 'DESCARTADA', 'Score editorial insuficiente: '.$score, $sourceUrl);
      continue;
    }

    if(($q['editorial_score']??null)!==$score || ($q['editorial_status']??'')!==$st['editorial_status']) $changed++;

    $image=trim((string)($q['image']??''));
    $hasImage=$image!=='' && tvs_is_valid_image_url($image);

    $editorProcessed=!empty($q['ai_editor_processed']);
    $q['editorial_score']=$score;
    $q['review_level']=$st['review_level'];
    $q['editorial_status']=$st['editorial_status'];
    $q['editorial_state']=$editorProcessed?'qualified':'awaiting_ai_editor';
    $q['region_status']='confirmed';
    $q['freshness_status']='current';
    $q['source_status']='original';
    $q['duplicate_status']='unique';
    $q['publication_eligible']=$editorProcessed?1:0;
    $q['video_eligible']=$editorProcessed?1:0;
    if(!$editorProcessed){
      $q['review_level']='precisa_revisao';
      $q['editorial_status']='Aguardando Editor IA';
      $q['ai_editor_stage']=$q['ai_editor_stage']??'pending';
    }
    $q['image_status']=$hasImage?'verified':'missing';
    $q['home_eligible']=$hasImage?1:0;
    $q['image_review_required']=0;
    $q['sensitive_review_required']=!empty($st['sensitive']) ? 1 : 0;

    if(!empty($st['sensitive'])) $q['sensitive_review_reason']='Pauta sensível ou de alto impacto: revisão humana obrigatória antes da publicação.';
    elseif(isset($q['sensitive_review_reason'])) $q['sensitive_review_reason']='';

    $new[]=$q;
  }

  // Deduplicação final da fila: mesma cidade + mesmo título normalizado representa
  // a mesma pauta, ainda que tenha sido descoberta por feeds/fontes diferentes.
  $dedup=[]; $dedupOrder=[];
  foreach($new as $item){
    $cityKey=tvs_slug((string)($item['city']??''));
    $titleKey=tvs_radar_normalize_title_for_match((string)($item['title']??''));
    $sourceUrl=trim((string)($item['source_url']??''));
    $key=$sourceUrl!=='' ? 'url:'.$sourceUrl : $cityKey.'|'.$titleKey;
    if($sourceUrl==='' && $titleKey==='') $key='id|'.(string)($item['id']??uniqid('queue_'));

    if(!isset($dedup[$key])){
      $dedup[$key]=$item;
      $dedupOrder[]=$key;
      continue;
    }

    $current=$dedup[$key];
    $currentRank=(!empty($current['ai_editor_processed'])?1000:0)
      +(!empty($current['publication_eligible'])?500:0)
      +(int)($current['sf_score']??0)
      +(int)($current['editorial_score']??0);
    $itemRank=(!empty($item['ai_editor_processed'])?1000:0)
      +(!empty($item['publication_eligible'])?500:0)
      +(int)($item['sf_score']??0)
      +(int)($item['editorial_score']??0);

    if($itemRank>$currentRank){
      tvs_radar_log_event($current['title']??'', $current['source']??'', $current['city']??'', 'DESCARTADA', 'Duplicata editorial consolidada na fila', $current['source_url']??'');
      $dedup[$key]=$item;
    } else {
      tvs_radar_log_event($item['title']??'', $item['source']??'', $item['city']??'', 'DESCARTADA', 'Duplicata editorial consolidada na fila', $item['source_url']??'');
    }
    $removed++;
  }
  $new=[];
  foreach($dedupOrder as $key) if(isset($dedup[$key])) $new[]=$dedup[$key];

  $new=tvs_radar_normalize_queue_by_rules($new);

  if($save) tvs_queue_save($new);
  return ['removed'=>$removed,'changed'=>$changed,'total'=>count($new)];
}

function tvs_radar_is_google_news_url($url){
  return preg_match(
    '~^https?://(?:news\.)?google\.com/(?:rss/)?articles/~i',
    trim((string)$url)
  )===1;
}

function tvs_radar_external_url_is_valid($url){
  $url=html_entity_decode(trim((string)$url),ENT_QUOTES|ENT_HTML5,'UTF-8');
  if(preg_match('~\.(?:jpg|jpeg|png|gif|webp|svg)(?:\?|$)~i',$url)) return false;
  return function_exists('tvs_outbound_url_is_allowed') && tvs_outbound_url_is_allowed($url);
}


function tvs_radar_strong_entity_score($expected,$candidate,$city=''){
  $expectedText=tvs_clean_text((string)$expected);
  $candidateText=tvs_clean_text((string)$candidate);
  $checks=[];

  preg_match_all('~\b\d+(?:[\.,]\d+)?\b~u',$expectedText,$nums);
  $numbers=array_values(array_unique($nums[0]??[]));
  if($numbers){
    $matched=0;
    foreach($numbers as $n){ if(stripos($candidateText,$n)!==false) $matched++; }
    $checks[]=$matched/count($numbers);
  }

  preg_match_all('~\b\p{Lu}[\p{L}]{2,}(?:\s+\p{Lu}[\p{L}]{2,})*\b~u',$expectedText,$names);
  $proper=array_values(array_unique(array_filter($names[0]??[],static fn($x)=>tvs_strlen($x)>=4)));
  if($proper){
    $matched=0;
    foreach($proper as $name){ if(stripos($candidateText,$name)!==false) $matched++; }
    $checks[]=$matched/count($proper);
  }

  if(trim((string)$city)!==''){
    $checks[]=stripos(tvs_lower($candidateText),tvs_lower((string)$city))!==false?1:0;
  }

  if(!$checks) return 100;
  return (int)round((array_sum($checks)/count($checks))*100);
}

function tvs_radar_resolve_by_bing_web($title,$city='',$source='',$domain=''){
  $title=trim((string)$title);
  if($title==='') return '';

  $queries=[];
  $host=tvs_radar_source_host($domain);
  if($host!==''){
    $queries[]='site:'.$host.' "'.$title.'"';
    $queries[]='site:'.$host.' '.$title;
  }
  $base='"'.$title.'"';
  if(trim((string)$city)!=='') $base.=' '.trim((string)$city);
  if(trim((string)$source)!=='') $base.=' '.trim((string)$source);
  $queries[]=$base;
  $queries[]=$title.' '.trim((string)$city).' '.trim((string)$source);

  $bestUrl='';
  $bestScore=0;
  foreach(array_values(array_unique(array_filter(array_map('trim',$queries)))) as $query){
    $url='https://www.bing.com/search?format=rss&q='.rawurlencode($query);
    $xml=tvs_fetch_url($url);
    if($xml==='') continue;

    libxml_use_internal_errors(true);
    $sx=@simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA);
    if(!$sx || !isset($sx->channel->item)) continue;

    foreach($sx->channel->item as $item){
      $candidateTitle=tvs_clean_text((string)($item->title??''));
      $candidateUrl=trim((string)($item->link??''));
      if(!tvs_radar_external_url_is_valid($candidateUrl)) continue;

      $candidateHost=tvs_radar_source_host($candidateUrl);
      if($candidateHost==='' || str_contains($candidateHost,'bing.com') || str_contains($candidateHost,'google.com')) continue;
      if($host!=='' && $candidateHost!==$host && !str_ends_with($candidateHost,'.'.$host) && !str_ends_with($host,'.'.$candidateHost)) continue;
      if(!tvs_radar_is_article_path($candidateUrl,$title,$city)) continue;

      $score=tvs_radar_title_match_score($title,$candidateTitle);
      if($score>$bestScore){
        $bestScore=$score;
        $bestUrl=$candidateUrl;
      }
    }
    if($bestScore>=70) break;
  }

  return $bestScore>=50 ? $bestUrl : '';
}

function tvs_radar_resolve_by_bing_news($title,$city='',$source=''){
  $query='"'.trim((string)$title).'"';

  if(trim((string)$city)!==''){
    $query.=' '.trim((string)$city);
  }

  if(trim((string)$source)!==''){
    $query.=' '.trim((string)$source);
  }

  $url='https://www.bing.com/news/search?q='
    .rawurlencode($query)
    .'&format=rss&setlang=pt-br';

  $xml=tvs_fetch_url($url);

  if($xml==='') return '';

  libxml_use_internal_errors(true);
  $sx=@simplexml_load_string(
    $xml,
    'SimpleXMLElement',
    LIBXML_NOCDATA
  );

  if(!$sx || !isset($sx->channel->item)) return '';

  $bestUrl='';
  $bestScore=0;

  foreach($sx->channel->item as $item){
    $candidateTitle=tvs_clean_text((string)($item->title??''));
    $candidateUrl=trim((string)($item->link??''));

    if(!tvs_radar_external_url_is_valid($candidateUrl)){
      continue;
    }

    $score=tvs_radar_title_match_score(
      $title,
      $candidateTitle
    );

    if($score>$bestScore){
      $bestScore=$score;
      $bestUrl=$candidateUrl;
    }
  }

  return $bestScore>=55 ? $bestUrl : '';
}

function tvs_radar_resolve_by_sitemap($domain,$title,$city=''){
  static $cache=[];
  $domain=rtrim(trim((string)$domain),'/');
  $host=tvs_radar_source_host($domain);
  if($host==='' || trim((string)$title)==='') return '';

  $cacheKey=md5($domain.'|'.$title.'|'.$city);
  if(array_key_exists($cacheKey,$cache)) return $cache[$cacheKey];

  $queue=[
    $domain.'/sitemap.xml',
    $domain.'/sitemap_index.xml',
    $domain.'/wp-sitemap.xml',
    $domain.'/wp-sitemap-posts-post-1.xml'
  ];
  $seen=[]; $bestUrl=''; $bestScore=0; $visited=0;

  while($queue && $visited<4){
    $sitemap=array_shift($queue);
    if(isset($seen[$sitemap])) continue;
    $seen[$sitemap]=1;
    $visited++;

    $xml=tvs_fetch_url($sitemap);
    if($xml==='') continue;

    if(!preg_match_all('~<loc>\s*(.*?)\s*</loc>~is',$xml,$m)) continue;
    foreach(array_slice($m[1],0,1200) as $rawLoc){
      $loc=html_entity_decode(trim(strip_tags((string)$rawLoc)),ENT_QUOTES|ENT_HTML5,'UTF-8');
      if($loc==='' || tvs_radar_source_host($loc)!==$host) continue;

      if(preg_match('~\.xml(?:\?|$)~i',$loc)){
        if(count($seen)<12) $queue[]=$loc;
        continue;
      }

      if(!tvs_radar_is_article_path($loc,$title,$city)) continue;
      $slug=(string)basename((string)(parse_url($loc,PHP_URL_PATH)??''));
      $score=tvs_radar_title_match_score($title,str_replace(['-','_'],' ',$slug));
      if($score>$bestScore){
        $bestScore=$score;
        $bestUrl=$loc;
      }
    }
  }

  if($bestScore>=48 && $bestUrl!==''){
    $validation=tvs_radar_validate_resolved_article($bestUrl,$title,$city);
    if(!empty($validation['ok'])) return $cache[$cacheKey]=$bestUrl;
  }

  return $cache[$cacheKey]='';
}

function tvs_radar_resolve_by_bing_site($domain,$title,$city=''){
  $domain=rtrim(trim((string)$domain),'/');
  $host=tvs_radar_source_host($domain);
  if($host==='' || trim((string)$title)==='') return '';

  $query='site:'.$host.' "'.trim((string)$title).'"';
  if(trim((string)$city)!=='') $query.=' '.trim((string)$city);

  $url='https://www.bing.com/news/search?q='
    .rawurlencode($query)
    .'&format=rss&setlang=pt-br';

  $xml=tvs_fetch_url($url);
  if($xml==='') return '';

  libxml_use_internal_errors(true);
  $sx=@simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA);
  if(!$sx || !isset($sx->channel->item)) return '';

  $bestUrl='';
  $bestScore=0;
  foreach($sx->channel->item as $item){
    $candidateTitle=tvs_clean_text((string)($item->title??''));
    $candidateUrl=trim((string)($item->link??''));
    if(!tvs_radar_external_url_is_valid($candidateUrl)) continue;
    if(tvs_radar_source_host($candidateUrl)!==$host) continue;

    $score=tvs_radar_title_match_score($title,$candidateTitle);
    if($score>$bestScore){
      $bestScore=$score;
      $bestUrl=$candidateUrl;
    }
  }

  return $bestScore>=52 ? $bestUrl : '';
}


function tvs_radar_source_host($url){
  $host=tvs_lower((string)(parse_url((string)$url,PHP_URL_HOST)??''));
  return preg_replace('~^www\.~i','',$host);
}

function tvs_radar_absolute_source_url($domain,$url){
  $domain=rtrim((string)$domain,'/');
  $url=html_entity_decode(trim((string)$url),ENT_QUOTES|ENT_HTML5,'UTF-8');

  if($url==='') return '';

  if(preg_match('~^https?://~i',$url)){
    return $url;
  }

  if(str_starts_with($url,'//')){
    $scheme=(string)(parse_url($domain,PHP_URL_SCHEME)??'https');
    return $scheme.':'.$url;
  }

  if(str_starts_with($url,'/')){
    return $domain.$url;
  }

  return $domain.'/'.$url;
}

function tvs_radar_source_section_urls($domain,$city=''){
  $domain=rtrim(trim((string)$domain),'/');
  $urls=[];

  if($domain==='') return [];

  $citySlug=tvs_slug((string)$city);

  if($citySlug!==''){
    $urls[]=$domain.'/'.$citySlug;
    $urls[]=$domain.'/cidade/'.$citySlug;
    $urls[]=$domain.'/cidades/'.$citySlug;
    $urls[]=$domain.'/noticias/'.$citySlug;
    $urls[]=$domain.'/noticias/cidade/'.$citySlug;
    $urls[]=$domain.'/categoria/'.$citySlug;
  }

  // Americana publica a listagem e as matérias no mesmo script, diferenciadas
  // pelos parâmetros a=noticias e a=noticia&id=... .
  if(tvs_radar_source_host($domain)==='americana.sp.gov.br'){
    $urls[]=$domain.'/americana-index.php?a=noticias';
  }

  $urls[]=$domain.'/noticias';
  $urls[]=$domain.'/cidades';
  $urls[]=$domain;

  return array_values(array_unique($urls));
}


function tvs_radar_validate_resolved_article($url,$expectedTitle,$city='',$expectedDomain='',$expectedPublishedAt='',$method=''){
  if(!tvs_radar_external_url_is_valid($url)){
    return ['ok'=>false,'reason'=>'URL externa inválida','confidence'=>0];
  }

  if(!tvs_radar_is_article_path($url,$expectedTitle,$city)){
    return ['ok'=>false,'reason'=>'URL corresponde a seção, categoria ou página genérica','confidence'=>0];
  }

  $resolvedHost=tvs_radar_source_host($url);
  $expectedHost=tvs_radar_source_host($expectedDomain);
  $domainScore=100;
  if($expectedHost!==''){
    $sameDomain=$resolvedHost===$expectedHost
      || str_ends_with($resolvedHost,'.'.$expectedHost)
      || str_ends_with($expectedHost,'.'.$resolvedHost);
    $domainScore=$sameDomain?100:0;
  }

  $html=tvs_fetch_url($url);
  $article=tvs_extract_article($url,$expectedTitle);
  $articleTitle=trim((string)($article['title']??''));
  $body=trim((string)($article['body']??''));
  $titleScore=tvs_radar_title_match_score($expectedTitle,$articleTitle);
  $entityScore=tvs_radar_strong_entity_score($expectedTitle,$articleTitle.' '.tvs_substr($body,0,2200),$city);

  $publishedAt=$html!=='' ? tvs_radar_extract_published_at_from_html($html) : '';
  $dateScore=70;
  if(trim((string)$expectedPublishedAt)!==''){
    $a=strtotime((string)$expectedPublishedAt);
    $b=$publishedAt!==''?strtotime($publishedAt):false;
    if($a && $b){
      $hours=abs($a-$b)/3600;
      if($hours<=24) $dateScore=100;
      elseif($hours<=72) $dateScore=85;
      elseif($hours<=168) $dateScore=65;
      else $dateScore=20;
    } else {
      $dateScore=40;
    }
  }

  $canonical='';
  if($html!=='' && preg_match('~<link\b[^>]*rel=["\'][^"\']*canonical[^"\']*["\'][^>]*href=["\']([^"\']+)["\']~i',$html,$m)){
    $canonical=tvs_radar_absolute_source_url($url,html_entity_decode(trim((string)$m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
  }
  $canonicalHost=$canonical!==''?tvs_radar_source_host($canonical):'';
  if($expectedHost!=='' && $canonicalHost!=='' && $canonicalHost!==$expectedHost && !str_ends_with($canonicalHost,'.'.$expectedHost)){
    $domainScore=min($domainScore,25);
  }

  if(tvs_strlen($body)<80){
    return ['ok'=>false,'reason'=>'Página sem conteúdo factual mínimo para validação da fonte','title_score'=>$titleScore,'domain_score'=>$domainScore,'date_score'=>$dateScore,'entity_score'=>$entityScore,'confidence'=>0];
  }

  $methodBase=[
    'source_domain_title_match'=>86,
    'source_feed_title_match'=>84,
    'source_sitemap_title_match'=>84,
    'bing_site_title_match'=>88,
    'bing_web_site_title_match'=>86,
    'google_news_resolution'=>92,
    'bing_news_title_match'=>92,
    'bing_web_title_match'=>90,
    'known_current_title'=>82
  ];
  $threshold=$methodBase[$method]??86;

  $confidence=(int)round(
    ($titleScore*0.45)+
    ($domainScore*0.25)+
    ($dateScore*0.15)+
    ($entityScore*0.15)
  );

  if($titleScore<55){
    return ['ok'=>false,'reason'=>'Título da página não corresponde à pauta','title_score'=>$titleScore,'domain_score'=>$domainScore,'date_score'=>$dateScore,'entity_score'=>$entityScore,'confidence'=>$confidence];
  }
  if($expectedHost!=='' && $domainScore<80){
    return ['ok'=>false,'reason'=>'Domínio resolvido não corresponde ao veículo esperado','title_score'=>$titleScore,'domain_score'=>$domainScore,'date_score'=>$dateScore,'entity_score'=>$entityScore,'confidence'=>$confidence];
  }
  if($confidence<$threshold){
    return ['ok'=>false,'reason'=>'Confiança insuficiente para aceitar resolução','title_score'=>$titleScore,'domain_score'=>$domainScore,'date_score'=>$dateScore,'entity_score'=>$entityScore,'confidence'=>$confidence,'threshold'=>$threshold];
  }

  return [
    'ok'=>true,
    'article'=>$article,
    'title_score'=>$titleScore,
    'domain_score'=>$domainScore,
    'date_score'=>$dateScore,
    'entity_score'=>$entityScore,
    'confidence'=>$confidence,
    'threshold'=>$threshold,
    'published_at'=>$publishedAt,
    'canonical'=>$canonical,
    'resolved_domain'=>$resolvedHost,
    'expected_domain'=>$expectedHost,
    'method'=>$method
  ];
}

function tvs_radar_find_article_in_html($domain,$html,$title){
  $bestUrl='';
  $bestScore=0;
  $expectedHost=tvs_radar_source_host($domain);

  if(
    $html==='' ||
    !preg_match_all(
      '~<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',
      $html,
      $matches,
      PREG_SET_ORDER
    )
  ){
    return '';
  }

  foreach($matches as $match){
    $candidateUrl=tvs_radar_absolute_source_url($domain,$match[1]??'');
    $candidateTitle=tvs_clean_text($match[2]??'');

    if($candidateUrl==='' || $candidateTitle==='') continue;

    $candidateHost=tvs_radar_source_host($candidateUrl);

    if(
      $candidateHost==='' ||
      $candidateHost!==$expectedHost
    ){
      continue;
    }

    $path=(string)(parse_url($candidateUrl,PHP_URL_PATH)??'');
    $normalizedPath=trim($path,'/');
    $segments=array_values(array_filter(explode('/',$normalizedPath)));

    $citySlug=tvs_slug((string)($GLOBALS['tvs_radar_resolution_city']??''));

    if(!tvs_radar_is_article_path(
      $candidateUrl,
      $title,
      (string)($GLOBALS['tvs_radar_resolution_city']??'')
    )){
      continue;
    }

    $score=tvs_radar_title_match_score(
      $title,
      $candidateTitle
    );

    $queryParams=[];
    parse_str((string)(parse_url($candidateUrl,PHP_URL_QUERY)??''),$queryParams);
    $isQueryArticle=
      tvs_lower((string)($queryParams['a']??''))==='noticia'
      && preg_match('~^[0-9]+$~',(string)($queryParams['id']??''))===1;

    $slugText=str_replace(
      ['-','_'],
      ' ',
      basename($normalizedPath)
    );

    // Em portais com artigo por ID, o texto da âncora substitui o slug genérico.
    $slugScore=$isQueryArticle
      ? $score
      : tvs_radar_title_match_score($title,$slugText);

    if($score<55 || $slugScore<45){
      continue;
    }

    // Prioriza correspondência textual e estrutural do endereço.
    $combinedScore=(int)round(($score*0.70)+($slugScore*0.30));

    if($combinedScore>$bestScore){
      $bestScore=$combinedScore;
      $bestUrl=$candidateUrl;
    }
  }

  return $bestScore>=55 ? $bestUrl : '';
}

function tvs_radar_source_feed_candidates($domain,$title,$city=''){
  static $feedCache=[];
  $domain=rtrim(trim((string)$domain),'/');
  $host=tvs_radar_source_host($domain);
  if($host==='' || trim((string)$title)==='') return [];

  $feedUrls=[
    $domain.'/feed/',
    $domain.'/feed',
    $domain.'/rss',
    $domain.'/rss.xml',
    $domain.'/feed.xml',
    $domain.'/atom.xml'
  ];
  $items=[];
  foreach(array_values(array_unique($feedUrls)) as $feedUrl){
    $cacheKey=$host.'|'.$feedUrl;
    if(!array_key_exists($cacheKey,$feedCache)){
      $xml=tvs_fetch_url($feedUrl);
      $feedCache[$cacheKey]=is_string($xml)?$xml:'';
    }
    $xml=$feedCache[$cacheKey];
    if($xml==='') continue;

    libxml_use_internal_errors(true);
    $sx=@simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA);
    if(!$sx) continue;
    $nodes=[];
    if(isset($sx->channel->item)) $nodes=$sx->channel->item;
    elseif(isset($sx->entry)) $nodes=$sx->entry;

    foreach($nodes as $it){
      $candidateTitle=tvs_clean_text((string)($it->title??''));
      $candidateUrl='';
      if(isset($it->link['href'])) $candidateUrl=(string)$it->link['href'];
      else $candidateUrl=(string)($it->link??'');
      $candidateUrl=tvs_radar_absolute_source_url($domain,$candidateUrl);
      if($candidateTitle==='' || $candidateUrl==='') continue;
      if(tvs_radar_source_host($candidateUrl)!==$host) continue;
      if(!tvs_radar_is_article_path($candidateUrl,$title,$city)) continue;
      $score=tvs_radar_title_match_score($title,$candidateTitle);
      if($score<45) continue;
      $published=(string)($it->pubDate??$it->published??$it->updated??'');
      $items[]=[
        'url'=>$candidateUrl,
        'title'=>$candidateTitle,
        'score'=>$score,
        'published_at'=>$published
      ];
    }
  }

  usort($items,static fn($a,$b)=>(int)($b['score']??0)<=>(int)($a['score']??0));
  return array_slice($items,0,8);
}

function tvs_radar_find_article_on_source($domain,$title,$city=''){
  static $cache=[];

  $domain=rtrim(trim((string)$domain),'/');
  $title=trim((string)$title);
  $city=trim((string)$city);

  // Contexto usado para rejeitar páginas genéricas como /sumare.
  $GLOBALS['tvs_radar_resolution_city']=$city;

  if(
    !preg_match('~^https?://~i',$domain) ||
    $title===''
  ){
    return '';
  }

  $cacheKey=md5($domain.'|'.$title.'|'.$city);

  if(isset($cache[$cacheKey])){
    return $cache[$cacheKey];
  }

  $queries=[
    $domain.'/?s='.rawurlencode($title),
    $domain.'/search?q='.rawurlencode($title)
  ];

  foreach(tvs_radar_source_feed_candidates($domain,$title,$city) as $feedCandidate){
    $candidateUrl=(string)($feedCandidate['url']??'');
    if($candidateUrl==='') continue;
    $validation=tvs_radar_validate_resolved_article(
      $candidateUrl,
      $title,
      $city,
      $domain,
      (string)($feedCandidate['published_at']??''),
      'source_feed_title_match'
    );
    if(!empty($validation['ok'])){
      return $cache[$cacheKey]=$candidateUrl;
    }
  }

  foreach(tvs_radar_source_section_urls($domain,$city) as $section){
    $queries[]=$section;
  }

  foreach(array_values(array_unique($queries)) as $candidatePage){
    $html=tvs_fetch_url($candidatePage);

    if($html==='') continue;

    $found=tvs_radar_find_article_in_html(
      $domain,
      $html,
      $title
    );

    if($found!==''){
      return $cache[$cacheKey]=$found;
    }
  }

  return $cache[$cacheKey]='';
}

function tvs_radar_resolution_cache_file(){
  return dirname(__DIR__).'/data/radar_resolution_cache.json';
}

function tvs_radar_resolution_cache_read(){
  $items=tvs_read_json_file(tvs_radar_resolution_cache_file());
  return is_array($items)?$items:[];
}

function tvs_radar_resolution_cache_save($items){
  if(!is_array($items)) $items=[];
  if(count($items)>600){
    uasort($items,function($a,$b){
      return strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??''));
    });
    $items=array_slice($items,0,600,true);
  }
  tvs_save_json_file(tvs_radar_resolution_cache_file(),$items);
}

function tvs_radar_resolution_cache_key($url){
  return hash('sha256',trim((string)$url));
}

function tvs_radar_resolution_cache_lookup($url){
  $cache=tvs_radar_resolution_cache_read();
  $key=tvs_radar_resolution_cache_key($url);
  $row=$cache[$key]??null;
  if(!is_array($row)) return null;

  $updated=strtotime((string)($row['updated_at']??''));
  if($updated && (time()-$updated)>7*86400) return null;

  return $row;
}

function tvs_radar_resolution_cache_record($url,$resolved='',$method='',$ok=false){
  if(!empty($GLOBALS['TVS_RADAR_DRY_RUN'])){
    return [
      'source_url'=>trim((string)$url),
      'resolved_url'=>$resolved,
      'method'=>$method,
      'failures'=>0,
      'ok'=>$ok?1:0,
      'dry_run'=>1
    ];
  }
  $cache=tvs_radar_resolution_cache_read();
  $key=tvs_radar_resolution_cache_key($url);
  $row=is_array($cache[$key]??null)?$cache[$key]:[];
  $row['source_url']=trim((string)$url);
  $row['updated_at']=date('c');
  if($ok && $resolved!==''){
    $row['resolved_url']=$resolved;
    $row['method']=$method;
    $row['failures']=0;
    $row['ok']=1;
  } else {
    $row['failures']=(int)($row['failures']??0)+1;
    $row['ok']=0;
  }
  $cache[$key]=$row;
  tvs_radar_resolution_cache_save($cache);
  return $row;
}

function tvs_radar_google_blob_url($url){
  $path=(string)(parse_url((string)$url,PHP_URL_PATH)??'');
  if(!preg_match('~/articles/([^/?]+)~',$path,$m)) return '';

  $blob=strtr($m[1],'-_','+/');
  $pad=strlen($blob)%4;
  if($pad) $blob.=str_repeat('=',4-$pad);
  $decoded=base64_decode($blob,true);
  if(!is_string($decoded) || $decoded==='') return '';

  if(preg_match('~https://[^\x00-\x20"\'<>]+~',$decoded,$u)){
    $candidate=rtrim((string)$u[0],').,;');
    if(tvs_radar_external_url_is_valid($candidate)) return $candidate;
  }
  return '';
}

function tvs_radar_resolve_google_news_url($url){
  static $cache=[];

  $url=trim((string)$url);
  if(!tvs_radar_is_google_news_url($url)) return $url;
  if(isset($cache[$url])) return $cache[$url];

  $persisted=tvs_radar_resolution_cache_lookup($url);
  if(is_array($persisted) && !empty($persisted['ok'])){
    $candidate=trim((string)($persisted['resolved_url']??''));
    if(tvs_radar_external_url_is_valid($candidate)){
      return $cache[$url]=$candidate;
    }
  }

  // Custo quase zero: tenta extrair URL embutida no blob /articles/... quando presente.
  $blobUrl=tvs_radar_google_blob_url($url);
  if($blobUrl!==''){
    tvs_radar_resolution_cache_record($url,$blobUrl,'google_blob',true);
    return $cache[$url]=$blobUrl;
  }

  $html='';
  $effective='';

  if(function_exists('curl_init')){
    $outboundOptions=tvs_outbound_curl_options($url,12);
    if($outboundOptions!==null){
      $ch=curl_init($url);
      curl_setopt_array($ch,$outboundOptions+[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_ENCODING=>'',
        CURLOPT_USERAGENT=>
          'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
          .'AppleWebKit/537.36 Chrome/126 Safari/537.36',
        CURLOPT_HTTPHEADER=>[
          'Accept: text/html,application/xhtml+xml',
          'Accept-Language: pt-BR,pt;q=0.9,en;q=0.7'
        ]
      ]);
      $html=(string)curl_exec($ch);
      $effective=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
      curl_close($ch);
      if(strlen($html)>2097152){ $html=''; $effective=''; }
    }
  }

  if(tvs_radar_external_url_is_valid($effective)){
    tvs_radar_resolution_cache_record($url,$effective,'http_effective_url',true);
    return $cache[$url]=$effective;
  }

  if($html!==''){
    // 1) Meta refresh.
    if(preg_match(
      '~<meta\b[^>]*http-equiv=["\']?refresh["\']?[^>]*content=["\'][^"\']*url=([^"\']+)["\']~iu',
      $html,$m
    )){
      $candidate=trim(html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));
      if(tvs_radar_external_url_is_valid($candidate)){
        tvs_radar_resolution_cache_record($url,$candidate,'meta_refresh',true);
        return $cache[$url]=$candidate;
      }
    }

    // 2) Open Graph URL.
    if(preg_match_all('~<meta\b[^>]*>~is',$html,$metaTags)){
      foreach($metaTags[0] as $tag){
        if(
          preg_match('~\bproperty=["\']og:url["\']~i',$tag) &&
          preg_match('~\bcontent=["\']([^"\']+)["\']~i',$tag,$m)
        ){
          $candidate=html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
          if(tvs_radar_external_url_is_valid($candidate)){
            tvs_radar_resolution_cache_record($url,$candidate,'og_url',true);
            return $cache[$url]=$candidate;
          }
        }
      }
    }

    // 3) Canonical.
    if(preg_match_all('~<link\b[^>]*>~is',$html,$tags)){
      foreach($tags[0] as $tag){
        if(
          preg_match('~\brel=["\'][^"\']*canonical[^"\']*["\']~i',$tag) &&
          preg_match('~\bhref=["\']([^"\']+)["\']~i',$tag,$m)
        ){
          $candidate=html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
          if(tvs_radar_external_url_is_valid($candidate)){
            tvs_radar_resolution_cache_record($url,$candidate,'canonical',true);
            return $cache[$url]=$candidate;
          }
        }
      }
    }

    // 4) Endereço externo com aparência de matéria.
    if(preg_match_all(
      '~https?://[^\s"\'<>\\\\]+~iu',
      html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8'),
      $links
    )){
      foreach(array_unique($links[0]) as $candidate){
        $candidate=rtrim($candidate,').,;');
        if(!tvs_radar_external_url_is_valid($candidate)) continue;

        $path=(string)(parse_url($candidate,PHP_URL_PATH)??'');
        if(preg_match(
          '~/(noticia|noticias|materia|cidade|politica|economia|'
          .'esporte|cultura|educacao|saude|emprego|concursos?|'
          .'campinas|sumare|hortolandia|paulinia|americana|nova-odessa)/~iu',
          $path
        )){
          tvs_radar_resolution_cache_record($url,$candidate,'external_article_link',true);
          return $cache[$url]=$candidate;
        }
      }
    }
  }

  tvs_radar_resolution_cache_record($url,'','',false);
  return $cache[$url]=$url;
}

function tvs_radar_known_current_url($title){
  $t=tvs_lower(tvs_clean_text((string)$title));
  $map=[
    'prefeitura de sumaré e detran promovem ação de conscientização na semana nacional de trânsito'=>'https://noticiasumare.com.br/prefeitura-de-sumare-e-detran-promovem-acao-de-conscientizacao-na-semana-nacional-de-transito/',
    'prefeitura de sumaré participa de treinamento da comgás para prevenção de danos à rede de gás'=>'https://portalon.com.br/sumare/prefeitura-de-sumare-participa-de-treinamento-da-comgas-para-prevencao-de-danos-a-rede-de-gas/',
    'encontro de emprego em hortolândia na segunda oferece 500 vagas para auxiliar logístico'=>'https://portalhortolandia.com.br/hortolandia/encontro-de-emprego-em-hortolandia-oferece-500-vagas-236631/',
    'prefeitura lança escola de formação de famílias de hortolândia'=>'https://portalhortolandia.com.br/hortolandia/prefeitura-lanca-escola-de-formacao-de-familias-236540/',
    'projeto de hortolândia para pcds leva estreantes para a corrida integração'=>'https://ge.globo.com/sp/campinas-e-regiao/corrida-integracao/noticia/2026/09/24/projeto-de-hortolandia-para-pcds-leva-estreantes-para-a-corrida-integracao.ghtml',
    'banda municipal faz apresentação neste domingo no parque das crianças, em nova odessa'=>'https://novomomento.com.br/banda-municipal-domingo-parque-criancas/',
    'prefeitura de americana oferece 34 vagas de estágio'=>'https://novomomento.com.br/prefeitura-americana-oferece-34-vagas-estagio/',
    'ciclista de americana vence campeonato sul-americano de bmx'=>'https://noticiafm.com/noticia/ciclista-de-americana-vence-campeonato-sul-americano-de-bmx',
    'educação de campinas leva 2 mil alunos à etecap de portas abertas para conhecer cursos técnicos'=>'https://campinas.sp.gov.br/noticias/educacao-de-campinas-leva-2-mil-alunos-a-etecap-de-portas-abertas-para-conhecer-cursos-tecnicos-149376',
    'campinas abre 197 vagas para aulas gratuitas de atividades esportivas e pilates nesta quarta, 23'=>'https://www.campinas.sp.gov.br/noticias/campinas-abre-197-vagas-para-aulas-gratuitas-de-atividades-esportivas-e-pilates-nesta-quarta-23-149219'
  ];
  foreach($map as $needle=>$url){
    if(strpos($t,tvs_lower($needle))!==false) return $url;
  }
  return '';
}

function tvs_radar_source_domain_hint($source,$title=''){
  $raw=tvs_lower(tvs_clean_text((string)$source.' '.(string)$title));
  // Quando o próprio nome da fonte/manchete já traz um domínio, usa-o
  // diretamente como pista. Isso evita depender de uma tabela manual infinita.
  if(preg_match('~\b((?:[a-z0-9-]+\.)+(?:com\.br|net\.br|org\.br|gov\.br|com|net|org))\b~iu',$raw,$dm)){
    $host=tvs_lower((string)$dm[1]);
    if($host!=='' && !str_contains($host,'google.') && !str_contains($host,'bing.')){
      return 'https://'.$host;
    }
  }

  $s=$raw;
  if(strpos($s,'hora campinas')!==false) return 'https://horacampinas.com.br';
  if(strpos($s,'portal hortolandia')!==false || strpos($s,'portal hortolândia')!==false) return 'https://portalhortolandia.com.br';

  // Portais oficiais das seis cidades monitoradas.
  if(strpos($s,'sumare.sp.gov.br')!==false || strpos($s,'sumaré.sp.gov.br')!==false || strpos($s,'prefeitura de sumaré')!==false || strpos($s,'prefeitura de sumare')!==false) return 'https://sumare.sp.gov.br';
  if(strpos($s,'hortolandia.sp.gov.br')!==false || strpos($s,'hortolândia.sp.gov.br')!==false || strpos($s,'prefeitura de hortolândia')!==false || strpos($s,'prefeitura de hortolandia')!==false) return 'https://www.hortolandia.sp.gov.br';
  if(strpos($s,'paulinia.sp.gov.br')!==false || strpos($s,'paulínia.sp.gov.br')!==false || strpos($s,'prefeitura de paulínia')!==false || strpos($s,'prefeitura de paulinia')!==false) return 'https://www.paulinia.sp.gov.br';
  if(strpos($s,'novaodessa.sp.gov.br')!==false || strpos($s,'prefeitura de nova odessa')!==false) return 'https://www.novaodessa.sp.gov.br';
  if(strpos($s,'americana.sp.gov.br')!==false || strpos($s,'prefeitura de americana')!==false) return 'https://www.americana.sp.gov.br';
  if(strpos($s,'prefeitura de campinas')!==false || strpos($s,'campinas.sp.gov.br')!==false) return 'https://www.campinas.sp.gov.br';

  // Veículos regionais recorrentes.
  if(strpos($s,'sb noticias')!==false || strpos($s,'sb notícias')!==false) return 'https://sbnoticias.com.br';
  if(strpos($s,'portal de sumare')!==false || strpos($s,'portal de sumaré')!==false) return 'https://portaldesumare.com.br';
  if(strpos($s,'notícias sumaré')!==false || strpos($s,'noticias sumare')!==false || strpos($s,'noticiasumare')!==false) return 'https://noticiasumare.com.br';
  if(strpos($s,'portal on')!==false || strpos($s,'portalon')!==false) return 'https://portalon.com.br';
  if(strpos($s,'notícia fm')!==false || strpos($s,'noticia fm')!==false || strpos($s,'noticiafm')!==false) return 'https://noticiafm.com';
  if(strpos($s,'novo momento')!==false || strpos($s,'novomomento')!==false) return 'https://novomomento.com.br';
  if(strpos($s,'o liberal')!==false || strpos($s,'liberal —')!==false || strpos($s,'liberal -')!==false || strpos($s,'liberal.com.br')!==false) return 'https://liberal.com.br';
  if(strpos($s,'portal da cidade sumaré')!==false || strpos($s,'portal da cidade sumare')!==false || strpos($s,'sumare.portaldacidade.com')!==false) return 'https://sumare.portaldacidade.com';
  if(strpos($s,'tribuna liberal')!==false || strpos($s,'tribunaliberal')!==false) return 'https://www.tribunaliberal.com.br';
  if(strpos(tvs_lower(tvs_clean_text((string)$source)),'liberal')!==false || strpos($s,'liberal.com.br')!==false) return 'https://liberal.com.br';
  if(strpos($s,'todo dia')!==false || strpos($s,'tododia')!==false) return 'https://tododia.com.br';
  if(strpos($s,'hortonews')!==false || strpos($s,'horto news')!==false) return 'https://hortonews.com.br';
  if(strpos($s,'portal porque')!==false || strpos($s,'portalporque')!==false || strpos($s,'jornalismo que faltava')!==false) return 'https://www.portalporque.com.br';
  if(preg_match('~\bge\b|globo esporte~u',$s)) return 'https://ge.globo.com';
  if(preg_match('~\bg1\b|eptv~u',$s)) return 'https://g1.globo.com';
  return '';
}

function tvs_radar_resolution_attempt_log($itemId,$method,$domain,$status,$confidence=0,$latencyMs=0,$httpStatus=0){
  $row=[
    'item_id'=>(string)$itemId,
    'method'=>(string)$method,
    'domain'=>(string)$domain,
    'status'=>(string)$status,
    'confidence'=>(int)$confidence,
    'latency_ms'=>(int)$latencyMs,
    'http_status'=>(int)$httpStatus,
    'created_at'=>date('c')
  ];
  @file_put_contents(
    dirname(__DIR__).'/data/resolution_attempts.jsonl',
    json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",
    FILE_APPEND|LOCK_EX
  );
}

function tvs_radar_resolution_next_attempt($attempts){
  $attempts=max(1,(int)$attempts);
  // Janela progressiva para notícia fresca: tenta rápido no início e desacelera
  // sem abandonar a fonte antes das 6 tentativas previstas pelo resolvedor.
  $schedule=[1=>1,2=>3,3=>6,4=>12,5=>24];
  $hours=$schedule[$attempts]??24;
  return date('c',time()+($hours*3600));
}

function tvs_radar_resolve_candidate_urls($items){
  foreach($items as &$item){
    $current=trim((string)($item['url']??''));
    if(!tvs_radar_is_google_news_url($current)) continue;

    $itemId=(string)($item['id']??md5($current.'|'.($item['title']??'')));
    $expectedTitle=(string)($item['title']??'');
    $city=(string)($item['city']??'');
    $expectedPublishedAt=(string)($item['published_at']??'');
    $sourceDomain=trim((string)($item['source_domain']??''));
    if($sourceDomain===''){
      $sourceDomain=tvs_radar_source_domain_hint($item['source']??'',$expectedTitle);
    }

    $resolved='';
    $method='';
    $validation=null;

    $tryCandidate=function($candidate,$candidateMethod) use (&$resolved,&$method,&$validation,$expectedTitle,$city,$sourceDomain,$expectedPublishedAt,$itemId){
      $candidate=trim((string)$candidate);
      if($candidate==='') return false;
      $started=microtime(true);
      $check=tvs_radar_validate_resolved_article(
        $candidate,
        $expectedTitle,
        $city,
        $sourceDomain,
        $expectedPublishedAt,
        $candidateMethod
      );
      $latency=(int)round((microtime(true)-$started)*1000);
      tvs_radar_resolution_attempt_log(
        $itemId,
        $candidateMethod,
        tvs_radar_source_host($candidate),
        !empty($check['ok'])?'resolved':'rejected',
        (int)($check['confidence']??0),
        $latency,
        0
      );
      if(empty($check['ok'])) return false;
      $resolved=$candidate;
      $method=$candidateMethod;
      $validation=$check;
      return true;
    };

    // 1) Domínio informado pelo próprio RSS é o caminho principal.
    if($sourceDomain!==''){
      $candidate=tvs_radar_find_article_on_source($sourceDomain,$expectedTitle,$city);
      $tryCandidate($candidate,'source_domain_title_match');
    }

    // 2) Sitemap/feed do veículo.
    if($resolved==='' && $sourceDomain!==''){
      $candidate=tvs_radar_resolve_by_sitemap($sourceDomain,$expectedTitle,$city);
      $tryCandidate($candidate,'source_sitemap_title_match');
    }

    // 3) Índice restrito ao domínio (News + Web).
    if($resolved==='' && $sourceDomain!==''){
      $candidate=tvs_radar_resolve_by_bing_site($sourceDomain,$expectedTitle,$city);
      $tryCandidate($candidate,'bing_site_title_match');
    }
    if($resolved==='' && $sourceDomain!==''){
      $candidate=tvs_radar_resolve_by_bing_web($expectedTitle,$city,$item['source']??'',$sourceDomain);
      $tryCandidate($candidate,'bing_web_site_title_match');
    }

    // 4) Mapeamentos conhecidos.
    if($resolved===''){
      $candidate=tvs_radar_known_current_url($expectedTitle);
      $tryCandidate($candidate,'known_current_title');
    }

    // 5) Resolver o link Google como fallback direto.
    if($resolved===''){
      $candidate=tvs_radar_resolve_google_news_url($current);
      if($candidate!=='' && $candidate!==$current){
        $tryCandidate($candidate,'google_news_resolution');
      }
    }

    // 6) Busca geral em News e Web. Web é importante para portais regionais
    // que não aparecem no índice Bing News, mas têm a matéria indexada.
    if($resolved===''){
      $candidate=tvs_radar_resolve_by_bing_news($expectedTitle,$city,$item['source']??'');
      $tryCandidate($candidate,'bing_news_title_match');
    }
    if($resolved===''){
      $candidate=tvs_radar_resolve_by_bing_web($expectedTitle,$city,$item['source']??'',$sourceDomain);
      $tryCandidate($candidate,'bing_web_title_match');
    }

    $attempts=(int)($item['source_resolution_attempts']??0)+1;
    $item['source_resolution_attempts']=$attempts;
    $firstAttempt=(string)($item['source_resolution_first_attempt_at']??'');
    if($firstAttempt===''){
      $firstAttempt=date('c');
      $item['source_resolution_first_attempt_at']=$firstAttempt;
    }

    if($resolved!=='' && is_array($validation) && !empty($validation['ok'])){
      $item['google_news_url']=$current;
      $item['url']=$resolved;
      $item['source_url']=$resolved;
      $item['url_resolved_at']=date('c');
      $item['url_resolution_required']=0;
      $item['url_resolution_method']=$method;
      $item['url_resolution_status']='resolved';
      $item['url_resolution_confidence']=(int)($validation['confidence']??0);
      $item['url_resolution_next_attempt_at']='';
      $item['resolved_domain']=$validation['resolved_domain']??tvs_radar_source_host($resolved);
      $item['resolved_published_at']=$validation['published_at']??'';
      $row=tvs_radar_resolution_cache_record($current,$resolved,$method,true);
      $item['resolution_failure_count']=(int)($row['failures']??0);
      continue;
    }

    $firstTs=strtotime($firstAttempt);
    $ageHours=$firstTs?(time()-$firstTs)/3600:0;
    $final=$ageHours>=72 || $attempts>=6;

    $item['url_resolution_required']=1;
    $item['url_resolution_status']=$final?'unresolved_final':'unresolved_retriable';
    $item['url_resolution_next_attempt_at']=$final?'':tvs_radar_resolution_next_attempt($attempts);
    $item['resolution_failure_count']=$attempts;
    if($attempts>=3){
      $item['resolution_priority_penalty']=min(6,$attempts-2);
    }
    tvs_radar_resolution_attempt_log(
      $itemId,
      'all_methods',
      tvs_radar_source_host($sourceDomain),
      $item['url_resolution_status'],
      0,
      0,
      0
    );
    tvs_radar_resolution_cache_record($current,'','',false);
  }

  unset($item);
  return $items;
}

function tvs_radar_process_due_source_resolution($limit=8){
  $limit=max(1,min(30,(int)$limit));
  $items=tvs_radar_discovery_read();
  if(!$items){
    return [
      'executed_at'=>date('c'),'mode'=>'source_resolution_queue',
      'due_before'=>0,'processed'=>0,'resolved'=>0,
      'retriable'=>0,'final'=>0,'remaining_due'=>0
    ];
  }

  $terminalReconciled=0;
  foreach($items as &$row){
    if(!is_array($row))continue;
    $normalized=tvs_radar_normalize_source_terminal_state($row);
    if($normalized!==$row){$row=$normalized;$terminalReconciled++;}
  }
  unset($row);
  $candidates=[];
  foreach($items as $idx=>$item){
    if(!is_array($item)) continue;
    if(in_array((string)($item['pipeline_stage']??''),['fonte_esgotada','revisao_manual_pipeline','expirada_sem_enriquecimento'],true))continue;
    $url=trim((string)($item['url']??$item['source_url']??''));
    if(!tvs_radar_is_google_news_url($url)) continue;

    $status=(string)($item['url_resolution_status']??'');
    $attempts=(int)($item['source_resolution_attempts']??0);
    if($status==='unresolved_final' || $attempts>=6) continue;

    $next=(string)($item['url_resolution_next_attempt_at']??'');
    $nextTs=$next!==''?strtotime($next):false;
    if($nextTs && $nextTs>time()) continue;

    $city=(string)($item['radar_requested_city']??$item['city']??'');
    $candidates[]=[
      'idx'=>$idx,
      'city'=>$city,
      'attempts'=>$attempts,
      'next_ts'=>$nextTs?:0,
      'updated'=>(string)($item['pipeline_updated_at']??$item['source_resolution_first_attempt_at']??'')
    ];
  }

  usort($candidates,function($a,$b){
    if($a['attempts']!==$b['attempts']) return $a['attempts']<=>$b['attempts'];
    if($a['next_ts']!==$b['next_ts']) return $a['next_ts']<=>$b['next_ts'];
    return strcmp($a['updated'],$b['updated']);
  });

  $dueBefore=count($candidates);
  $selected=array_slice($candidates,0,$limit);
  $processed=0; $resolved=0; $retriable=0; $final=0;
  $byCity=[]; $byMethod=[];

  foreach($selected as $meta){
    $idx=(int)$meta['idx'];
    if(!isset($items[$idx]) || !is_array($items[$idx])) continue;

    $beforeUrl=(string)($items[$idx]['url']??$items[$idx]['source_url']??'');
    $batch=tvs_radar_resolve_candidate_urls([$items[$idx]]);
    if(empty($batch[0]) || !is_array($batch[0])) continue;

    $item=$batch[0];
    $processed++;
    $city=(string)($item['radar_requested_city']??$item['city']??'Região');
    $byCity[$city]=($byCity[$city]??0)+1;

    if(!tvs_radar_is_google_news_url($item['url']??$item['source_url']??'')){
      $resolved++;
      $item['pipeline_stage']='fonte_resolvida';
      $item['pipeline_reason']='Fonte original resolvida automaticamente; pauta liberada para enriquecimento factual.';
      $item['pipeline_updated_at']=date('c');
      $method=(string)($item['url_resolution_method']??'unknown');
      $byMethod[$method]=($byMethod[$method]??0)+1;
    } else {
      $resolutionStatus=(string)($item['url_resolution_status']??'unresolved_retriable');
      $item['pipeline_stage']='aguardando_fonte';
      if($resolutionStatus==='unresolved_final'){
        $final++;
        $item['pipeline_reason']='Fonte original não resolvida após o limite de tentativas/TTL; estado final preservado para auditoria.';
      } else {
        $retriable++;
        $item['pipeline_reason']='Fonte original ainda não resolvida; nova tentativa automática agendada.';
      }
      $item['pipeline_updated_at']=date('c');
      $item=tvs_radar_normalize_source_terminal_state($item);
    }

    $items[$idx]=$item;
  }

  tvs_radar_discovery_save($items);

  $remainingDue=0;
  foreach(tvs_radar_discovery_read() as $item){
    if(!is_array($item)) continue;
    $url=trim((string)($item['url']??$item['source_url']??''));
    if(!tvs_radar_is_google_news_url($url)) continue;
    $status=(string)($item['url_resolution_status']??'');
    $attempts=(int)($item['source_resolution_attempts']??0);
    if($status==='unresolved_final' || $attempts>=6) continue;
    $next=(string)($item['url_resolution_next_attempt_at']??'');
    $nextTs=$next!==''?strtotime($next):false;
    if(!$nextTs || $nextTs<=time()) $remainingDue++;
  }

  $report=[
    'executed_at'=>date('c'),
    'mode'=>'source_resolution_queue',
    'terminal_reconciled'=>$terminalReconciled,
    'due_before'=>$dueBefore,
    'processed'=>$processed,
    'resolved'=>$resolved,
    'retriable'=>$retriable,
    'final'=>$final,
    'remaining_due'=>$remainingDue,
    'by_city'=>$byCity,
    'by_method'=>$byMethod
  ];
  tvs_save_json_file(dirname(__DIR__).'/data/source_resolution_queue_status.json',$report);
  return $report;
}

function tvs_radar_resolve_google_backlog_offline($limit=60){
  $items=tvs_radar_discovery_read();
  $resolved=0; $unresolved=0; $processed=0;
  $byMethod=[]; $byDomain=[]; $unresolvedByDomain=[]; $missingSourceDomain=0;
  $confidenceBands=['90_100'=>0,'80_89'=>0,'70_79'=>0,'lt_70'=>0];

  foreach($items as $idx=>&$item){
    if($processed>=$limit) break;
    if(!is_array($item) || !tvs_radar_is_google_news_url($item['url']??'')) continue;

    $beforeUrl=(string)($item['url']??'');
    $batch=tvs_radar_resolve_candidate_urls([$item]);
    if(empty($batch[0]) || !is_array($batch[0])) continue;
    $item=$batch[0];
    $processed++;

    if(!tvs_radar_is_google_news_url($item['url']??'')){
      $resolved++;
      $method=(string)($item['url_resolution_method']??'unknown');
      $domain=(string)($item['resolved_domain']??tvs_radar_source_host($item['url']??''));
      $confidence=(int)($item['url_resolution_confidence']??0);
      $byMethod[$method]=($byMethod[$method]??0)+1;
      if($domain!=='') $byDomain[$domain]=($byDomain[$domain]??0)+1;
      if($confidence>=90) $confidenceBands['90_100']++;
      elseif($confidence>=80) $confidenceBands['80_89']++;
      elseif($confidence>=70) $confidenceBands['70_79']++;
      else $confidenceBands['lt_70']++;
    } else {
      $unresolved++;
      $hint=trim((string)($item['source_domain']??''));
      if($hint==='') $hint=tvs_radar_source_domain_hint($item['source']??'',$item['title']??'');
      $host=tvs_radar_source_host($hint);
      if($host===''){
        $missingSourceDomain++;
        $host='sem_dominio_origem';
      }
      $unresolvedByDomain[$host]=($unresolvedByDomain[$host]??0)+1;
    }
  }
  unset($item);

  tvs_radar_discovery_save($items);
  $report=[
    'executed_at'=>date('c'),
    'mode'=>'resolver_only',
    'processed'=>$processed,
    'resolved'=>$resolved,
    'unresolved'=>$unresolved,
    'resolution_rate'=>$processed>0?round(($resolved/$processed)*100,2):0,
    'by_method'=>$byMethod,
    'by_domain'=>$byDomain,
    'unresolved_by_domain'=>$unresolvedByDomain,
    'missing_source_domain'=>$missingSourceDomain,
    'confidence_bands'=>$confidenceBands
  ];
  tvs_save_json_file(dirname(__DIR__).'/data/source_resolution_offline_report.json',$report);
  return $report;
}

function tvs_radar_google_news($city,$limit=36){
  $themes=[
    'cultura eventos agenda show teatro música festival',
    'esporte campeonato jogos corrida',
    'educação escola creche curso alunos',
    'saúde hospital UBS vacinação atendimento',
    'obras trânsito transporte serviços públicos',
    'economia comércio empreendedorismo empresas',
    'meio ambiente turismo lazer',
    'prefeitura notícias cidade'
  ];

  $items=[];
  $seen=[];
  $perTheme=max(3,min(8,(int)ceil($limit/count($themes))));

  // Para Sumaré, prioriza também o portal oficial.
  if(tvs_lower($city)==='sumaré'){
    $officialThemes=[
      'cultura evento programação show festival',
      'esporte campeonato jogos',
      'educação escola curso',
      'saúde vacinação UBS',
      'obras serviços públicos turismo lazer'
    ];

    foreach($officialThemes as $theme){
      $q='site:sumare.sp.gov.br/cidadao/noticia "Sumaré" ('.$theme.') when:15d';
      $url='https://news.google.com/rss/search?q='
        .urlencode($q)
        .'&hl=pt-BR&gl=BR&ceid=BR:pt-419';

      foreach(tvs_reporter_fetch_feed_compat($url,5) as $it){
        $key=trim((string)($it['url']??''));

        if($key==='' || isset($seen[$key])) continue;

        $seen[$key]=1;
        $it['city']=$city;
        $it['source']=$it['source'] ?: 'Prefeitura de Sumaré';
        $it['source_type']='Fonte oficial';
        $it['radar_search_theme']='oficial: '.$theme;
        $it['priority']=1;
        $items[]=$it;
      }
    }
  }

  foreach($themes as $theme){
    $q='"'.$city.'" ('.$theme.')';
    $url='https://news.google.com/rss/search?q='
      .urlencode($q)
      .'&hl=pt-BR&gl=BR&ceid=BR:pt-419';

    foreach(tvs_reporter_fetch_feed_compat($url,$perTheme) as $it){
      $key=trim((string)($it['url']??''));

      if($key===''){
        $key=md5(tvs_lower((string)($it['title']??'')));
      }

      if(isset($seen[$key])) continue;
      $seen[$key]=1;

      $it['city']=$city;
      $it['source']=$it['source'] ?: 'Google Notícias';
      $it['source_type']='Google Notícias';
      $it['radar_search_theme']=$theme;
      $items[]=$it;

      if(count($items)>=$limit) break 2;
    }
  }

  return $items;
}
function tvs_reporter_fetch_feed_compat($url,$limit=6){
  $xml=tvs_fetch_url($url); if(!$xml) return [];
  $items=[];
  if(function_exists('simplexml_load_string')){
    libxml_use_internal_errors(true);
    $sx=@simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA);
    if($sx){
      $nodes=[]; if(isset($sx->channel->item)) $nodes=$sx->channel->item; elseif(isset($sx->entry)) $nodes=$sx->entry;
      foreach($nodes as $it){
        $title=tvs_clean_text((string)($it->title??''));
        $link=''; if(isset($it->link['href'])) $link=(string)$it->link['href']; else $link=(string)($it->link??'');
        $rawDesc=(string)($it->description??$it->summary??'');
        $desc=tvs_clean_text($rawDesc);
        $image='';

        $media=$it->children('media', true);
        if($media){
          if(isset($media->content)){
            foreach($media->content as $mediaContent){
              $attrs=$mediaContent->attributes();
              $candidate=trim((string)($attrs['url']??''));
              $type=tvs_lower((string)($attrs['type']??''));
              if($candidate!=='' && ($type==='' || strpos($type,'image/')===0) && tvs_is_valid_image_url($candidate)){
                $image=$candidate;
                break;
              }
            }
          }
          if($image==='' && isset($media->thumbnail)){
            $attrs=$media->thumbnail->attributes();
            $candidate=trim((string)($attrs['url']??''));
            if(tvs_is_valid_image_url($candidate)) $image=$candidate;
          }
        }

        if($image==='' && isset($it->enclosure)){
          $attrs=$it->enclosure->attributes();
          $candidate=trim((string)($attrs['url']??''));
          $type=tvs_lower((string)($attrs['type']??''));
          if($candidate!=='' && strpos($type,'image/')===0 && tvs_is_valid_image_url($candidate)) $image=$candidate;
        }

        if($image==='') $image=tvs_extract_image_from_rss_description($link,$rawDesc);

        $sourceName=trim((string)($it->source??''));
        $sourceDomain='';

        if(isset($it->source)){
          $sourceAttrs=$it->source->attributes();

          if(isset($sourceAttrs['url'])){
            $sourceDomain=trim((string)$sourceAttrs['url']);
          }
        }

        if($title && $link && !tvs_is_boilerplate($title)){
          $items[]=[
            'title'=>tvs_radar_clean_google_title($title),
            'url'=>$link,
            'description'=>$desc,
            'source'=>$sourceName!==''
              ? $sourceName
              : tvs_radar_source_from_title($title,'Google Notícias'),
            'source_domain'=>$sourceDomain,
            'source_type'=>'Google Notícias',
            'published_at'=>(string)($it->pubDate??$it->published??''),
            'image'=>$image,
            'image_source_type'=>$image!==''?'rss:source':''
          ];
        }
        if(count($items)>=$limit) break;
      }
    }
  }
  if(!$items && preg_match_all('~<item\b[^>]*>(.*?)</item>~is',$xml,$m)){
    foreach($m[1] as $block){
      preg_match('~<title[^>]*>(.*?)</title>~is',$block,$tm); preg_match('~<link[^>]*>(.*?)</link>~is',$block,$lm); preg_match('~<description[^>]*>(.*?)</description>~is',$block,$dm);
      $title=tvs_clean_text($tm[1]??''); $link=tvs_clean_text($lm[1]??''); $desc=tvs_clean_text($dm[1]??'');
      if($title && $link && !tvs_is_boilerplate($title)) $items[]=['title'=>tvs_radar_clean_google_title($title),'url'=>$link,'description'=>$desc,'source'=>tvs_radar_source_from_title($title,'Google Notícias'),'source_type'=>'Google Notícias'];
      if(count($items)>=$limit) break;
    }
  }
  return $items;
}



function tvs_radar_topic_cluster($item){
  $text=tvs_lower(
    trim(
      (string)($item['title']??'').' '.
      (string)($item['description']??'')
    )
  );

  if(
    preg_match('~\b(2[\.,]?9(?:02)?\s*mil|2\.902|2902)\b~u',$text) &&
    preg_match('~\b(empregos?|vagas?|gera[cç][aã]o)\b~iu',$text)
  ){
    return 'empregos-2902-primeiro-semestre';
  }

  if(
    preg_match('~\b(parceria com empresas|oportunidades? de emprego|pat)\b~iu',$text)
  ){
    return 'parceria-empresas-pat';
  }

  if(
    preg_match('~\b7[\.,]?3\s*mil\b~u',$text) &&
    preg_match('~\b(empreendedorismo|emprego|atendimentos?)\b~iu',$text)
  ){
    return 'atendimentos-empreendedorismo-7300';
  }

  if(
    preg_match('~\b158 anos\b~iu',$text) &&
    preg_match('~\b(cultura|evento|show|programa[cç][aã]o|anivers[aá]rio)\b~iu',$text)
  ){
    return 'aniversario-158-cultura';
  }

  if(
    preg_match('~\b158 anos\b~iu',$text) &&
    preg_match('~\b(economia|investimentos?|crescimento|desenvolvimento|novo ciclo)\b~iu',$text)
  ){
    return 'aniversario-158-economia';
  }

  return '';
}

function tvs_radar_title_fingerprint($title){
  $title=tvs_lower(tvs_clean_text((string)$title));

  $title=preg_replace(
    '~\b(prefeitura|município|municipio|cidade|sumaré|sumare|'
    .'portal|notícias|noticias|jornal|região|regiao|rmc|'
    .'fortalece|amplia|registra|finaliza|gera|gerou|'
    .'no|na|nos|nas|do|da|dos|das|de|e|em|com|por|para|'
    .'primeiro|semestre|meio)\b~iu',
    ' ',
    $title
  );

  // Normaliza valores equivalentes como 2.902 e 2,9 mil.
  $title=preg_replace('~\b2[\.,]9(?:02)?\s*mil?\b~iu','2900',$title);
  $title=preg_replace('~\b2\.902\b~u','2900',$title);

  $title=preg_replace('~[^\p{L}\p{N}]+~u',' ',$title);
  $title=preg_replace('~\s+~u',' ',trim($title));

  $words=array_values(array_filter(
    explode(' ',$title),
    static fn($word)=>tvs_strlen($word)>=4
  ));

  sort($words,SORT_STRING);

  return implode('|',array_unique($words));
}

function tvs_radar_titles_are_similar($a,$b){
  $fa=tvs_radar_title_fingerprint($a);
  $fb=tvs_radar_title_fingerprint($b);

  if($fa==='' || $fb==='') return false;
  if($fa===$fb) return true;

  $wa=array_values(array_filter(explode('|',$fa)));
  $wb=array_values(array_filter(explode('|',$fb)));

  if(!$wa || !$wb) return false;

  $intersection=count(array_intersect($wa,$wb));
  $minimum=min(count($wa),count($wb));

  return $minimum>=2 && ($intersection/$minimum)>=0.70;
}

function tvs_radar_history_duplicate($candidate,$history){
  $candCity=(string)($candidate['city']??$candidate['radar_requested_city']??'');
  $candUrl=trim((string)($candidate['url']??$candidate['source_url']??''));
  $candTitle=(string)($candidate['title']??'');
  $candCluster=tvs_radar_topic_cluster($candidate);

  foreach((array)$history as $existing){
    $existingCity=(string)($existing['city']??'');
    if($candCity!=='' && $existingCity!=='' && tvs_lower($candCity)!==tvs_lower($existingCity)) continue;

    $existingUrl=trim((string)($existing['url']??$existing['source_url']??''));
    if($candUrl!=='' && $existingUrl!=='' && $candUrl===$existingUrl) return true;

    $existingCluster=tvs_radar_topic_cluster($existing);
    if($candCluster!=='' && $existingCluster!=='' && $candCluster===$existingCluster) return true;

    if(tvs_radar_titles_are_similar($candTitle,(string)($existing['title']??''))) return true;
  }
  return false;
}

function tvs_radar_deduplicate_topics($items){
  $result=[];
  $clusters=[];

  foreach($items as $item){
    $title=(string)($item['title']??'');
    $cluster=tvs_radar_topic_cluster($item);

    if($cluster!=='' && isset($clusters[$cluster])){
      continue;
    }

    $duplicate=false;

    foreach($result as $existing){
      if(tvs_radar_titles_are_similar(
        $title,
        (string)($existing['title']??'')
      )){
        $duplicate=true;
        break;
      }
    }

    if($duplicate) continue;

    if($cluster!==''){
      $clusters[$cluster]=1;
    }

    $result[]=$item;
  }

  return $result;
}

function tvs_radar_candidate_category($item){
  $text=trim(
    (string)($item['title']??'').' '.
    (string)($item['description']??'')
  );

  // Contextos compostos têm prioridade sobre palavras isoladas.
  if(preg_match(
    '~\b(empreendedorismo|emprego|empregos|vagas?|pat|'
    .'mercado de trabalho|gera[cç][aã]o de empregos?|'
    .'oportunidades? de trabalho)\b~iu',
    $text
  )){
    return 'Empregos';
  }

  if(preg_match(
    '~\b(economia|econ[oô]mico|com[eé]rcio|ind[uú]stria|'
    .'empresas?|investimento|desenvolvimento econ[oô]mico|'
    .'crescimento econ[oô]mico)\b~iu',
    $text
  )){
    return 'Economia';
  }

  // Esporte precisa ser classificado antes de Cultura,
  // pois campeonatos e jogos também podem conter a palavra evento.
  if(preg_match(
    '~\b(esporte|esportes|campeonato|campeonatos|amador|'
    .'oitavas|quartas|semifinal|final|jogo|jogos|partida|'
    .'torneio|atleta|corrida|futebol|v[oô]lei|basquete)\b~iu',
    $text
  )){
    return 'Esportes';
  }

  if(preg_match(
    '~\b(evento|eventos|agenda|festival|show|shows|feira|'
    .'teatro|cinema|exposi[cç][aã]o|m[uú]sica|turismo|'
    .'lazer|programa[cç][aã]o|rota cervejeira|desfile)\b~iu',
    $text
  )){
    return 'Cultura';
  }

  if(preg_match(
    '~\b(sa[uú]de|hospital|ubs|upa|vacina[cç][aã]o|'
    .'dengue|consulta|m[eé]dico|paciente)\b~iu',
    $text
  )){
    return 'Saúde';
  }

  if(preg_match(
    '~\b(pra[cç]a|revitaliza[cç][aã]o|obra|obras|ponte|pontes|viaduto|viadutos|'
    .'mobilidade urbana|ciclovia|ordem de servi[cç]o|pavimenta[cç][aã]o|recape|ilumina[cç][aã]o|'
    .'servi[cç]os? p[uú]blicos?|cad[uú]nico|atendimento itinerante)\b~iu',
    $text
  )){
    return 'Cidade';
  }

  return tvs_category_from_text($text) ?: 'Cidade';
}

function tvs_radar_diversify_candidates($items,$limit=72){
  $limit=max(1,(int)$limit);

  $seed=[
    'Cultura'=>5,
    'Cidade'=>4,
    'Saúde'=>3,
    'Educação'=>3,
    'Esportes'=>3,
    'Economia'=>2,
    'Empregos'=>2,
    'Política'=>2,
    'Segurança'=>2,
    'Brasil'=>1
  ];

  $caps=[
    'Cultura'=>18,
    'Cidade'=>18,
    'Saúde'=>12,
    'Educação'=>12,
    'Esportes'=>12,
    'Economia'=>10,
    'Empregos'=>2,
    'Política'=>8,
    'Segurança'=>7,
    'Brasil'=>6
  ];

  $buckets=[];

  foreach($items as $item){
    $category=tvs_radar_candidate_category($item);
    $item['radar_pre_category']=$category;
    $buckets[$category][]=$item;
  }

  $selected=[];
  $selectedKeys=[];
  $counts=[];

  $add=function($item,$category) use (&$selected,&$selectedKeys,&$counts,$limit,$caps){
    if(count($selected)>=$limit) return false;

    $key=trim((string)($item['url']??''));

    if($key===''){
      $key=md5(tvs_lower((string)($item['title']??'')));
    }

    if(isset($selectedKeys[$key])) return false;

    $cap=$caps[$category]??10;

    if(($counts[$category]??0)>=$cap) return false;

    $selectedKeys[$key]=1;
    $counts[$category]=($counts[$category]??0)+1;
    $selected[]=$item;

    return true;
  };

  // Reserva inicial para garantir variedade nas primeiras pautas processadas.
  foreach($seed as $category=>$minimum){
    $bucket=$buckets[$category]??[];

    for($i=0;$i<$minimum && isset($bucket[$i]);$i++){
      $add($bucket[$i],$category);
    }
  }

  // Segunda etapa: rodízio entre categorias.
  $positions=[];

  foreach(array_keys($buckets) as $category){
    $positions[$category]=$seed[$category]??0;
  }

  $progress=true;

  while(count($selected)<$limit && $progress){
    $progress=false;

    foreach(array_keys($seed) as $category){
      $pos=$positions[$category]??0;
      $bucket=$buckets[$category]??[];

      if(isset($bucket[$pos])){
        $positions[$category]=$pos+1;

        if($add($bucket[$pos],$category)){
          $progress=true;
        }
      }
    }
  }

  // Completa com os melhores candidatos restantes, respeitando os limites.
  foreach($items as $item){
    if(count($selected)>=$limit) break;

    $category=$item['radar_pre_category']
      ??tvs_radar_candidate_category($item);

    $add($item,$category);
  }

  return $selected;
}


function tvs_radar_obvious_false_positive($item,$city,&$reason=''){
  $title=trim((string)($item['title']??''));
  $desc=trim((string)($item['description']??''));
  $url=trim((string)($item['url']??''));
  $all=$title.' '.$desc.' '.$url;

  // Publicidade, espaços de festas e conteúdo comercial disfarçado de pauta.
  if(preg_match(
    '~\b(buffet|sal[aã]o de festas|espa[cç]o para eventos|eventos privados|'
    .'conforto e eleg[aâ]ncia|fa[cç]a sua reserva|reserve agora|or[cç]amento|'
    .'loca[cç][aã]o para festas|delivery|promo[cç][aã]o|desconto|card[aá]pio)\b~iu',
    $all
  )){
    $reason='Conteúdo comercial ou publicitário';
    return true;
  }

  // "Sumaré" pode ser nome de bairro em cidades de outros estados.
  if(
    tvs_lower($city)==='sumaré' &&
    preg_match('~\b(mossor[oó]|rio grande do norte|\brn\b|bairro sumar[eé])\b~iu',$all)
  ){
    $reason='Falso positivo geográfico: Sumaré aparece como bairro fora da região';
    return true;
  }

  // Páginas de editoria/listagem não são pautas, mesmo quando carregam vários
  // parágrafos e acabam parecendo uma matéria para o extrator.
  if(
    preg_match('~^(Economia|Cidade|Cidades|Sa[uú]de|Educa[cç][aã]o|Cultura|Esportes?|Seguran[cç]a|Pol[ií]tica|Empregos?)\s*[-–—:]\s*Not[ií]cias sobre\b~iu',$title)
    || preg_match('~/noticias/(?:economia|cidade|cidades|saude|educacao|cultura|esportes?|seguranca|politica|empregos?)/?$~iu',$url)
  ){
    $reason='Página de editoria/listagem, não matéria jornalística';
    return true;
  }

  // Vagas privadas de cursos, projetos ou instituições sem caráter jornalístico.
  if(preg_match(
    '~\b(abre vagas para crianças|abre vagas para adolescentes|'
    .'matrículas abertas na instituição|inscreva seu filho|projeto social abre vagas)\b~iu',
    $all
  )){
    $reason='Divulgação institucional privada sem fato jornalístico suficiente';
    return true;
  }

  return false;
}


function tvs_radar_editorial_caps(){
  return [
    'Cidade'=>5,
    'Cultura'=>2,
    'Esportes'=>2,
    'Empregos'=>2,
    'Educação'=>2,
    'Saúde'=>2,
    'Segurança'=>2,
    'Economia'=>2,
    'Política'=>1
  ];
}

function tvs_radar_apply_city_quotas($items,$city=''){
  $caps=tvs_radar_editorial_caps();
  $counts=[];
  $selected=[];

  foreach($items as $item){
    $category=trim((string)(
      $item['radar_pre_category']
      ??$item['category']
      ??''
    ));

    if($category==='' || !isset($caps[$category])){
      $category=tvs_radar_candidate_category($item);
    }

    if(!isset($caps[$category])){
      $category='Cidade';
    }

    $current=$counts[$category]??0;

    if($current >= $caps[$category]){
      continue;
    }

    $item['radar_pre_category']=$category;
    $item['category']=$category;
    $item['radar_requested_city']=$city;

    $counts[$category]=$current+1;
    $selected[]=$item;
  }

  return $selected;
}

function tvs_radar_global_caps(){
  return [
    'Brasil'=>3,
    'São Paulo'=>3,
    'RMC'=>3
  ];
}

function tvs_radar_extract_published_at_from_html($html){
  $html=(string)$html;
  if($html==='') return '';
  $patterns=[
    '~<meta\b[^>]*(?:property|name)=["\']article:published_time["\'][^>]*content=["\']([^"\']+)["\']~i',
    '~<meta\b[^>]*content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']article:published_time["\']~i',
    '~"datePublished"\s*:\s*"([^"]+)"~i',
    '~<time\b[^>]*datetime=["\']([^"\']+)["\']~i'
  ];
  foreach($patterns as $rx){
    if(preg_match($rx,$html,$m)){
      $raw=html_entity_decode(trim((string)$m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8');
      if($raw!=='' && strtotime($raw)!==false) return $raw;
    }
  }
  return '';
}

function tvs_radar_liberal_city($city,$limit=8){
  $slugs=[
    'Sumaré'=>'sumare',
    'Hortolândia'=>'hortolandia',
    'Nova Odessa'=>'nova-odessa',
    'Americana'=>'americana',
    'Campinas'=>'campinas'
  ];
  if(!isset($slugs[$city])) return [];

  $base='https://liberal.com.br';
  $page=$base.'/cidades/'.$slugs[$city];
  $html=tvs_fetch_url($page);
  // Algumas editorias do Liberal podem responder 403/HTML reduzido enquanto
  // a home continua disponível e traz blocos "Explore por cidade".
  if($html==='') $html=tvs_fetch_url($base.'/');
  $out=[]; $seen=[];
  $diag=['page_bytes'=>strlen((string)$html),'sitemap_bytes'=>0,'post_sitemaps'=>0,'sitemap_checked'=>0,'google_seen'=>0];

  // No Liberal, o título <h2> pode ficar fora do <a>. Associa cada heading ao
  // último link interno imediatamente anterior no card, em vez de exigir
  // texto dentro da âncora.
  if($html!=='' && preg_match_all('~<h[2-4]\\b[^>]*>(.*?)</h[2-4]>~is',$html,$hm,PREG_OFFSET_CAPTURE)){
    foreach($hm[1] as $idx=>$capture){
      $title=tvs_clean_text((string)($capture[0]??''));
      $headingOffset=(int)($hm[0][$idx][1]??0);
      if(tvs_strlen($title)<25 || tvs_strlen($title)>220) continue;
      if(tvs_is_boilerplate($title)) continue;

      $start=max(0,$headingOffset-3500);
      $window=substr($html,$start,7000);
      if(!preg_match_all("~<a\\b[^>]*href=[\"']([^\"']+)[\"'][^>]*>~is",$window,$am)) continue;

      $url='';
      $bestScore=0;
      foreach($am[1] as $rawHref){
        $candidate=tvs_radar_absolute_source_url($base,$rawHref??'');
        if($candidate==='' || tvs_radar_source_host($candidate)!=='liberal.com.br') continue;
        if(isset($seen[$candidate])) continue;

        $path=(string)(parse_url($candidate,PHP_URL_PATH)??'');
        $last=(string)basename(trim($path,'/'));
        if($last==='') continue;

        $slugText=str_replace(['-','_'],' ',$last);
        $score=tvs_radar_title_match_score($title,$slugText);
        if($score<$bestScore) continue;
        if(!tvs_radar_is_article_path($candidate,$title,$city)) continue;

        $bestScore=$score;
        $url=$candidate;
      }
      if($url==='' || $bestScore<42){
        $url=tvs_radar_find_article_on_source($base,$title,$city);
        if($url==='') $url=tvs_radar_resolve_by_bing_site($base,$title,$city);
      }
      if($url==='') continue;

      $articleHtml=tvs_fetch_url($url);
      if($articleHtml==='') continue;
      $article=tvs_extract_article($url,$title);
      $desc=trim((string)($article['description']??''));
      $body=trim((string)($article['body']??''));
      if(tvs_strlen($desc.' '.$body)<80) continue;

      $published=tvs_radar_extract_published_at_from_html($articleHtml);
      if($published==='') continue;

      $factText=trim((string)($article['title']??$title).' '.$desc.' '.$body);
      $path=tvs_lower((string)(parse_url($url,PHP_URL_PATH)??''));
      $cityPath='/cidades/'.$slugs[$city].'/';
      $cityConfirmed=tvs_radar_text_mentions_city($factText,$city) || strpos($path,$cityPath)!==false;
      if(!$cityConfirmed) continue;

      $candidate=[
        'title'=>trim((string)($article['title']??$title)) ?: $title,
        'url'=>$url,
        'description'=>$desc!==''?$desc:tvs_substr(tvs_clean_text($body),0,420),
        'published_at'=>$published,
        'source'=>'Liberal',
        'source_type'=>'Portal Regional',
        'city'=>$city,
        'image'=>$article['image']??'',
        'source_city_confirmed'=>1,
        'priority'=>2,
        'source_domain'=>'https://liberal.com.br'
      ];

      $reason='';
      if(!tvs_radar_candidate_region_ok($candidate,$city,$reason)) continue;

      $seen[$url]=1;
      $out[]=$candidate;
      if(count($out)>=$limit) break;
    }
  }

  // Fallback direto pelo sitemap oficial do Liberal. É independente do HTML
  // das editorias/home e continua funcionando quando essas páginas respondem 403
  // ou mudam a marcação visual.
  if(!$out){
    $sitemapIndex=tvs_fetch_url($base.'/wp-sitemap.xml');
    $diag['sitemap_bytes']=strlen((string)$sitemapIndex);
    $postSitemaps=[];
    if($sitemapIndex!=='' && preg_match_all('~<loc>\\s*(.*?)\\s*</loc>~is',$sitemapIndex,$sm)){
      foreach($sm[1] as $rawLoc){
        $loc=html_entity_decode(trim(strip_tags((string)$rawLoc)),ENT_QUOTES|ENT_HTML5,'UTF-8');
        if($loc!=='' && tvs_radar_source_host($loc)==='liberal.com.br' && preg_match('~wp-sitemap-posts-post-[0-9]+\\.xml(?:\\?|$)~i',$loc)){
          $postSitemaps[]=$loc;
        }
      }
    }

    if(!$postSitemaps){
      $postSitemaps[]=$base.'/wp-sitemap-posts-post-1.xml';
    }
    $diag['post_sitemaps']=count($postSitemaps);

    natsort($postSitemaps);
    $postSitemaps=array_reverse(array_values($postSitemaps));
    $checked=0;

    foreach(array_slice($postSitemaps,0,2) as $postSitemap){
      if($checked>=30 || count($out)>=$limit) break;
      $xml=tvs_fetch_url($postSitemap);
      if($xml==='' || !preg_match_all('~<loc>\\s*(.*?)\\s*</loc>~is',$xml,$lm)) continue;

      $articleUrls=array_reverse(array_values($lm[1]));
      foreach($articleUrls as $rawLoc){
        if($checked>=30 || count($out)>=$limit) break 2;

        $url=html_entity_decode(trim(strip_tags((string)$rawLoc)),ENT_QUOTES|ENT_HTML5,'UTF-8');
        if($url==='' || isset($seen[$url]) || tvs_radar_source_host($url)!=='liberal.com.br') continue;

        $path=(string)(parse_url($url,PHP_URL_PATH)??'');
        $last=(string)basename(trim($path,'/'));
        if($last==='' || count(array_filter(preg_split('~[-_]+~',$last)))<4) continue;

        $slugTitle=str_replace(['-','_'],' ',$last);
        $checked++;
        $diag['sitemap_checked']=$checked;

        $articleHtml=tvs_fetch_url($url);
        if($articleHtml==='') continue;
        $article=tvs_extract_article($url,$slugTitle);
        $title=trim((string)($article['title']??''));
        $desc=trim((string)($article['description']??''));
        $body=trim((string)($article['body']??''));
        if($title==='' || tvs_strlen($desc.' '.$body)<80) continue;

        $published=tvs_radar_extract_published_at_from_html($articleHtml);
        if($published==='') continue;

        $candidate=[
          'title'=>$title,
          'url'=>$url,
          'description'=>$desc!==''?$desc:tvs_substr(tvs_clean_text($body),0,420),
          'published_at'=>$published,
          'source'=>'Liberal',
          'source_type'=>'Portal Regional',
          'city'=>$city,
          'image'=>$article['image']??'',
          'source_city_confirmed'=>tvs_radar_text_mentions_city($title.' '.$desc.' '.$body,$city)?1:0,
          'priority'=>2,
          'source_domain'=>'https://liberal.com.br'
        ];

        if(empty($candidate['source_city_confirmed'])) continue;

        $reason='';
        if(!tvs_radar_candidate_region_ok($candidate,$city,$reason)) continue;

        $seen[$url]=1;
        $out[]=$candidate;
      }
    }
  }

  // Último fallback: indexação do Google News. Aqui NÃO confirma a cidade
  // automaticamente; o fato precisa mencionar a cidade-alvo antes de entrar.
  if(!$out){
    $q='site:liberal.com.br "'.$city.'" when:3d';
    $feed='https://news.google.com/rss/search?q='
      .urlencode($q)
      .'&hl=pt-BR&gl=BR&ceid=BR:pt-419';

    foreach(tvs_reporter_fetch_feed_compat($feed,max(6,(int)$limit)) as $it){
      $diag['google_seen']++;
      $it['city']=$city;
      $it['source']='Liberal';
      $it['source_type']='Portal Regional';
      $it['source_city_confirmed']=0;
      $it['priority']=2;
      $it['source_domain']='https://liberal.com.br';

      $reason='';
      if(!tvs_radar_candidate_region_ok($it,$city,$reason)) continue;

      $out[]=$it;
      if(count($out)>=$limit) break;
    }
  }

  if(PHP_SAPI==='cli'){
    echo 'LIBERAL_COLLECT city='.str_replace(' ','_',$city)
      .' accepted='.count($out)
      .' mode='.(isset($out[0]) && !tvs_radar_is_google_news_url($out[0]['url']??'')?'direct':'fallback')
      .' page_bytes='.(int)$diag['page_bytes']
      .' sitemap_bytes='.(int)$diag['sitemap_bytes']
      .' post_sitemaps='.(int)$diag['post_sitemaps']
      .' sitemap_checked='.(int)$diag['sitemap_checked']
      .' google_seen='.(int)$diag['google_seen']
      ."\\n";
  }

  return $out;
}

function tvs_radar_portal_cidade_sumare($limit=6){
  $base='https://sumare.portaldacidade.com';
  $pages=[
    $base.'/noticias/cidade',
    $base.'/noticias'
  ];
  $out=[]; $seen=[];

  foreach($pages as $page){
    $html=tvs_fetch_url($page);
    if($html==='') continue;

    if(!preg_match_all('~<a\b[^>]*href=["\']([^"\']*/noticias/[^"\']+)["\'][^>]*>(.*?)</a>~is',$html,$m,PREG_SET_ORDER)) continue;

    foreach($m as $match){
      $url=tvs_radar_absolute_source_url($base,$match[1]??'');
      if($url==='' || isset($seen[$url]) || tvs_radar_source_host($url)!=='sumare.portaldacidade.com') continue;

      $linkTitle=tvs_clean_text((string)($match[2]??''));
      if(!tvs_radar_is_article_path($url,$linkTitle,'Sumaré')) continue;
      $articleHtml=tvs_fetch_url($url);
      if($articleHtml==='') continue;

      $article=tvs_extract_article($url,$linkTitle);
      $title=trim((string)($article['title']??$linkTitle));
      $desc=trim((string)($article['description']??''));
      $body=trim((string)($article['body']??''));
      if($title==='' || tvs_strlen($desc.' '.$body)<80) continue;

      $published=tvs_radar_extract_published_at_from_html($articleHtml);
      if($published==='') continue;

      $candidate=[
        'title'=>$title,
        'url'=>$url,
        'description'=>$desc!==''?$desc:tvs_substr(tvs_clean_text($body),0,420),
        'published_at'=>$published,
        'source'=>'Portal da Cidade Sumaré',
        'source_type'=>'Portal Regional',
        'city'=>'Sumaré',
        'image'=>$article['image']??'',
        'source_city_confirmed'=>1,
        'priority'=>2,
        'source_domain'=>$base
      ];

      $reason='';
      if(!tvs_radar_candidate_region_ok($candidate,'Sumaré',$reason)) continue;

      $seen[$url]=1;
      $out[]=$candidate;
      if(count($out)>=$limit) break 2;
    }
  }

  if(PHP_SAPI==='cli'){
    echo 'PORTAL_CIDADE_SUMARE accepted='.count($out)."\n";
  }
  return $out;
}

function tvs_radar_builtin_regional_sources($city){
  $sources=[];
  // O Liberal possui coletor dedicado por cidade; aqui ficam apenas fontes
  // suplementares que não dependem do cadastro persistente.
  if($city==='Sumaré'){
    $sources[]=[
      'type'=>'Portal Regional',
      'city'=>'Sumaré',
      'name'=>'Portal da Cidade Sumaré',
      'url'=>'https://sumare.portaldacidade.com/',
      'rss'=>'',
      'active'=>true
    ];
  }
  return $sources;
}

function tvs_radar_resolve_and_hydrate_candidate($item,$city=''){
  if(!is_array($item)) return $item;

  $originalUrl=trim((string)($item['url']??''));
  if(!tvs_radar_is_google_news_url($originalUrl)) return $item;

  $batch=tvs_radar_resolve_candidate_urls([$item]);
  if(empty($batch[0]) || !is_array($batch[0])) return $item;
  $item=$batch[0];

  $resolvedUrl=trim((string)($item['url']??''));
  if($resolvedUrl==='' || tvs_radar_is_google_news_url($resolvedUrl)) return $item;

  $article=tvs_extract_article($resolvedUrl,(string)($item['title']??''));
  if(!is_array($article)) return $item;

  $articleTitle=trim((string)($article['title']??''));
  $articleDesc=trim((string)($article['description']??''));
  $articleBody=trim((string)($article['body']??''));
  $articleImage=trim((string)($article['image']??''));

  if($articleTitle!=='' && tvs_radar_title_match_score((string)($item['title']??''),$articleTitle)>=45){
    $item['title']=$articleTitle;
  }

  if($articleDesc!==''){
    $item['description']=$articleDesc;
  } elseif($articleBody!==''){
    $item['description']=tvs_substr(tvs_clean_text($articleBody),0,600);
  }

  if($articleBody!=='') $item['text']=$articleBody;
  if($articleImage!=='') $item['image']=$articleImage;

  $published=trim((string)($item['resolved_published_at']??''));
  if($published===''){
    $html=tvs_fetch_url($resolvedUrl);
    $published=$html!=='' ? tvs_radar_extract_published_at_from_html($html) : '';
  }
  if($published!=='') $item['published_at']=$published;

  $item['source_original_resolved']=1;
  $item['source_url']=$resolvedUrl;
  $item['city']=$city!==''?$city:($item['city']??'');

  return $item;
}

function tvs_radar_candidates_for_city($city){
  $volumeMode=tvs_radar_is_volume_mode();
  $fontes=tvs_read_json_file(dirname(__DIR__).'/data/fontes.json');
  if(!is_array($fontes)) $fontes=[];
  $fontes=array_merge($fontes,tvs_radar_builtin_regional_sources($city));
  $items=[]; $officialCount=0;

  // Coletor dedicado do Liberal por editoria municipal. O portal publica
  // várias cidades na mesma home; usar a editoria correta evita que uma
  // pauta de Campinas/Americana seja atribuída à cidade errada.
  foreach(tvs_radar_liberal_city($city,$volumeMode?8:6) as $it){
    $items[]=$it;
  }
  if($city==='Sumaré'){
    foreach(tvs_radar_portal_cidade_sumare($volumeMode?8:6) as $it){
      $items[]=$it;
    }
  }

  foreach($fontes as $src){
    if(isset($src['active']) && !$src['active']) continue;
    $scity=$src['city']??'Região';
    // Fontes genéricas da região não devem ser consumidas como se fossem da primeira cidade do loop.
    // Isso estava fazendo notícias de Americana/Campinas entrarem como Sumaré e bloquearem as demais cidades por duplicidade.
    if($scity==='Região' && !($volumeMode && preg_match('/google|regional|portal|notícias|noticias/iu', (string)($src['type']??'').' '.(string)($src['name']??'')))) continue;
    if($scity && $scity!=='Região' && tvs_lower($scity)!==tvs_lower($city)) continue;
    foreach(tvs_capture_source_items($src) as $it){
      $it['city']=$city;
      $it['source']=$src['name']??($it['source']??'Fonte cadastrada');
      $it['source_type']=$src['type']??'Fonte cadastrada';
      $it['priority']=tvs_source_priority(($src['type']??'').' '.($src['name']??''));
      if($it['priority']===1) $officialCount++;
      $items[]=$it;
    }
  }
  // Google Notícias é fallback de descoberta, não fonte principal do Radar.
  // Em modo normal, só complementa quando as fontes diretas não entregam volume mínimo,
  // e entra com limite pequeno para não recriar um backlog de URLs intermediárias.
  $directTarget=$volumeMode?30:12;
  if(count($items)<$directTarget){
    $googleLimit=max(0,$directTarget-count($items));
    foreach(tvs_radar_google_news($city,$googleLimit) as $it){
      $it['priority']=$volumeMode?4:3;
      $items[]=$it;
    }
  }
  $unique=[]; $seen=[];
  foreach($items as $it){
    $url=$it['url']??''; $title=$it['title']??'';
    if(!$url || !$title || isset($seen[$url])) continue;
    if(tvs_is_boilerplate($title)) continue;

    // Google News é somente descoberta. Resolve e hidrata a fonte original
    // ANTES dos gates geográfico e temporal, para que eles avaliem o fato
    // publicado pelo veículo em vez do snippet incompleto do agregador.
    if(tvs_radar_is_google_news_url($url)){
      $it=tvs_radar_resolve_and_hydrate_candidate($it,$city);
      $url=$it['url']??$url;
      $title=$it['title']??$title;
    }

    $falseReason='';
    if(tvs_radar_obvious_false_positive($it,$city,$falseReason)){
      tvs_radar_log_event(
        $title,
        $it['source']??'Fonte',
        $city,
        'DESCARTADA',
        $falseReason,
        $url
      );
      continue;
    }

    $regionReason='';
    if(!tvs_radar_candidate_region_ok($it,$city,$regionReason)){ tvs_radar_log_event($title,$it['source']??'Fonte',$city,'DESCARTADA',$regionReason,$url); continue; }
    if(tvs_is_non_news_candidate($title,$url,$it['description']??'')){ tvs_radar_log_event($title,$it['source']??'Fonte',$city,'DESCARTADA','Página institucional/menu/rodapé',$url); continue; }
    $time=tvs_radar_temporal_status($it);

    $age=$time['age']??null;
    $temporalLabel=(string)($time['label']??'');

    if(!$time['ok']){
      tvs_radar_log_event(
        $title,
        $it['source']??'Fonte',
        $city,
        'DESCARTADA',
        $temporalLabel!=='' ? $temporalLabel : 'Data/validade editorial não confirmada',
        $url
      );
      continue;
    }

    $it['age_days']=is_numeric($age)?(int)$age:null;
    if(!empty($time['force_review'])){
      $it['force_review']=true;
      $it['temporal_label']=$temporalLabel;
    }
    $seen[$url]=1;
    $it['category']=tvs_category_from_text(($it['title']??'').' '.($it['description']??''));
    if(empty($it['priority'])) $it['priority']=tvs_source_priority(($it['source_type']??'').' '.($it['source']??''));
    $unique[]=$it;
  }
  usort($unique,function($a,$b){
    $pa=(int)($a['priority']??3); $pb=(int)($b['priority']??3);
    if($pa!==$pb) return $pa<=>$pb;
    return strcmp((string)($b['published_at']??''), (string)($a['published_at']??''));
  });
  $unique=tvs_radar_deduplicate_topics($unique);

  $selected=tvs_radar_diversify_candidates(
    $unique,
    $volumeMode?140:72
  );

  // Régua editorial fixa por cidade.
  $selected=tvs_radar_apply_city_quotas(
    $selected,
    $city
  );

  // Google News já foi resolvido/hidratado antes dos gates. Candidatos
  // ainda não resolvidos permanecem rastreáveis para novas tentativas no pipeline.
  return $selected;
}
function tvs_is_google_news_candidate($cand){
  $src=tvs_lower(($cand['source']??'').' '.($cand['source_type']??'').' '.($cand['url']??''));
  return strpos($src,'google')!==false || strpos($src,'news.google.com')!==false;
}
function tvs_radar_google_title_parts($title){
  $title=tvs_clean_text((string)$title);
  $source='';
  $headline=$title;
  // Google News costuma vir como "Título - Veículo".
  $parts=preg_split('/\s+-\s+/u',$title);
  if(is_array($parts) && count($parts)>1 && tvs_strlen($parts[0])>18){
    $headline=trim($parts[0]);
    $source=trim(end($parts));
  }
  // Remove sufixos residuais comuns sem apagar o fato jornalístico.
  $source=preg_replace('~^www\.|\.com(\.br)?$~i','',$source);
  return ['title'=>$headline,'source'=>$source];
}
function tvs_radar_clean_google_title($title){
  $p=tvs_radar_google_title_parts($title);
  return $p['title'];
}
function tvs_radar_source_from_title($title,$fallback='Google Notícias'){
  $p=tvs_radar_google_title_parts($title);
  return $p['source'] ?: $fallback;
}
function tvs_build_material_from_candidate($cand){
  $url=$cand['url']??'';
  $rawTitle=$cand['title']??'';
  $title=tvs_is_google_news_candidate($cand) ? tvs_radar_clean_google_title($rawTitle) : $rawTitle;
  $desc=tvs_normalize_article_body($cand['description']??'');
  $a=['title'=>$title,'description'=>$desc,'body'=>'','image'=>$cand['image']??''];

  // A URL já passou pelo resolvedor do Radar. Quando houver URL original válida,
  // sempre tentamos abrir a matéria real: a IA não pode substituir a verificação da fonte.
  if($url && !tvs_radar_is_google_news_url($url)){
    $ex=tvs_extract_article($url,$title);
    if(is_array($ex) && (tvs_strlen(($ex['body']??'').($ex['description']??''))>80)){
      $a=$ex;
      if(empty($a['title'])) $a['title']=$title;
    }
  }
  $text=trim(($a['description']??'')."\n\n".($a['body']??''));
  if(tvs_strlen($text)<80) $text=$desc;
  if(tvs_strlen($text)<40) $text=$title;
  if(!empty($cand['enrichment_text'])){
    $text=trim($text."\n\n".(string)$cand['enrichment_text']);
  }
  $text=tvs_normalize_article_body($text);
  $cat=$cand['category'] ?? tvs_category_from_text(($title??'').' '.($desc??'').' '.$text);
  $image=tvs_best_image($cand['image']??'', $a['image']??'', $cat);
  return ['article'=>$a,'text'=>$text,'image'=>$image, 'url'=>$url, 'title'=>($a['title'] ?: $title), 'source'=>$cand['source']??'Fonte consultada'];
}
function tvs_radar_word_count($text){
  $text=tvs_clean_text((string)$text);
  if($text==='') return 0;
  $parts=preg_split('/\s+/u',$text);
  return count(array_filter($parts));
}

function tvs_radar_fact_signals($cand,$mat,$city){
  $title=trim((string)($mat['title']??$cand['title']??''));
  $text=trim((string)($mat['text']??''));
  $all=$title."\n".$text;
  $lc=tvs_lower($all);
  $published=(string)($cand['published_at']??'');

  $signals=[
    'quem'=>(
      preg_match('~\b(prefeitura|secretaria|governo|câmara|camara|polícia|policia|hospital|ubs|empresa|associação|associacao|escola|universidade|moradores|alunos|atletas|prefeito|vereador|deputado|senador|instituto|fundação|fundacao|defesa civil|guarda municipal|samu)\b~iu',$all)===1
      || preg_match('~\b\p{Lu}[\p{L}]+\s+\p{Lu}[\p{L}]+\b~u',$all)===1
    ),
    'o_que'=>tvs_strlen($title)>=18 && tvs_strlen($text)>=45,
    'quando'=>($published!=='' || preg_match('~\b(hoje|ontem|amanhã|amanha|segunda|terça|terca|quarta|quinta|sexta|sábado|sabado|domingo|\d{1,2}[/-]\d{1,2}(?:[/-]\d{2,4})?|\d{1,2}\s+de\s+[a-zç]+)\b~iu',$all)===1),
    'onde'=>trim((string)$city)!=='' && strpos(tvs_slug($all),tvs_slug((string)$city))!==false,
    'por_que'=>preg_match('~\b(devido|porque|por causa|objetivo|visa|para garantir|para ampliar|para reduzir|em razão|em razao)\b~iu',$all)===1,
    'como'=>preg_match('~\b(por meio|através|atraves|com apoio|em parceria|será realizado|sera realizado|foi realizado|passa a|vai oferecer|oferece|recebeu|realizou)\b~iu',$all)===1,
    'impacto'=>preg_match('~\b(\d+[\.,]?\d*|vagas?|pessoas?|alunos?|moradores?|atendimentos?|milhões?|milhoes?|mil|reais|r\$|km|unidades?)\b~iu',$all)===1,
  ];
  return $signals;
}

function tvs_radar_fact_package($cand,$mat,$city,$extraSources=[]){
  $signals=tvs_radar_fact_signals($cand,$mat,$city);
  $url=trim((string)($cand['url']??$mat['url']??''));
  $articleText=trim((string)(($mat['article']['description']??'').' '.($mat['article']['body']??'')));
  $sourceResolved=$url!=='' && !tvs_radar_is_google_news_url($url) && tvs_strlen($articleText)>=80;
  $trustedSource=tvs_radar_trusted_source($cand,$url);
  $freshnessOk=tvs_radar_freshness_gate($cand);

  $sources=[[
    'url'=>$url,
    'vehicle'=>(string)($cand['source']??'Fonte consultada'),
    'observed_at'=>date('c'),
    'primary'=>1
  ]];
  foreach((array)$extraSources as $src){
    if(!is_array($src) || empty($src['url'])) continue;
    $sources[]=$src;
  }

  $hosts=[];
  foreach($sources as $src){
    $host=tvs_radar_source_host($src['url']??'');
    if($host!=='') $hosts[$host]=1;
  }
  $secondSource=count($hosts)>=2;

  $fiveW2HCount=count(array_filter($signals));
  $score=(int)round(($fiveW2HCount/7)*35);
  if($sourceResolved) $score+=20;
  if($secondSource) $score+=15;

  $all=trim((string)($mat['title']??'').' '.(string)($mat['text']??''));
  $specificity=0;
  if(preg_match('~\b\d+[\.,]?\d*\b~u',$all)) $specificity+=5;
  if(preg_match('~\b\d{1,2}[/-]\d{1,2}(?:[/-]\d{2,4})?|\d{1,2}\s+de\s+[a-zç]+\b~iu',$all)) $specificity+=5;
  if(preg_match('~\b(Prefeitura|Secretaria|Hospital|Universidade|Câmara|Camara|Polícia|Policia|Associação|Associacao|Instituto|Fundação|Fundacao)\b~u',$all)) $specificity+=5;
  $score+=$specificity;

  $noiseFree=!tvs_is_non_news_candidate((string)($mat['title']??''),$url,(string)($mat['text']??''))
    && !tvs_is_commercial_candidate((string)($mat['title']??''),(string)($mat['text']??''),$url);
  if($noiseFree) $score+=10;

  $editorialText=tvs_lower(tvs_clean_text(
    (string)($mat['title']??'').' '.
    (string)($mat['text']??'').' '.
    (string)($cand['category']??'')
  ));
  $editorialInterest=preg_match(
    '~\b(sa[uú]de|hospital|upa|ubs|dengue|vacina[cç][aã]o|educa[cç][aã]o|escola|creche|'
    .'empregos?|vagas?|pat|trabalho|economia|empresa|ind[uú]stria|com[eé]rcio|'
    .'obras?|ponte|pontes|viaduto|viadutos|ciclovia|ordem de servi[cç]o|tr[aâ]nsito|mobilidade|transporte|seguran[cç]a|pol[ií]cia|pris[aã]o|acidente|'
    .'cultura|festival|teatro|m[uú]sica|evento|esporte|corrida|futebol|document[aá]rio|cinema|dan[cç]a|coral|literatura|patrim[oô]nio|exposi[cç][aã]o|'
    .'servi[cç]os? p[uú]blicos?|meio ambiente|turismo|defesa civil)\b~iu',
    $editorialText
  )===1;
  $contentWords=tvs_radar_word_count((string)($mat['text']??''));
  $coreContentSignals=!empty($signals['quem']) && !empty($signals['o_que']) && !empty($signals['quando']) && !empty($signals['onde']);
  $contentUsable=$sourceResolved
    && $noiseFree
    && (
      $contentWords>=70
      || ($trustedSource && $coreContentSignals && $contentWords>=45)
    );

  $age=$cand['age_days']??null;
  if(is_numeric($age)){
    if((int)$age<=3) $score+=5;
    elseif((int)$age<=7) $score+=3;
    elseif((int)$age<=14) $score+=1;
  } else {
    $score+=3;
  }
  $score=max(0,min(100,$score));

  $coreOk=!empty($signals['quem']) && !empty($signals['o_que']) && !empty($signals['quando']) && !empty($signals['onde']);

  return [
    'title'=>(string)($mat['title']??$cand['title']??''),
    'city'=>$city,
    'fact_date'=>(string)($cand['published_at']??''),
    'category'=>(string)($cand['category']??'Cidade'),
    'summary_5w2h'=>$signals,
    'facts'=>[],
    'sources'=>$sources,
    'divergences'=>[],
    'sf_score'=>$score,
    'core_4w_ok'=>$coreOk?1:0,
    'source_original_resolved'=>$sourceResolved?1:0,
    'trusted_source'=>$trustedSource?1:0,
    'freshness_ok'=>$freshnessOk?1:0,
    'editorial_interest'=>$editorialInterest?1:0,
    'content_usable'=>$contentUsable?1:0,
    'content_words'=>$contentWords,
    'second_source_confirmed'=>$secondSource?1:0,
    'flags'=>[
      'single_source'=>$secondSource?0:1,
      'official'=>((int)($cand['priority']??3)===1)?1:0,
      'trusted_source'=>$trustedSource?1:0,
      'freshness_ok'=>$freshnessOk?1:0
    ]
  ];
}

function tvs_radar_try_second_source($cand,$mat,$city){
  $primary=trim((string)($cand['url']??''));
  $candidateUrl=tvs_radar_resolve_by_bing_news(
    $cand['title']??($mat['title']??''),
    $city,
    ''
  );
  if($candidateUrl==='' || !tvs_radar_external_url_is_valid($candidateUrl)) return null;
  if(tvs_radar_source_host($candidateUrl)==='' || tvs_radar_source_host($candidateUrl)===tvs_radar_source_host($primary)) return null;

  $article=tvs_extract_article($candidateUrl,$cand['title']??($mat['title']??''));
  if(!is_array($article)) return null;
  $body=trim((string)(($article['description']??'').' '.($article['body']??'')));
  if(tvs_strlen($body)<80) return null;

  $titleScore=tvs_radar_title_match_score(
    (string)($cand['title']??$mat['title']??''),
    (string)($article['title']??'')
  );
  if($titleScore<55) return null;

  return [
    'url'=>$candidateUrl,
    'vehicle'=>tvs_radar_source_host($candidateUrl),
    'observed_at'=>date('c'),
    'primary'=>0,
    'title_score'=>$titleScore,
    'text'=>tvs_normalize_article_body($body)
  ];
}

function tvs_radar_enrich_candidate($cand,$city){
  if(tvs_radar_is_google_news_url($cand['url']??'')){
    $resolvedBatch=tvs_radar_resolve_candidate_urls([$cand]);
    if(isset($resolvedBatch[0]) && is_array($resolvedBatch[0])) $cand=$resolvedBatch[0];
  }

  $mat=tvs_build_material_from_candidate($cand);
  $extra=[];
  $package=tvs_radar_fact_package($cand,$mat,$city,$extra);

  if(($package['sf_score']??0)<70 || empty($package['core_4w_ok'])){
    $second=tvs_radar_try_second_source($cand,$mat,$city);
    if(is_array($second)){
      $extra[]=[
        'url'=>$second['url'],
        'vehicle'=>$second['vehicle'],
        'observed_at'=>$second['observed_at'],
        'primary'=>0,
        'title_score'=>$second['title_score']
      ];
      $enrichedText=trim(
        (string)($mat['text']??'')
        ."\n\n--- FONTE SECUNDÁRIA: ".(string)($second['vehicle']??'Fonte alternativa')
        ." | ".(string)($second['url']??'')." ---\n"
        .(string)($second['text']??'')
      );
      $mat['text']=tvs_normalize_article_body($enrichedText);
      $cand['enrichment_text']=$mat['text'];
      $cand['enrichment_sources']=$extra;
      $package=tvs_radar_fact_package($cand,$mat,$city,$extra);
    }
  }

  $cand['fact_package']=$package;
  $cand['sf_score']=(int)($package['sf_score']??0);
  return [$cand,$mat,$package];
}

function tvs_radar_enrichment_due($cand){
  $resolutionNext=(string)($cand['url_resolution_next_attempt_at']??'');
  if($resolutionNext!==''){
    $resolutionTs=strtotime($resolutionNext);
    if($resolutionTs && $resolutionTs>time()) return false;
  }
  $next=(string)($cand['enrichment_next_retry_at']??'');
  if($next==='') return true;
  $ts=strtotime($next);
  if(!$ts || $ts<=time()) return true;

  // Notícias frescas não podem ficar presas por janelas legadas de 2 horas.
  // Se a última tentativa ocorreu há pelo menos 15 minutos, libera novo ciclo.
  $updatedRaw=(string)($cand['pipeline_updated_at']??$cand['url_resolved_at']??$cand['enrichment_first_seen_at']??'');
  $updated=$updatedRaw!=='' ? strtotime($updatedRaw) : false;
  return $updated && (time()-$updated)>=900;
}

function tvs_radar_schedule_enrichment(&$cand,$reason=''){
  if(empty($cand['enrichment_first_seen_at'])) $cand['enrichment_first_seen_at']=date('c');
  $first=strtotime((string)$cand['enrichment_first_seen_at']);
  $ageHours=$first ? (time()-$first)/3600 : 0;

  if($ageHours<48){
    $cand['pipeline_stage']='aguardando_enriquecimento';
    $cand['enrichment_next_retry_at']=date('c',time()+900);
  } elseif($ageHours<168){
    $cand['pipeline_stage']='enriquecimento_baixa_prioridade';
    $cand['enrichment_next_retry_at']=date('c',time()+86400);
  } else {
    $cand['pipeline_stage']='expirada_sem_enriquecimento';
  }
  $cand['pipeline_reason']=$reason;
}
function tvs_radar_has_generic_text($text){
  $bad='~(Uma informação divulgada por|O tema foi classificado|Antes da publicação final|Moradores interessados devem acompanhar|A TV Sumar[eé] identificou|rascunho|monitor regional|conte[uú]do gerado automaticamente|redação deve conferir|fonte consultada para confirmar|entrou no acompanhamento regional|permanece em revisão editorial|A pauta tem relação|A ocorrência foi registrada em .* acompanhamento regional|Segundo informações publicadas por .* pode ter impacto direto|Novas informações oficiais poderão detalhar|o assunto envolve .* e pode ter impacto direto|fonte original .* serviços públicos ou atividades da região)~iu';
  return preg_match($bad,(string)$text)===1;
}
function tvs_radar_discard($cand,$city,$reason){
  $file=dirname(__DIR__).'/data/pautas_descartadas.json';
  $items=tvs_read_json_file($file); if(!is_array($items)) $items=[];
  $row=is_array($cand)?$cand:[];
  $row['original_id']=$cand['id']??'';
  $row['id']=uniqid('desc_');
  $row['city']=$city;
  $row['title']=$cand['title']??'';
  $row['url']=$cand['url']??($cand['source_url']??'');
  $row['source']=$cand['source']??'Fonte consultada';
  $row['reason']=$reason;
  $row['created_at']=date('c');
  $items[]=$row;
  $items=array_slice($items,-500);
  tvs_save_json_file($file,$items);
  if(function_exists('tvs_radar_log_event')) tvs_radar_log_event($row['title'], $row['source'], $city, 'DESCARTADA', $reason, $row['url']);
}
function tvs_radar_quality_ok(&$article,&$reason=''){
  if(!is_array($article)){ $reason='IA não retornou matéria válida'; return false; }
  $title=trim((string)($article['title']??''));
  $subtitle=trim((string)($article['subtitle']??''));
  $body=trim((string)($article['body']??''));

  // Régua equilibrada: descarta apenas o que realmente não pode virar matéria.
  // Conteúdo curto entra como "precisa revisão", em vez de ser reprovado automaticamente.
  if(tvs_strlen($title)<12){ $reason='Título ausente ou inválido'; return false; }
  if(tvs_radar_has_generic_text($title.' '.$subtitle.' '.$body)){ $reason='Texto genérico de sistema detectado'; return false; }
  if(tvs_is_boilerplate($title.' '.$body)){ $reason='Conteúdo parece menu/rodapé'; return false; }

  if($subtitle==='') $article['subtitle']=tvs_first_sentence($body,$title);
  if(empty($article['source_url'])) $article['source_url']='';
  if(empty($article['image'])){
    $article['image']='';
    $article['image_source_type']=$article['image_source_type']??'missing:source';
    $article['image_review_required']=1;
    $article['image_review_reason']=$article['image_review_reason']??'Imagem jornalística da matéria não encontrada; selecione uma imagem válida antes de publicar.';
  }

  $wc=tvs_radar_word_count($body);
  $minWords=tvs_radar_is_volume_mode()?14:20;
  if($wc<$minWords){ $reason='Texto muito curto para revisão'; return false; }
  if($wc<80){ $article['review_level']='precisa_revisao'; $article['editorial_status']='Nota curta'; }
  elseif($wc<120){ $article['review_level']='precisa_revisao'; $article['editorial_status']='Revisão'; }
  else { $article['review_level']=$article['review_level']??'normal'; $article['editorial_status']=$article['editorial_status']??'Publicável'; }
  if(tvs_radar_is_volume_mode()){ $article['review_level']='precisa_revisao'; if(($article['editorial_status']??'')==='Publicável') $article['editorial_status']='Revisão'; }
  if($wc>=260 && !empty($article['image']) && !tvs_radar_is_volume_mode()){ $article['editorial_status']='Destaque'; }
  return true;
}
function tvs_material_quality_ok($mat,&$reason=''){
  $text=trim((string)($mat['text']??''));
  $title=trim((string)($mat['title']??''));
  $url=trim((string)($mat['url']??''));
  $combined=$title.' '.$text.' '.$url;
  if(tvs_strlen($title)<8){ $reason='Título da fonte insuficiente'; return false; }
  if(tvs_is_boilerplate($combined)){ $reason='Conteúdo parece menu/rodapé'; return false; }
  if(tvs_is_non_news_candidate($title,$url,$text)){ $reason='Página institucional detectada'; return false; }
  if(function_exists('tvs_is_institutional_profile_text') && tvs_is_institutional_profile_text($title,$url,$text)){ $reason='Página institucional/perfil de secretaria detectado'; return false; }
  // Estratégia editorial: gerar opções para o editor decidir.
  // No modo normal, exige sinal regional mínimo. No Modo Volume Máximo, aceita nota curta
  // com fonte/título aproveitável, mas sempre entra como revisão humana.
  $score=tvs_news_detector_score($title,$url,$combined);
  if(tvs_radar_is_volume_mode()){
    if(tvs_strlen($text)<25 && $score<0){ $reason='Sem fato regional identificável'; return false; }
    if($score<0){ $reason='Sem relação regional clara'; return false; }
  } else {
    if(tvs_strlen($text)<35 && $score<2){ $reason='Sem fato regional identificável'; return false; }
    if($score<1){ $reason='Sem relação regional clara'; return false; }
  }
  return true;
}
function tvs_build_reviewable_article_without_ai($city,$category,$cand,$mat){
  // Fallback de segurança: nunca usa frases internas de sistema.
  // Se houver pouco conteúdo, cria uma NOTA CURTA factual para revisão, sem fingir apuração completa.
  $title=trim((string)($mat['title'] ?: ($cand['title']??'')));
  $title=tvs_radar_clean_google_title($title);
  $source=trim((string)($cand['source']??'Fonte consultada'));
  $url=trim((string)($cand['url']??''));
  $base=tvs_normalize_article_body((string)($mat['text']??($cand['description']??'')));
  $base=tvs_remove_editorial_artifacts($base);
  $summary=tvs_first_sentence($base, $title);
  if(tvs_strlen($summary)<25) $summary=$title;

  $paras=[];
  foreach(tvs_quality_paragraphs($base, 8) as $p){
    $p=tvs_remove_editorial_artifacts($p);
    if($p && !tvs_radar_has_generic_text($p) && !tvs_is_boilerplate($p)) $paras[]=$p;
  }

  // Fallback sem IA só pode usar informação real extraída.
  // Em modo produtivo, uma pauta curta pode entrar como NOTA CURTA para revisão,
  // desde que venha de título/descrição factual. Não inventa complemento.
  if(count($paras)<2){
    $facts=[];
    foreach([$summary, $base, $title] as $fact){
      $fact=tvs_remove_editorial_artifacts(tvs_clean_text($fact));
      if($fact && tvs_strlen($fact)>28 && !tvs_radar_has_generic_text($fact) && !tvs_is_boilerplate($fact)) $facts[]=$fact;
      if(count($facts)>=3) break;
    }
    $facts=array_values(array_unique($facts));
    if(!$facts) return null;
    $body=$facts;
  } else {
    $body=$paras;
  }

  $body=implode("\n\n", array_values(array_unique(array_filter($body))));
  $body=tvs_remove_editorial_artifacts($body);
  if(tvs_radar_has_generic_text($body)) return null;

  return [
    'title'=>$title,
    'subtitle'=>tvs_first_sentence($base, $summary),
    'summary'=>$summary,
    'body'=>$body,
    'category'=>$category,
    'tags'=>[$city,$category,'TV Sumaré'],
    'seo_title'=>$title,
    'meta_description'=>tvs_substr($summary,0,155),
    'slug'=>tvs_slug($title),
    'instagram_caption'=>$title."\n\nLeia no portal da TV Sumaré.",
    'whatsapp_text'=>'Confira no portal da TV Sumaré: '.$title,
    'source'=>$source,
    'source_url'=>$url,
    'image'=>tvs_best_image('', $mat['image']??'', $category),
    'image_credit'=>tvs_image_credit_from_source($source, tvs_best_image('', $mat['image']??'', $category)),
    'review_level'=>'precisa_revisao',
    'editorial_status'=>'Revisão'
  ];
}
function tvs_generate_ready_article($city,$cand){
  global $gemini_api_key;
  $requestedCity=$city;
  // Agregador é apenas descoberta. Sem resolução para a matéria original, a pauta
  // não entra na redação e não é completada artificialmente pela IA.
  if(tvs_radar_is_google_news_url($cand['url']??'')){
    tvs_radar_discard($cand,$city,'Agregador sem URL original confirmada');
    return null;
  }

  $mat=tvs_build_material_from_candidate($cand);
  // Cidade editorial = cidade do fato, não necessariamente cidade consultada no loop.
  // Ex.: Portal de Sumaré pode trazer acidente em Americana; a matéria deve cair em Americana.
  $detectText=($mat['title']??'').' '.($mat['text']??'').' '.($cand['title']??'').' '.($cand['description']??'').' '.($cand['url']??'').' '.($cand['source']??'');
  $detectedCity=tvs_radar_detect_city_from_text($detectText, '');
  if($detectedCity) $city=$detectedCity;
  if(!in_array($city,tvs_radar_allowed_cities(),true)){ tvs_radar_discard($cand,$city,'Cidade fora da região monitorada'); return null; }
  $regionReason='';
  $candRegion=$cand; $candRegion['description']=($cand['description']??'').' '.($mat['text']??''); $candRegion['city']=$city;
  if(!tvs_radar_candidate_region_ok($candRegion,$city,$regionReason)){ tvs_radar_discard($cand,$city,$regionReason); return null; }
  $category=$cand['category'] ?? tvs_category_from_text(($cand['title']??'').' '.($cand['description']??'').' '.($mat['text']??''));
  if(!empty($mat['article']['discard_reason'])){
    tvs_radar_discard($cand,$city,$mat['article']['discard_reason']);
    return null;
  }
  if(tvs_is_commercial_candidate($mat['title']??($cand['title']??''), ($mat['text']??'').' '.($cand['description']??''), $cand['url']??'')){
    tvs_radar_log_event($cand['title']??'', $cand['source']??'Fonte consultada', $city, 'GUIA_COMERCIAL', 'Conteúdo com perfil comercial/empresa, não notícia', $cand['url']??'');
    return null;
  }
  $reason='';
  if(!tvs_material_quality_ok($mat,$reason)){
    tvs_radar_discard($cand,$city,$reason);
    tvs_radar_log_event($cand['title']??'', $cand['source']??'Fonte consultada', $city, 'DESCARTADA', $reason, $cand['url']??'');
    return null;
  }
  $facts=tvs_extract_facts_block($mat['title']??($cand['title']??''),$city,$category,$mat['text']??'',($cand['source']??'Fonte consultada'),($cand['url']??''));
  $factPackage=is_array($cand['fact_package']??null)?$cand['fact_package']:[];
  $material="CIDADE: {$city}\nCATEGORIA: {$category}\nFONTE PRINCIPAL: ".($cand['source']??'Fonte consultada')."\nURL PRINCIPAL: ".($cand['url']??'')."\nTÍTULO ORIGINAL: ".($mat['title']??'')."\n\n".$facts
    ."\n\nPACOTE FACTUAL E PROVENIÊNCIA:\n"
    .json_encode($factPackage,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    ."\n\nREGRA DE GROUNDING: use somente fatos presentes no material e nas fontes identificadas; não complete lacunas por inferência."
    ."\n\nCONTEÚDO COMPLETO COLETADO E ENRIQUECIDO:\n".tvs_substr($mat['text']??'',0,12000);

  // Toda pauta precisa atravessar o Editor de Matéria IA antes de poder ser publicada.
  // O Repórter IA pode montar uma primeira versão, mas ela não recebe elegibilidade
  // de publicação enquanto a etapa editorial obrigatória não for concluída.
  $isGoogle = function_exists('tvs_is_google_news_candidate') ? tvs_is_google_news_candidate($cand) : false;
  if($isGoogle && tvs_strlen($mat['text']??'') < 320){
    $baseline=tvs_build_reviewable_article_without_ai($city,$category,$cand,$mat);
  } else {
    $baseline=gemini_reporter_article($gemini_api_key??'', $material, ['city'=>$city,'theme'=>$category,'category'=>$category]);
    if(!$baseline) $baseline=tvs_build_reviewable_article_without_ai($city,$category,$cand,$mat);
  }
  if(!$baseline) $baseline=tvs_build_reviewable_article_without_ai($city,$category,$cand,$mat);
  if(!$baseline){
    tvs_radar_log_event($cand['title']??'', $cand['source']??'Fonte consultada', $city, 'REVISÃO', 'Material insuficiente para o Editor de Matéria IA', $cand['url']??'');
    return null;
  }

  $baseline['city']=$city;
  $baseline['category']=$baseline['category']??$category;
  $baseline['source']=$cand['source']??'Fonte consultada';
  $baseline['source_url']=$cand['url']??'';
  $baseline['editorial_origin']='radar';

  $edited=function_exists('tvs_ai_editor_process_article')
    ? tvs_ai_editor_process_article($gemini_api_key??'',$baseline,[
        'city'=>$city,
        'category'=>$category,
        'source'=>$cand['source']??'Fonte consultada',
        'source_url'=>$cand['url']??'',
        'origin'=>'radar'
      ])
    : null;

  if($edited){
    $result=$edited;
  } else {
    // Falha do Editor IA nunca vira atalho para publicação.
    $result=$baseline;
    $result['ai_editor_processed']=0;
    $result['ai_editor_stage']='pending';
    $result['review_level']='precisa_revisao';
    $result['editorial_status']='Aguardando Editor IA';
    $result['publication_eligible']=0;
  }

  $result=tvs_sanitize_ai_article($result,['city'=>$city,'name'=>$cand['source']??'Fonte consultada'],['title'=>$cand['title']??'','description'=>$cand['description']??'','body'=>$mat['text']??'','url'=>$cand['url']??'']);
  if(tvs_is_non_news_candidate($result['title']??'', $cand['url']??'', ($result['subtitle']??'').' '.($result['body']??''))){
    tvs_radar_discard($cand,$city,'Texto institucional detectado, não é matéria jornalística');
    return null;
  }
  if(tvs_radar_has_generic_text(($result['title']??'').' '.($result['subtitle']??'').' '.($result['body']??''))){
    tvs_radar_discard($cand,$city,'Texto com frase interna ou genérica detectada');
    return null;
  }
  $result['source_original_title']=trim((string)($mat['title']??$cand['title']??''));
  $result['id']=uniqid('aprov_');
  $result['city']=$city;
  if($requestedCity!==$city) $result['radar_requested_city']=$requestedCity;
  $result['category']=$result['category'] ?: $category;
  $result['image']=tvs_best_image($cand['image'] ?? '', $mat['image'] ?? '', $result['category'] ?: $category);
  $result['image_source_type']=$cand['image_source_type']??'';
  $result['image_review_required']=!empty($cand['image_review_required']) ? 1 : 0;
  $result['image_review_reason']=$cand['image_review_reason']??'';
  $originalImage=trim((string)($cand['image']??''));
  $resolvedImage=trim((string)($mat['image']??''));
  $hasVerifiedSourceImage=false;
  foreach([$originalImage,$resolvedImage] as $imgCandidate){
    if($imgCandidate!=='' && function_exists('tvs_is_valid_image_url') && tvs_is_valid_image_url($imgCandidate) && !preg_match('~logo|placeholder|sprite|icon|icone|avatar|favicon~i',$imgCandidate)){
      $hasVerifiedSourceImage=true;
      break;
    }
  }
  if(!$hasVerifiedSourceImage){
    $result['image']='';
    $result['image_review_required']=0;
    $result['image_review_reason']='Imagem jornalística não confirmada. A matéria pode ser publicada; Home, Hero e redes exigem imagem válida.';
    if($result['image_source_type']==='') $result['image_source_type']='missing:source';
  }

  if(empty($result['image_credit'])){
    $result['image_credit']=tvs_image_credit_from_source(
      $cand['source']??'Fonte consultada',
      $result['image']
    );
  }
  $result['source']=$cand['source']??'Fonte consultada';
  $result['source_url']=$cand['url']??'';
  $result['status']='aguardando';
  if(!empty($cand['age_days'])) $result['age_days']=$cand['age_days'];
  if(!empty($cand['temporal_label'])) $result['temporal_label']=$cand['temporal_label'];
  if(!empty($cand['force_review'])){ $result['review_level']='precisa_revisao'; $result['editorial_status']='Revisão temporal'; }
  $sensitive=tvs_radar_sensitive_topic(($result['title']??''), ($result['subtitle']??'').' '.($result['summary']??'').' '.($result['body']??''));
  $score=tvs_radar_editorial_score($result['title']??'', $city, $result['category']??$category, $result['source']??($cand['source']??''), ($result['subtitle']??'').' '.($result['summary']??'').' '.($result['body']??''), $result['source_url']??($cand['url']??''), $result['age_days']??null);
  $st=tvs_radar_status_from_score($score,$sensitive);
  if($st['review_level']==='descartar'){ tvs_radar_discard($cand,$city,'Score editorial insuficiente: '.$score); return null; }
  $result['editorial_score']=$score;
  $result['review_level']=$st['review_level'];
  $result['editorial_status']=$st['editorial_status'];
  $result['sensitive_review_required']=!empty($st['sensitive']) ? 1 : 0;
  if(!empty($st['sensitive'])) $result['sensitive_review_reason']='Pauta sensível ou de alto impacto: revisão humana obrigatória antes da publicação.';

  $editorProcessed=!empty($result['ai_editor_processed']);
  if(!$editorProcessed){
    $result['review_level']='precisa_revisao';
    $result['editorial_status']='Aguardando Editor IA';
    $result['ai_editor_stage']='pending';
  }

  // Imagem é um atributo paralelo. Falta de foto não altera o estado editorial.
  // A passagem pelo Editor IA, porém, é obrigatória para publicação.
  $result['image_status']=!empty($result['image_review_required'])?'missing':'verified';
  $result['editorial_state']=$editorProcessed?'qualified':'needs_ai_editor';
  $result['region_status']='confirmed';
  $result['freshness_status']='current';
  $result['source_status']='original';
  $result['duplicate_status']='unique';
  $result['publication_eligible']=$editorProcessed?1:0;
  $result['home_eligible']=!empty($result['image_review_required'])?0:1;
  $result['video_eligible']=1;
  $result['created_at']=date('c');
  if(!tvs_radar_quality_ok($result,$reason)){
    tvs_radar_discard($cand,$city,$reason);
    return null;
  }
  return $result;
}
function tvs_category_image($category){
  return tvs_category_image_path($category);
}
function tvs_radar_discovery_read(){
  global $radarDiscoveryFile;
  $items=tvs_read_json_file($radarDiscoveryFile);
  return is_array($items)?array_values($items):[];
}
function tvs_radar_discovery_save($items){
  global $radarDiscoveryFile;
  tvs_save_json_file($radarDiscoveryFile,array_slice(array_values($items),-300));
}
function tvs_radar_discovery_key($item){
  $url=trim((string)($item['url']??$item['source_url']??''));
  if($url!=='') return 'url:'.$url;
  return 'title:'.md5(tvs_lower(trim((string)($item['city']??'').'|'.(string)($item['title']??''))));
}

function tvs_radar_entry_audit($cand,$requestedCity,$history=[]){
  $status='aprovada_para_enriquecimento';
  $reason='Auditoria de entrada concluída.';
  if(!in_array((string)$requestedCity,tvs_radar_allowed_cities(),true)){
    $status='descartada_regiao';
    $reason='Cidade fora do recorte operacional do Radar.';
  }

  if($status==='aprovada_para_enriquecimento'){
    $regionReason='';
    $regionOk=tvs_radar_candidate_region_ok($cand,(string)$requestedCity,$regionReason)
      || tvs_radar_source_matches_city($cand,(string)$requestedCity);
    if(!$regionOk){
      if(tvs_radar_has_outside_city_signal(tvs_radar_fact_text($cand))){
        $status='descartada_regiao';
        $reason=$regionReason!==''?$regionReason:'Fato associado a cidade fora da região monitorada.';
      } else {
        $status='precisa_resolver_fonte';
        $reason='Cidade ainda depende da resolução da fonte original.';
      }
    }
  }

  if(in_array($status,['aprovada_para_enriquecimento','precisa_resolver_fonte'],true)){
    $temporal=tvs_radar_temporal_status($cand);
    if(empty($temporal['ok'])){
      if(array_key_exists('age',$temporal) && $temporal['age']!==null){
        $status='descartada_data';
        $reason=$temporal['label']??'Pauta fora da janela editorial.';
      } else {
        $status='precisa_resolver_fonte';
        $reason='Data da fonte ainda não confirmada; resolver origem antes de avaliar atualidade.';
      }
    }
  }

  if(in_array($status,['aprovada_para_enriquecimento','precisa_resolver_fonte'],true)
     && tvs_radar_history_duplicate($cand,(array)$history)){
    $status='duplicada';
    $reason='Pauta já existe na fila ou no histórico publicado.';
  }

  if(in_array($status,['aprovada_para_enriquecimento','precisa_resolver_fonte'],true)
     && tvs_is_non_news_candidate(
       $cand['title']??'',
       $cand['url']??$cand['source_url']??'',
       ($cand['description']??'').' '.($cand['text']??'')
     )){
    $status='descartada_institucional';
    $reason='Página institucional, genérica, comercial ou sem fato jornalístico identificável.';
  }

  if($status==='aprovada_para_enriquecimento'){
    $url=trim((string)($cand['url']??$cand['source_url']??''));
    $snippet=tvs_clean_text(($cand['description']??'').' '.($cand['text']??''));
    if($url==='' || tvs_radar_is_google_news_url($url) || tvs_radar_word_count($snippet)<40){
      $status='precisa_resolver_fonte';
      $reason='Fonte original ou corpo completo precisa ser resolvido antes do enriquecimento.';
    }
  }

  return ['status'=>$status,'reason'=>$reason,'checked_at'=>date('c'),'rule_version'=>'1.2'];
}

function tvs_radar_audit_missing_entries_v12($limit=100){
  global $newsFile;
  $discovery=tvs_radar_discovery_read();
  if(!is_array($discovery) || !$discovery){
    return ['checked'=>0,'audited'=>0,'discarded'=>0,'remaining_without_audit'=>0];
  }

  $approval=tvs_queue_read();
  $news=tvs_read_json_file($newsFile); if(!is_array($news)) $news=[];
  $history=array_merge($approval,$news);

  $checked=0;
  $audited=0;
  $discarded=0;
  foreach($discovery as $idx=>&$cand){
    if($checked>=$limit) break;
    if(!is_array($cand) || !empty($cand['entry_audit_status'])) continue;
    $checked++;

    $city=(string)($cand['radar_requested_city']??$cand['city']??'');
    $audit=tvs_radar_entry_audit($cand,$city,$history);
    $cand['entry_audit_status']=$audit['status'];
    $cand['entry_audit_reason']=$audit['reason'];
    $cand['entry_audit_at']=$audit['checked_at'];
    $cand['editorial_rule_version']=$audit['rule_version'];
    $cand['reprocessed_at']=date('c');
    $cand['reprocess_reason']='mandatory_entry_audit_recovery';

    if(in_array($audit['status'],['descartada_regiao','descartada_data','duplicada','descartada_institucional'],true)){
      tvs_radar_discard($cand,$city,$audit['reason']);
      unset($discovery[$idx]);
      $discarded++;
      continue;
    }

    if($audit['status']==='precisa_resolver_fonte'){
      $cand['pipeline_stage']='precisa_resolver_fonte';
    } elseif(empty($cand['pipeline_stage'])){
      $cand['pipeline_stage']='pauta_encontrada';
    }
    $audited++;
  }
  unset($cand);

  tvs_radar_discovery_save(array_values($discovery));
  $remaining=0;
  foreach(tvs_radar_discovery_read() as $row){
    if(is_array($row) && empty($row['entry_audit_status'])) $remaining++;
  }

  return [
    'checked'=>$checked,
    'audited'=>$audited,
    'discarded'=>$discarded,
    'remaining_without_audit'=>$remaining
  ];
}

function tvs_radar_cursor_state(){
  global $radarCursorFile;
  $state=tvs_read_json_file($radarCursorFile);
  return is_array($state)?$state:[];
}
function tvs_radar_cursor_save($state){
  global $radarCursorFile;
  tvs_save_json_file($radarCursorFile,(array)$state);
}
function tvs_radar_collect_discovery($mode='normal',$perCity=12){
  global $cities,$newsFile;
  $discovery=tvs_radar_discovery_read();
  $approval=tvs_queue_read();
  $news=tvs_read_json_file($newsFile); if(!is_array($news)) $news=[];
  $seen=[];
  $history=array_merge($approval,$news);
  foreach(array_merge($discovery,$history) as $row){
    $key=tvs_radar_discovery_key($row);
    if($key!=='title:'.md5('')) $seen[$key]=1;
  }

  $added=0;
  foreach($cities as $city){
    $cityAdded=0;
    foreach(tvs_radar_candidates_for_city($city) as $cand){
      if($cityAdded>=$perCity) break;
      $key=tvs_radar_discovery_key($cand);
      if(isset($seen[$key])) continue;
      if(tvs_radar_history_duplicate($cand,$history)){
        tvs_radar_log_event($cand['title']??'', $cand['source']??'Fonte', $city, 'DESCARTADA', 'Duplicata de pauta já aprovada/publicada', $cand['url']??'');
        continue;
      }
      $cand['id']=$cand['id']??uniqid('pauta_');
      $cand['city']=$cand['city']??$city;
      $cand['radar_requested_city']=$city;
      $cand['pipeline_attempts']=0;
      $cand['source_resolution_attempts']=(int)($cand['source_resolution_attempts']??0);
      $cand['extraction_attempts']=0;
      $cand['second_source_attempts']=0;
      $cand['pipeline_created_at']=date('c');
      $cand['pipeline_updated_at']=date('c');
      $audit=tvs_radar_entry_audit($cand,$city,$history);
      $cand['entry_audit_status']=$audit['status'];
      $cand['entry_audit_reason']=$audit['reason'];
      $cand['entry_audit_at']=$audit['checked_at'];
      $cand['editorial_rule_version']=$audit['rule_version'];
      if(in_array($audit['status'],['descartada_regiao','descartada_data','duplicada','descartada_institucional'],true)){
        tvs_radar_discard($cand,$city,$audit['reason']);
        continue;
      }
      $cand['pipeline_stage']=$audit['status']==='precisa_resolver_fonte'?'precisa_resolver_fonte':'pauta_encontrada';
      $discovery[]=$cand;
      $seen[$key]=1;
      $cityAdded++;
      $added++;
    }
  }
  tvs_radar_discovery_save($discovery);
  return $added;
}
function tvs_radar_ready_count_by_city($queue){
  global $cities;
  $counts=array_fill_keys($cities,0);
  foreach((array)$queue as $q){
    $city=(string)($q['city']??'');
    if(!isset($counts[$city])) continue;
    if(!empty($q['ai_editor_processed']) && ($q['publication_eligible']??0)) $counts[$city]++;
  }
  return $counts;
}

function tvs_radar_ready_categories_by_city($queue){
  global $cities;
  $out=[];
  foreach($cities as $city) $out[$city]=[];
  foreach((array)$queue as $q){
    $city=(string)($q['city']??'');
    if(!isset($out[$city])) continue;
    if(empty($q['ai_editor_processed']) || empty($q['publication_eligible'])) continue;
    $cat=trim((string)($q['category']??'Cidade'));
    if($cat==='') $cat='Cidade';
    $out[$city][$cat]=($out[$city][$cat]??0)+1;
  }
  return $out;
}

function tvs_radar_final_category_cap($category,$targetPerCity=5){
  $category=trim((string)$category);
  // Em uma fila de 5, Esportes ocupa no máximo 1 vaga. As demais editorias
  // podem ocupar até 2, evitando monocultura editorial sem forçar pauta fraca.
  if($category==='Esportes') return 1;
  if($category==='Política') return 1;
  return 2;
}

function tvs_radar_category_room($city,$category,$readyCategories,$targetPerCity=5){
  $used=(int)($readyCategories[$city][$category]??0);
  return $used < tvs_radar_final_category_cap($category,$targetPerCity);
}

function tvs_radar_factually_ready($package){
  $sf=(int)($package['sf_score']??0);
  $coreOk=!empty($package['core_4w_ok']);
  $sourceResolved=!empty($package['source_original_resolved']);
  $trustedSource=!empty($package['trusted_source']);
  $freshnessOk=!empty($package['freshness_ok']);
  $contentUsable=!empty($package['content_usable']);
  $editorialInterest=!empty($package['editorial_interest']);

  // Régua v1.1 canônica:
  // - SF >= 70 segue, desde que os gates factuais duros estejam presentes.
  // - SF 60-69 segue somente com os quatro gates compensatórios completos.
  // - SF 40-59 exige enriquecimento ativo/segunda fonte.
  // - SF < 40 permanece aguardando enriquecimento.
  // Contagem de palavras não participa desta decisão.
  $strongGates=$sourceResolved && $coreOk && $trustedSource && $freshnessOk;
  $highScoreGates=$sourceResolved && $coreOk && $freshnessOk;

  $ready=false;
  $decisionBand='aguardando_enriquecimento';
  if($sf>=70 && $highScoreGates){
    $ready=true;
    $decisionBand='sf_ge_70';
  } elseif($sf>=60 && $sf<70 && $strongGates){
    $ready=true;
    $decisionBand='sf_60_69_gates';
  } elseif($sf>=40){
    $decisionBand='enriquecimento_ativo';
  }

  return [
    'ready'=>$ready?1:0,
    'sf'=>$sf,
    'decision_band'=>$decisionBand,
    'core_4w_ok'=>$coreOk?1:0,
    'source_original_resolved'=>$sourceResolved?1:0,
    'trusted_source'=>$trustedSource?1:0,
    'freshness_ok'=>$freshnessOk?1:0,
    'content_usable'=>$contentUsable?1:0,
    'editorial_interest'=>$editorialInterest?1:0,
    'layered_eligible'=>$strongGates?1:0
  ];
}

function tvs_radar_retry_pending_editor_articles(&$approval,$limit=6,$ignoreSchedule=false){
  global $gemini_api_key;
  $recovered=0; $attempted=0;
  foreach($approval as &$item){
    if($attempted>=$limit) break;
    if(!is_array($item) || !empty($item['ai_editor_processed'])) continue;
    if(trim((string)($item['title']??''))==='' || trim((string)($item['body']??''))==='') continue;

    $nextAiRetry=(string)($item['ai_editor_next_retry_at']??'');
    if(!$ignoreSchedule && $nextAiRetry!=='' && ($nextAiTs=strtotime($nextAiRetry)) && $nextAiTs>time()) continue;
    $previousAttempts=(int)($item['ai_editor_attempts']??0);
    if($previousAttempts>=3){
      $item['queue_status']='processing';
      $item['ai_editor_stage']='manual_review';
      $item['editorial_status']='Revisão manual necessária';
      $item['publication_eligible']=0;
      $item['queue_pending_reasons']=array_values(array_unique(array_filter(array_merge(
        (array)($item['queue_pending_reasons']??[]),
        ['Editor IA não concluiu após 3 tentativas; revisar manualmente']
      ))));
      continue;
    }

    $attempted++;
    $item['ai_editor_attempts']=$previousAttempts+1;
    $item['ai_editor_last_attempt_at']=date('c');
    $edited=function_exists('tvs_ai_editor_process_article') ? tvs_ai_editor_process_article($gemini_api_key??'',$item,[
      'city'=>$item['city']??'Região','category'=>$item['category']??'Cidade',
      'source'=>$item['source']??'Fonte consultada','source_url'=>$item['source_url']??'','origin'=>'radar_retry'
    ]) : null;
    if(!$edited){
      $rawAiError=trim((string)($GLOBALS['tvs_ai_last_error']??''));
      $typedReason=trim((string)($GLOBALS['tvs_ai_last_reason']??''));
      $aiErrorCode='editor_nao_concluiu';
      $aiErrorLabel='Editor IA ainda não concluiu';

      if($typedReason==='truncated'){
        $aiErrorCode='resposta_truncada';
        $aiErrorLabel='Resposta da IA truncada por limite de tokens';
      } elseif($typedReason==='schema_invalid'){
        $aiErrorCode='schema_invalido';
        $aiErrorLabel='Resposta da IA fora do schema esperado';
      } elseif($typedReason==='unparseable'){
        $aiErrorCode='resposta_fora_formato';
        $aiErrorLabel='Resposta da IA fora do formato esperado';
      } elseif($typedReason==='provider_limit'){
        $aiErrorCode='limite_temporario_provider';
        $aiErrorLabel='Limite temporário do provider de IA';
      } elseif($typedReason==='provider_error'){
        $aiErrorCode='provider_indisponivel';
        $aiErrorLabel='Provider de IA não concluiu a chamada';
      } elseif(stripos($rawAiError,'texto insuficiente após edição')!==false){
        $aiErrorCode='texto_insuficiente_pos_edicao';
        $aiErrorLabel='Texto insuficiente após edição';
      }

      if(in_array($aiErrorCode,['limite_temporario_provider','resposta_truncada','provider_indisponivel'],true)){
        $item['ai_editor_attempts']=$previousAttempts;
        $item['ai_editor_next_retry_at']=date('c',time()+($aiErrorCode==='limite_temporario_provider'?3600:900));
      }
      $item['ai_editor_last_error_code']=$aiErrorCode;
      $item['ai_editor_last_error_label']=$aiErrorLabel;
      $item['queue_status']='processing'; $item['ai_editor_stage']='pending';
      $item['editorial_status']='Aguardando Editor IA'; $item['publication_eligible']=0;
      $item['queue_pending_reasons']=array_values(array_unique(array_filter(array_merge(
        (array)($item['queue_pending_reasons']??[]),[$aiErrorLabel.'; nova tentativa automática será realizada']
      ))));
      if(PHP_SAPI==='cli'){
        $diag=trim((string)($GLOBALS['tvs_ai_last_error']??'sem_detalhe'));
        $diag=preg_replace('/\\s+/u',' ',$diag);
        $hub=(array)($GLOBALS['tvs_centro_ia_last_result']??[]);
        $hubDiag=!empty($hub['ok'])
          ? 'ok:'.(string)($hub['model']??'hub-routed')
          : preg_replace('/\\s+/u',' ',(string)($hub['error']??'nao_acionado'));
        echo "EDITOR_RETRY_FAIL attempts=".(int)$item['ai_editor_attempts']
          ." city=".str_replace(' ','_',(string)($item['city']??'Região'))
          ." words=".tvs_radar_word_count((string)($item['body']??''))
          ." hub=".substr($hubDiag,0,260)
          ." reason=".substr($diag,0,180)
          ." title=".substr(preg_replace('/\\s+/u',' ',(string)($item['title']??'')),0,120)."\n";
      }
      continue;
    }
    if(PHP_SAPI==='cli'){
      echo "EDITOR_RETRY_OK attempts=".(int)$item['ai_editor_attempts']
        ." city=".str_replace(' ','_',(string)($item['city']??'Região'))
        ." title=".substr(preg_replace('/\\s+/u',' ',(string)($item['title']??'')),0,120)."\n";
    }
    $item['source_original_title']=$item['source_original_title']??$item['title']??'';
    $preservedId=$item['id']??uniqid('aprov_'); $preservedCreated=$item['created_at']??date('c');
    $item=array_merge($item,$edited); $item['id']=$preservedId; $item['created_at']=$preservedCreated;
    $item['ai_editor_processed']=1; $item['ai_editor_stage']='completed';
    $item['ai_editor_processed_at']=$item['ai_editor_processed_at']??date('c');
    $readiness=function_exists('tvs_radar_queue_item_readiness') ? tvs_radar_queue_item_readiness($item) : ['ready'=>1,'reasons'=>[]];
    $item['queue_pending_reasons']=array_values(array_unique(array_filter((array)($readiness['reasons']??[]))));
    $item['queue_status']=!empty($readiness['ready'])?'ready':'processing';
    $item['publication_eligible']=!empty($readiness['ready'])?1:0;
    $item['editorial_status']=!empty($readiness['ready'])?'Editor IA concluído':'Revisão editorial pendente';
    if(!empty($readiness['ready'])) $recovered++;
  }
  unset($item);
  return ['attempted'=>$attempted,'recovered'=>$recovered];
}

function tvs_radar_repair_queue_listing_urls($limit=20){
  $limit=max(1,min(40,(int)$limit));
  $approval=tvs_queue_read();
  global $radarLogFile;
  $history=array_merge(tvs_radar_discovery_read(),(array)tvs_read_json_file($radarLogFile));
  $restoredTitles=0;
  foreach($approval as &$legacyItem){
    if(!is_array($legacyItem)||empty($legacyItem['ai_editor_processed']))continue;
    $restored=tvs_radar_restore_original_title($legacyItem,$history);
    if($restored!==$legacyItem){$legacyItem=$restored;$restoredTitles++;}
  }
  unset($legacyItem);
  $attempted=0; $resolved=0; $ready=0; $failed=0; $methods=[];

  foreach($approval as &$item){
    if($attempted>=$limit) break;
    if(!is_array($item) || empty($item['ai_editor_processed'])) continue;

    $readiness=function_exists('tvs_radar_queue_item_readiness')
      ? tvs_radar_queue_item_readiness($item)
      : ['ready'=>1,'reasons'=>[]];
    if(!empty($readiness['ready'])) continue;

    $reasons=(array)($readiness['reasons']??[]);
    if(!in_array('URL corresponde a página de listagem',$reasons,true)) continue;

    $retryAt=strtotime((string)($item['queue_url_repair_next_retry_at']??''))?:0;
    if($retryAt>time()) continue;
    $attempted++;
    $title=trim((string)($item['source_original_title']??$item['title']??''));
    $city=trim((string)($item['city']??''));
    $current=trim((string)($item['source_url']??$item['url']??''));
    $source=(string)($item['source']??'');

    $sourceDomain=tvs_radar_source_domain_hint($source,$title);
    if($sourceDomain==='' && $current!==''){
      $host=tvs_radar_source_host($current);
      if($host!=='') $sourceDomain='https://'.$host;
    }

    $candidate='';
    $method='';
    $validation=null;
    $tryCandidate=function($url,$candidateMethod) use (&$candidate,&$method,&$validation,$title,$city,$sourceDomain,$item){
      $url=trim((string)$url);
      if($url==='') return false;
      $check=tvs_radar_validate_resolved_article(
        $url,
        $title,
        $city,
        $sourceDomain,
        (string)($item['published_at']??$item['created_at']??''),
        $candidateMethod
      );
      if(empty($check['ok'])) return false;
      $candidate=$url;
      $method=$candidateMethod;
      $validation=$check;
      return true;
    };

    if($current!=='' && $sourceDomain!==''){
      $GLOBALS['tvs_radar_resolution_city']=$city;
      $listingHtml=tvs_fetch_url($current);
      if($listingHtml!==''){
        $tryCandidate(
          tvs_radar_find_article_in_html($sourceDomain,$listingHtml,$title),
          'queue_current_listing_title_match'
        );
      }
    }
    if($candidate==='' && $sourceDomain!==''){
      $tryCandidate(tvs_radar_find_article_on_source($sourceDomain,$title,$city),'queue_source_domain_title_match');
    }
    if($candidate==='' && $sourceDomain!==''){
      $tryCandidate(tvs_radar_resolve_by_sitemap($sourceDomain,$title,$city),'queue_source_sitemap_title_match');
    }
    if($candidate==='' && $sourceDomain!==''){
      $tryCandidate(tvs_radar_resolve_by_bing_site($sourceDomain,$title,$city),'queue_bing_site_title_match');
    }
    if($candidate==='' && $sourceDomain!==''){
      $tryCandidate(tvs_radar_resolve_by_bing_web($title,$city,$source,$sourceDomain),'queue_bing_web_title_match');
    }
    if($candidate===''){
      $tryCandidate(tvs_radar_known_current_url($title),'queue_known_current_title');
    }
    if($candidate===''){
      $tryCandidate(tvs_radar_resolve_by_bing_news($title,$city,$source),'queue_bing_news_title_match');
    }

    if($candidate===''){
      $failed++;
      $item['queue_url_repair_attempts']=(int)($item['queue_url_repair_attempts']??0)+1;
      $item['queue_url_repair_last_attempt_at']=date('c');
      $item['queue_url_repair_next_retry_at']=date('c',time()+21600);
      $item['queue_pending_reasons']=$reasons;
      $item['queue_status']='processing';
      $item['publication_eligible']=0;
      continue;
    }

    $item['source_url']=$candidate;
    $item['url']=$candidate;
    $item['url_resolution_required']=0;
    $item['url_resolution_status']='resolved';
    $item['url_resolution_method']=$method;
    $item['url_resolution_confidence']=(int)($validation['confidence']??0);
    $item['queue_url_repaired_at']=date('c');
    unset($item['queue_url_repair_next_retry_at']);
    $resolved++;
    $methods[$method]=($methods[$method]??0)+1;

    $after=function_exists('tvs_radar_queue_item_readiness')
      ? tvs_radar_queue_item_readiness($item)
      : ['ready'=>1,'reasons'=>[]];
    $item['queue_pending_reasons']=array_values(array_unique(array_filter((array)($after['reasons']??[]))));
    if(!empty($after['ready'])){
      $item['queue_status']='ready';
      $item['publication_eligible']=1;
      $item['editorial_status']='Editor IA concluído';
      $ready++;
    } else {
      $item['queue_status']='processing';
      $item['publication_eligible']=0;
    }
  }
  unset($item);

  tvs_queue_save($approval);
  $report=[
    'executed_at'=>date('c'),
    'mode'=>'queue_listing_url_repair',
    'original_titles_restored'=>$restoredTitles,
    'attempted'=>$attempted,
    'resolved'=>$resolved,
    'ready'=>$ready,
    'failed'=>$failed,
    'methods'=>$methods
  ];
  tvs_save_json_file(dirname(__DIR__).'/data/queue_listing_url_repair_status.json',$report);
  return $report;
}

function tvs_radar_force_editor_queue_pass($limit=20,$ignoreSchedule=true){
  $limit=max(1,min(40,(int)$limit));
  $approval=tvs_queue_read();
  $beforeReady=0; $beforePending=0;
  foreach($approval as $item){
    if(!is_array($item)) continue;
    $readiness=function_exists('tvs_radar_queue_item_readiness')
      ? tvs_radar_queue_item_readiness($item)
      : ['ready'=>!empty($item['publication_eligible'])];
    if(!empty($item['ai_editor_processed']) && !empty($readiness['ready'])) $beforeReady++;
    elseif(empty($item['ai_editor_processed'])) $beforePending++;
  }

  $result=tvs_radar_retry_pending_editor_articles($approval,$limit,$ignoreSchedule);
  tvs_queue_save($approval);

  $afterReady=0; $afterPending=0; $manualReview=0; $processedNotReady=0; $blockedReasons=[]; $blockedItems=[];
  foreach($approval as $item){
    if(!is_array($item)) continue;
    $readiness=function_exists('tvs_radar_queue_item_readiness')
      ? tvs_radar_queue_item_readiness($item)
      : ['ready'=>!empty($item['publication_eligible']),'reasons'=>[]];
    if(!empty($item['ai_editor_processed']) && !empty($readiness['ready'])) $afterReady++;
    elseif(empty($item['ai_editor_processed'])) $afterPending++;
    elseif(!empty($item['ai_editor_processed']) && empty($readiness['ready'])){
      $processedNotReady++;
      $sourceUrl=(string)($item['source_url']??$item['url']??'');
      $sourcePath=(string)(parse_url($sourceUrl,PHP_URL_PATH)??'');
      $blockedItems[]=[
        'id'=>(string)($item['id']??''),
        'title'=>(string)($item['title']??''),
        'source_original_title'=>(string)($item['source_original_title']??''),
        'source_host'=>(string)(parse_url($sourceUrl,PHP_URL_HOST)??''),
        'source_path'=>$sourcePath,
        'city'=>(string)($item['city']??''),
        'slug_title_score'=>tvs_radar_title_match_score((string)($item['source_original_title']??$item['title']??''),str_replace(['-','_'],' ',basename($sourcePath))),
        'reasons'=>(array)($readiness['reasons']??[])
      ];
      foreach((array)($readiness['reasons']??[]) as $reason){
        $reason=trim((string)$reason);
        if($reason!=='') $blockedReasons[$reason]=($blockedReasons[$reason]??0)+1;
      }
    }
    if(($item['ai_editor_stage']??'')==='manual_review') $manualReview++;
  }

  $report=[
    'executed_at'=>date('c'),
    'mode'=>'editor_queue_forced_pass',
    'before_ready'=>$beforeReady,
    'before_pending'=>$beforePending,
    'attempted'=>(int)($result['attempted']??0),
    'recovered'=>(int)($result['recovered']??0),
    'after_ready'=>$afterReady,
    'after_pending'=>$afterPending,
    'processed_not_ready'=>$processedNotReady,
    'blocked_reasons'=>$blockedReasons,
    'blocked_items'=>$blockedItems,
    'manual_review'=>$manualReview
  ];
  tvs_save_json_file(dirname(__DIR__).'/data/editor_queue_recovery_status.json',$report);

  $st=tvs_radar_status();
  $st=is_array($st)?$st:[];
  $st['last_editor_recovery_run']=$report['executed_at'];
  $st['last_editor_recovery_attempted']=$report['attempted'];
  $st['last_editor_recovery_recovered']=$report['recovered'];
  $st['last_editor_recovery_ready']=$report['after_ready'];
  $st['last_editor_recovery_pending']=$report['after_pending'];
  tvs_radar_save_status($st);

  return $report;
}

function tvs_radar_run_telemetry_file(){
  return dirname(__DIR__).'/data/radar_run_telemetry.json';
}

function tvs_radar_record_run_telemetry($row){
  if(!is_array($row)) $row=[];
  $file=tvs_radar_run_telemetry_file();
  $items=tvs_read_json_file($file);
  if(!is_array($items)) $items=[];
  $items[]=$row;
  if(count($items)>120) $items=array_slice($items,-120);
  tvs_save_json_file($file,array_values($items));
}

function tvs_radar_process_discovery($mode='normal',$targetPerCity=5,$options=[]){
  global $cities,$newsFile;
  $runId='radar_'.date('Ymd_His').'_'.substr(md5(uniqid('',true)),0,8);
  $runStartedAt=date('c');
  $retroactiveStage=(string)($options['retroactive_stage']??(
    !empty($options['audit_backlog'])?'pilot':'normal'
  ));
  $cursorBefore=tvs_radar_cursor_state();
  $discovery=tvs_radar_discovery_read();
  $selectedCount=!empty($options['only_ids'])
    ? count(array_values(array_unique(array_filter(array_map('strval',(array)$options['only_ids'])))))
    : count($discovery);
  $processedIds=[];

  if(!$discovery){
    $metrics=[
      'run_id'=>$runId,
      'started_at'=>$runStartedAt,
      'finished_at'=>date('c'),
      'mode'=>$mode,
      'retroactive_stage'=>$retroactiveStage,
      'selected_count'=>0,
      'processed_count'=>0,
      'changed_count'=>0,
      'unchanged_count'=>0,
      'persisted_count'=>0,
      'error_count'=>0,
      'first_item_id'=>'',
      'last_item_id'=>'',
      'cursor_before'=>$cursorBefore['city_start']??null,
      'cursor_after'=>$cursorBefore['city_start']??null,
      'rule_version'=>(string)($options['editorial_rule_version']??'')
    ];
    $GLOBALS['TVS_RADAR_LAST_PROCESS_METRICS']=$metrics;
    tvs_radar_record_run_telemetry($metrics);
    return 0;
  }

  $approval=tvs_queue_read();
  $editorRetry=!empty($options['skip_editor_retry'])
    ? ['attempted'=>0,'recovered'=>0]
    : tvs_radar_retry_pending_editor_articles($approval,tvs_radar_is_volume_mode($mode)?20:12);
  $publishedHistory=tvs_read_json_file($newsFile); if(!is_array($publishedHistory)) $publishedHistory=[];

  $auditHistory=array_merge($approval,$publishedHistory);
  if(!empty($options['audit_backlog'])) foreach($discovery as $auditIdx=>&$legacyCand){
    if(!is_array($legacyCand)) continue;
    $legacyId=(string)($legacyCand['id']??'');
    $auditOnlyIds=array_values(array_filter(array_map('strval',(array)($options['only_ids']??[]))));
    if($auditOnlyIds && !in_array($legacyId,$auditOnlyIds,true)) continue;
    if($legacyId!=='') $processedIds[]=$legacyId;
    $auditCity=(string)($legacyCand['radar_requested_city']??$legacyCand['city']??'');
    if(empty($legacyCand['entry_audit_status'])){
      $audit=tvs_radar_entry_audit($legacyCand,$auditCity,$auditHistory);
      $legacyCand['entry_audit_status']=$audit['status'];
      $legacyCand['entry_audit_reason']=$audit['reason'];
      $legacyCand['entry_audit_at']=$audit['checked_at'];
      $legacyCand['editorial_rule_version']=$legacyCand['editorial_rule_version']??$audit['rule_version'];
      $legacyCand['reprocessed_at']=date('c');
      $legacyCand['reprocess_reason']='retroactive_entry_audit';
      $legacyCand['previous_pipeline_stage']=$legacyCand['pipeline_stage']??'legacy';
      if(in_array($audit['status'],['descartada_regiao','descartada_data','duplicada','descartada_institucional'],true)){
        tvs_radar_discard($legacyCand,$auditCity,$audit['reason']);
        unset($discovery[$auditIdx]);
        continue;
      }
      if($audit['status']==='precisa_resolver_fonte') $legacyCand['pipeline_stage']='precisa_resolver_fonte';
    }
    if((int)($legacyCand['pipeline_attempts']??0)>100){
      if(tvs_radar_is_google_news_url($legacyCand['url']??'')){
        $legacyCand['source_resolution_attempts']=max(4,(int)($legacyCand['source_resolution_attempts']??0));
        $legacyCand['pipeline_stage']='aguardando_fonte';
        $legacyCand['pipeline_reason']='Backlog legado com mais de 100 tentativas: pausado para evitar loop de resolução de fonte.';
        $legacyCand['enrichment_next_retry_at']=date('c',time()+604800);
      } else {
        $legacyCand['pipeline_stage']='revisao_manual_pipeline';
        $legacyCand['pipeline_reason']='Backlog legado com mais de 100 tentativas: encaminhado para revisão manual do pipeline.';
      }
    }
  }
  if(!empty($options['audit_backlog'])) unset($legacyCand);
  $discovery=array_values($discovery);

  $ready=tvs_radar_ready_count_by_city($approval);
  $readyCategories=tvs_radar_ready_categories_by_city($approval);
  $generated=0;
  $processed=0;
  $forceRetry=!empty($options['force_retry']);
  $onlyGoogleUnresolved=!empty($options['only_google_unresolved']);
  $maxCandidates=max(0,(int)($options['max_candidates']??0));
  $onlyIds=array_values(array_filter(array_map('strval',(array)($options['only_ids']??[]))));
  $onlyLookup=$onlyIds?array_fill_keys($onlyIds,true):[];
  $ruleVersion=(string)($options['editorial_rule_version']??'');
  $reprocessReason=(string)($options['reprocess_reason']??'');
  $maxPerCycle=tvs_radar_is_volume_mode($mode)?12:6;
  if(!empty($options['max_generated'])) $maxPerCycle=max(1,(int)$options['max_generated']);
  $maxTriesPerCity=tvs_radar_is_volume_mode($mode)?8:6;
  $cursor=tvs_radar_cursor_state();
  $startCity=(int)($cursor['city_start']??0);
  $cityCount=count($cities);
  $cycleCities=$cities;
  if($cityCount>0){
    $startCity=$startCity%$cityCount;
    $cycleCities=array_merge(array_slice($cities,$startCity),array_slice($cities,0,$startCity));
  }

  foreach($cycleCities as $city){
    if($generated>=$maxPerCycle) break;
    if(($ready[$city]??0)>=$targetPerCity && !$forceRetry) continue;

    $cityCandidates=[];
    foreach($discovery as $idx=>$cand){
      $requested=(string)($cand['radar_requested_city']??$cand['city']??'');
      if($requested!==$city) continue;
      // Proteção anti-loop: pautas com histórico extremo de tentativas não podem
      // continuar consumindo o ciclo normal indefinidamente.
      if((int)($cand['pipeline_attempts']??0)>100){
        if(tvs_radar_is_google_news_url($cand['url']??$cand['source_url']??'')){
          $cand['pipeline_stage']='aguardando_fonte';
          $cand['pipeline_reason']='Limite global de tentativas do pipeline excedido; fonte pendente preservada para diagnóstico/resolução específica.';
          $cand['enrichment_next_retry_at']=date('c',time()+604800);
        } else {
          $cand['pipeline_stage']='revisao_manual_pipeline';
          $cand['pipeline_reason']='Limite global de tentativas do pipeline excedido; removida do ciclo automático para interromper repetição sem avanço.';
          $cand['enrichment_next_retry_at']='';
        }
        $discovery[$idx]=$cand;
        continue;
      }
      // Régua 1.3: pauta sem auditoria de entrada não entra no ciclo normal.
      if(empty($cand['entry_audit_status']) && empty($options['audit_backlog'])) continue;

      $candidateStage=(string)($cand['pipeline_stage']??'');
      if(in_array($candidateStage,['fonte_esgotada','revisao_manual_pipeline','expirada_sem_enriquecimento'],true)) continue;

      // Barreira pré-enriquecimento: um Google News ainda não resolvido que já
      // passou por seis ciclos não pode chegar novamente ao PIPELINE_TRY.
      $candidateUrl=trim((string)($cand['url']??$cand['source_url']??''));
      if(tvs_radar_is_google_news_url($candidateUrl) && (int)($cand['pipeline_attempts']??0)>=6){
        $cand['pipeline_stage']='fonte_esgotada';
        $cand['pipeline_reason']='Fonte original esgotou seis ciclos de resolução; removida do processamento automático antes do enriquecimento.';
        $cand['enrichment_next_retry_at']='';
        $cand['pipeline_updated_at']=date('c');
        $discovery[$idx]=$cand;
        continue;
      }

      if($candidateStage==='aguardando_fonte' && !$forceRetry) continue;
      if($reprocessReason!=='' && ($cand['reprocess_reason']??'')===$reprocessReason && ($cand['editorial_rule_version']??'')===$ruleVersion) continue;
      if($onlyGoogleUnresolved && !tvs_radar_is_google_news_url($cand['url']??'')) continue;
      $candId=(string)($cand['id']??'');
      if($onlyLookup && !isset($onlyLookup[$candId])) continue;
      if(!$forceRetry && !tvs_radar_enrichment_due($cand)) continue;

      $category=trim((string)($cand['category']??$cand['radar_pre_category']??''));
      if($category==='') $category=tvs_radar_candidate_category($cand);

      $used=(int)($readyCategories[$city][$category]??0);
      $preferredCap=tvs_radar_final_category_cap($category,$targetPerCity);
      $diversityPenalty=max(0,$used-$preferredCap+1);

      $cityCandidates[]=[
        'idx'=>$idx,
        'category'=>$category,
        'diversity_penalty'=>$diversityPenalty,
        'resolution_penalty'=>(int)($cand['resolution_priority_penalty']??0),
        'sf_score'=>(int)($cand['sf_score']??0),
        'attempts'=>(int)($cand['pipeline_attempts']??0),
        'updated'=>(string)($cand['pipeline_updated_at']??$cand['pipeline_created_at']??'')
      ];
    }

    usort($cityCandidates,function($a,$b){
      if($a['diversity_penalty']!==$b['diversity_penalty']) return $a['diversity_penalty']<=>$b['diversity_penalty'];
      if($a['resolution_penalty']!==$b['resolution_penalty']) return $a['resolution_penalty']<=>$b['resolution_penalty'];
      if($a['sf_score']!==$b['sf_score']) return $b['sf_score']<=>$a['sf_score'];
      if($a['attempts']!==$b['attempts']) return $a['attempts']<=>$b['attempts'];
      return strcmp($a['updated'],$b['updated']);
    });

    $tries=0;
    foreach($cityCandidates as $meta){
      if($generated>=$maxPerCycle) break 2;
      if($maxCandidates>0 && $processed>=$maxCandidates) break 2;
      if($tries>=$maxTriesPerCity) break;
      $pick=$meta['idx'];
      if(!isset($discovery[$pick])) continue;

      $tries++;
      $processed++;
      $cand=$discovery[$pick];
      $processedIds[]=(string)($cand['id']??'');
      $previousStage=(string)($cand['pipeline_stage']??'');
      $cand['pipeline_attempts']=(int)($cand['pipeline_attempts']??0)+1;
      $cand['pipeline_updated_at']=date('c');
      if($ruleVersion!=='') $cand['editorial_rule_version']=$ruleVersion;
      if($reprocessReason!==''){
        $cand['reprocessed_at']=date('c');
        $cand['reprocess_reason']=$reprocessReason;
        $cand['previous_pipeline_stage']=$previousStage;
      }

      [$cand,$mat,$package]=tvs_radar_enrich_candidate($cand,$city);
      $discovery[$pick]=$cand;

      $sf=(int)($package['sf_score']??0);
      $coreOk=!empty($package['core_4w_ok']);
      $sourceWords=tvs_radar_word_count($mat['text']??'');

      if(PHP_SAPI==='cli'){
        echo "PIPELINE_TRY city=".str_replace(' ','_',$city)
          ." source=".(tvs_radar_is_google_news_url($cand['url']??'')?'google':'original')
          ." words={$sourceWords}"
          ." sf={$sf}"
          ." core=".($coreOk?'ok':'missing')
          ." attempts=".(int)$cand['pipeline_attempts']
          ." title=".substr(preg_replace('/\s+/u',' ',(string)($cand['title']??'')),0,120)."\n";
      }

      if(tvs_radar_is_google_news_url($cand['url']??'')){
        $resolutionStatus=(string)($cand['url_resolution_status']??'unresolved_retriable');
        if($resolutionStatus==='unresolved_final'){
          $cand['pipeline_stage']='aguardando_fonte';
          $cand['pipeline_reason']='Fonte original não resolvida dentro do TTL; estado final preservado para auditoria.';
          $cand['enrichment_next_retry_at']=date('c',time()+604800);
          $discovery[$pick]=$cand;
          tvs_radar_log_event($cand['title']??'',$cand['source']??'Fonte',$city,'AGUARDANDO_FONTE',$cand['pipeline_reason'],$cand['url']??'');
          continue;
        }
        $factText=tvs_radar_fact_text($cand);
        $detectedAllowed=tvs_radar_detect_city_from_text($factText,'');
        if($detectedAllowed!=='' && in_array($detectedAllowed,tvs_radar_allowed_cities(),true) && $detectedAllowed!==$city){
          $cand['city']=$detectedAllowed;
          $cand['radar_requested_city']=$detectedAllowed;
          $cand['pipeline_stage']='aguardando_enriquecimento';
          $cand['pipeline_reason']='Pauta regional reclassificada para '.$detectedAllowed.' antes da resolução da fonte original.';
          $cand['enrichment_next_retry_at']=date('c',time()+300);
          $discovery[$pick]=$cand;
          if(PHP_SAPI==='cli'){
            echo "PIPELINE_RECLASSIFY from=".str_replace(' ','_',$city)
              ." to=".str_replace(' ','_',$detectedAllowed)
              ." title=".substr(preg_replace('/\\s+/u',' ',(string)($cand['title']??'')),0,120)."\n";
          }
          continue;
        }
        if($detectedAllowed==='' && tvs_radar_has_outside_city_signal($factText)){
          tvs_radar_discard($cand,$city,'Pauta do agregador pertence a cidade fora da região monitorada.');
          unset($discovery[$pick]);
          if(PHP_SAPI==='cli'){
            echo "PIPELINE_DROP_OUTSIDE city=".str_replace(' ','_',$city)
              ." title=".substr(preg_replace('/\\s+/u',' ',(string)($cand['title']??'')),0,120)."\n";
          }
          continue;
        }
        $cand['pipeline_stage']='aguardando_fonte';
        $cand['pipeline_reason']='Pauta regional válida, mas a URL original ainda não foi resolvida. Snippet não será usado como matéria.';
        $cand['enrichment_next_retry_at']=(string)($cand['url_resolution_next_attempt_at']??date('c',time()+3600));
        if(($cand['pipeline_stage']??'')==='expirada_sem_enriquecimento'){
          tvs_radar_discard($cand,$city,'TTL de enriquecimento expirado após 7 dias sem fonte original resolvida.');
          unset($discovery[$pick]);
        } else {
          if($reprocessReason!=='') $cand['new_pipeline_stage']=(string)($cand['pipeline_stage']??'');
          $discovery[$pick]=$cand;
        }
        continue;
      }

      $decision=tvs_radar_factually_ready($package);
      $sourceResolved=!empty($decision['source_original_resolved']);
      $trustedSource=!empty($decision['trusted_source']);
      $freshnessOk=!empty($decision['freshness_ok']);
      $factuallyReady=!empty($decision['ready']);

      if(!$factuallyReady){
        $contentUsable=!empty($decision['content_usable']);
        $stageField='factual_gate_attempts';
        $stageMax=3;
        $stageWait='aguardando_enriquecimento';
        if(!$sourceResolved){
          $stageField='source_resolution_attempts'; $stageMax=6; $stageWait='aguardando_fonte';
        } elseif($sf>=40 && $sf<60){
          $stageField='second_source_attempts'; $stageMax=3; $stageWait='aguardando_enriquecimento';
        } elseif($sf>=60 && (!$coreOk || !$trustedSource)){
          $stageField='second_source_attempts'; $stageMax=3; $stageWait='aguardando_enriquecimento';
        } elseif(!$contentUsable){
          // Conteúdo útil é sinal diagnóstico, nunca corte por quantidade de palavras.
          $stageField='extraction_attempts'; $stageMax=3; $stageWait='aguardando_enriquecimento';
        }
        $cand[$stageField]=(int)($cand[$stageField]??0)+1;
        if($cand[$stageField]>=$stageMax){
          $cand['pipeline_stage']=$stageWait;
          $cand['pipeline_reason']='Limite da etapa '.$stageField.' atingido ('.$stageMax.'); pauta retirada da seleção normal e mantida para reavaliação auditável.';
          $cand['enrichment_next_retry_at']=date('c',time()+604800);
          $discovery[$pick]=$cand;
          tvs_radar_log_event($cand['title']??'',$cand['source']??'Fonte',$city,strtoupper($stageWait),$cand['pipeline_reason'],$cand['url']??'');
          continue;
        }

        $editorialInterest=!empty($decision['editorial_interest']);
        $gateReason='Pacote factual ainda insuficiente: SF '.$sf.'/100; 4W básico '.($coreOk?'completo':'incompleto')
          .'; fonte original '.($sourceResolved?'resolvida':'não resolvida')
          .'; fonte confiável '.($trustedSource?'sim':'não')
          .'; atualidade '.($freshnessOk?'ok':'fora da janela')
          .'; conteúdo útil '.($contentUsable?'sim':'não').' ('.$sourceWords.' palavras)'
          .'; interesse editorial '.($editorialInterest?'sim':'não').'.';
        $cand['fact_gate_audit']=[
          'checked_at'=>date('c'),
          'sf_score'=>$sf,
          'source_words'=>$sourceWords,
          'core_4w_ok'=>$coreOk?1:0,
          'source_original_resolved'=>$sourceResolved?1:0,
          'trusted_source'=>$trustedSource?1:0,
          'freshness_ok'=>$freshnessOk?1:0,
          'content_usable'=>$contentUsable?1:0,
          'editorial_interest'=>$editorialInterest?1:0
        ];
        if(PHP_SAPI==='cli'){
          echo "FACT_GATE_BLOCK city=".str_replace(' ','_',$city)
            ." sf={$sf} words={$sourceWords}"
            ." core=".($coreOk?'ok':'missing')
            ." resolved=".($sourceResolved?'yes':'no')
            ." trusted=".($trustedSource?'yes':'no')
            ." fresh=".($freshnessOk?'yes':'no')
            ." usable=".($contentUsable?'yes':'no')
            ." editorial=".($editorialInterest?'yes':'no')
            ." title=".substr(preg_replace('/\\s+/u',' ',(string)($cand['title']??'')),0,120)."\n";
        }
        tvs_radar_schedule_enrichment(
          $cand,
          $gateReason
        );
        if(($cand['pipeline_stage']??'')==='expirada_sem_enriquecimento'){
          tvs_radar_discard($cand,$city,'TTL de enriquecimento expirado após 7 dias sem pacote factual suficiente.');
          unset($discovery[$pick]);
        } else {
          if($reprocessReason!=='') $cand['new_pipeline_stage']=(string)($cand['pipeline_stage']??'');
          $discovery[$pick]=$cand;
          tvs_radar_log_event(
            $cand['title']??'',
            $cand['source']??'Fonte',
            $city,
            strtoupper((string)$cand['pipeline_stage']),
            $cand['pipeline_reason'],
            $cand['url']??''
          );
        }
        continue;
      }

      $cand['pipeline_stage']='pronta_para_redacao';
      $cand['pipeline_reason']='Pacote factual aprovado: SF '.$sf.'/100.';
      $cand['fact_package']=$package;
      $cand['sf_score']=$sf;
      if($reprocessReason!=='') $cand['new_pipeline_stage']='pronta_para_redacao';

      $article=tvs_generate_ready_article($city,$cand);
      if(is_array($article) && !empty($article['title']) && !empty($article['body'])){
        $articleCategory=trim((string)($article['category']??$cand['category']??'Cidade'));
        if($articleCategory==='') $articleCategory='Cidade';

        $article['sf_score']=$sf;
        $article['source_candidate_id']=(string)($cand['id']??'');
        $article['fact_package']=$package;
        $article['provenance_sources']=$package['sources']??[];
        if($ruleVersion!=='') $article['editorial_rule_version']=$ruleVersion;
        if($reprocessReason!==''){
          $article['reprocessed_at']=date('c');
          $article['reprocess_reason']=$reprocessReason;
          $article['previous_pipeline_stage']=$previousStage;
          $article['new_pipeline_stage']='fila_humana';
        }
        $article['diversity_overrepresented']=tvs_radar_category_room($city,$articleCategory,$readyCategories,$targetPerCity)?0:1;

        if(tvs_radar_history_duplicate($article,array_merge($approval,$publishedHistory))){
          tvs_radar_log_event(
            $article['title']??($cand['title']??''),
            $article['source']??($cand['source']??'Fonte'),
            $city,
            'DESCARTADA',
            'Duplicata de pauta já aprovada/publicada',
            $article['source_url']??($cand['url']??'')
          );
          unset($discovery[$pick]);
          continue;
        }

        $articleReadiness=function_exists('tvs_radar_queue_item_readiness')
          ? tvs_radar_queue_item_readiness($article)
          : ['ready'=>!empty($article['ai_editor_processed'])];

        if(empty($article['ai_editor_processed']) || empty($articleReadiness['ready'])){
          $pendingReasons=(array)($articleReadiness['reasons']??[]);
          if(empty($article['ai_editor_processed'])) $pendingReasons[]='Editor IA ainda não concluído';
          $pendingReasons=array_values(array_unique(array_filter($pendingReasons)));

          $article['queue_status']='processing';
          $article['queue_pending_reasons']=$pendingReasons;
          $article['publication_eligible']=0;
          $article['ai_editor_stage']='pending';
          $article['editorial_status']='Aguardando Editor IA';
          $article['ai_editor_attempts']=(int)($article['ai_editor_attempts']??0)+1;
          $article['ai_editor_last_attempt_at']=date('c');
          $approval[]=$article;
          unset($discovery[$pick]);
          tvs_radar_log_event($article['title']??($cand['title']??''),$article['source']??($cand['source']??'Fonte'),$city,'PROCESSAMENTO',$pendingReasons ? implode('; ',$pendingReasons) : 'Aguardando conclusão e validação do Editor IA.',$cand['url']??'');
          continue;
        }

        $article['queue_status']='ready';
        $article['queue_pending_reasons']=[];
        $approval[]=$article;
        unset($discovery[$pick]);
        $generated++;
        $ready[$city]=($ready[$city]??0)+1;
        $readyCategories[$city][$articleCategory]=($readyCategories[$city][$articleCategory]??0)+1;
        tvs_radar_log_event(
          $article['title']??'',
          $article['source']??($cand['source']??'Fonte'),
          $city,
          ($article['editorial_status']??'REVISÃO'),
          'Repórter IA + Editor IA concluídos e conteúdo validado; SF '.$sf.'/100; diversidade aplicada como preferência.',
          $cand['url']??''
        );
        break;
      }

      // Se a própria geração já classificou a pauta como descarte terminal,
      // não a reinsere em enriquecimento. Isso evitava "descartar e tentar de novo"
      // no mesmo item, inflando backlog e telemetria.
      $discardedNow=tvs_read_json_file(dirname(__DIR__).'/data/pautas_descartadas.json');
      $wasDiscarded=false;
      if(is_array($discardedNow)){
        $candidateId=(string)($cand['id']??'');
        for($di=count($discardedNow)-1;$di>=0;$di--){
          $dr=$discardedNow[$di]??null;
          if(!is_array($dr)) continue;
          if((string)($dr['original_id']??'')===$candidateId){
            $wasDiscarded=true;
            break;
          }
        }
      }
      if($wasDiscarded){
        unset($discovery[$pick]);
        continue;
      }

      $cand['reporter_generation_attempts']=(int)($cand['reporter_generation_attempts']??0)+1;
      $cand['reporter_generation_last_attempt_at']=date('c');

      if($cand['reporter_generation_attempts']>=3){
        $cand['pipeline_stage']='revisao_manual_pipeline';
        $cand['pipeline_reason']='Pacote factual aprovado, mas Repórter/Editor IA não concluiu matéria segura após 3 tentativas. Encaminhada para revisão manual do pipeline.';
        $cand['enrichment_next_retry_at']='';
        tvs_radar_log_event(
          $cand['title']??'',
          $cand['source']??'Fonte',
          $city,
          'REVISAO_MANUAL_PIPELINE',
          $cand['pipeline_reason'],
          $cand['url']??''
        );
      } else {
        $cand['pipeline_stage']='aguardando_enriquecimento';
        $cand['pipeline_reason']='Pacote factual aprovado, mas a redação/editoria não concluiu uma matéria segura neste ciclo. Nova tentativa controlada do Repórter/Editor IA.';
        $cand['enrichment_next_retry_at']=date('c',time()+1800);
      }

      if($reprocessReason!=='') $cand['new_pipeline_stage']=(string)($cand['pipeline_stage']??'');
      $discovery[$pick]=$cand;
    }
  }

  tvs_queue_save($approval);
  tvs_radar_discovery_save(array_values($discovery));
  if(!empty($cities)){
    $cursor['city_start']=($startCity+1)%count($cities);
    $cursor['updated_at']=date('c');
    tvs_radar_cursor_save($cursor);
  }
  tvs_radar_enforce_queue_rules(true);

  $processedIds=array_values(array_unique(array_filter($processedIds,static fn($id)=>$id!=='')));
  $persistedRows=array_merge(
    tvs_radar_discovery_read(),
    tvs_queue_read(),
    (array)tvs_read_json_file(dirname(__DIR__).'/data/pautas_descartadas.json')
  );
  $persistedCount=0;
  $changedCount=0;
  foreach($processedIds as $processedId){
    $matched=null;
    foreach($persistedRows as $row){
      if(!is_array($row)) continue;
      if(
        (string)($row['id']??'')===$processedId
        || (string)($row['source_candidate_id']??'')===$processedId
        || (string)($row['original_id']??'')===$processedId
      ){
        $matched=$row;
        break;
      }
    }
    if(!$matched) continue;
    $persistedCount++;
    $before=(string)($matched['previous_pipeline_stage']??'');
    $after=(string)($matched['new_pipeline_stage']??$matched['pipeline_stage']??'');
    if(
      (string)($matched['source_candidate_id']??'')===$processedId
      || (string)($matched['original_id']??'')===$processedId
      || ($before!=='' && $after!=='' && $before!==$after)
    ){
      $changedCount++;
    }
  }
  $cursorAfter=tvs_radar_cursor_state();
  $metrics=[
    'run_id'=>$runId,
    'started_at'=>$runStartedAt,
    'finished_at'=>date('c'),
    'mode'=>$mode,
    'retroactive_stage'=>$retroactiveStage,
    'selected_count'=>$selectedCount,
    'processed_count'=>count($processedIds),
    'changed_count'=>$changedCount,
    'unchanged_count'=>max(0,count($processedIds)-$changedCount),
    'persisted_count'=>$persistedCount,
    'error_count'=>max(0,count($processedIds)-$persistedCount),
    'first_item_id'=>$processedIds[0]??'',
    'last_item_id'=>$processedIds?end($processedIds):'',
    'cursor_before'=>$cursorBefore['city_start']??null,
    'cursor_after'=>$cursorAfter['city_start']??null,
    'rule_version'=>(string)($options['editorial_rule_version']??'')
  ];
  $GLOBALS['TVS_RADAR_LAST_PROCESS_METRICS']=$metrics;
  tvs_radar_record_run_telemetry($metrics);
  return $generated;
}

function tvs_radar_select_backlog_pilot_ids($limit=10){
  global $cities;
  $discovery=tvs_radar_discovery_read();
  $byCity=[]; foreach($cities as $city) $byCity[$city]=[];
  foreach($discovery as $cand){
    if(!is_array($cand) || !empty($cand['entry_audit_status'])) continue;
    $city=(string)($cand['radar_requested_city']??$cand['city']??'');
    if(!isset($byCity[$city])) continue;
    $byCity[$city][]=$cand;
  }
  foreach($byCity as &$items){
    usort($items,function($a,$b){
      $aa=(int)($a['pipeline_attempts']??0); $bb=(int)($b['pipeline_attempts']??0);
      if(($aa>100)!==($bb>100)) return ($aa>100)?-1:1;
      if($aa!==$bb) return $bb<=>$aa;
      return strcmp((string)($a['pipeline_updated_at']??''),(string)($b['pipeline_updated_at']??''));
    });
  }
  unset($items);
  $ids=[];
  while(count($ids)<$limit){
    $progress=false;
    foreach($cities as $city){
      if(count($ids)>=$limit) break;
      if(empty($byCity[$city])) continue;
      $row=array_shift($byCity[$city]);
      if(!empty($row['id'])) $ids[]=(string)$row['id'];
      $progress=true;
    }
    if(!$progress) break;
  }
  return $ids;
}

function tvs_radar_run_backlog_pilot_v12($limit=10){
  $ids=tvs_radar_select_backlog_pilot_ids($limit);
  if(!$ids){
    return [
      'selected'=>0,'generated'=>0,'ids'=>[],
      'processed'=>0,'persisted'=>0,'persistence_failures'=>0,
      'skipped_state_transition'=>0,'hard_rule_violations'=>0,
      'auto_published'=>0,'pilot_status'=>'sem_candidatos'
    ];
  }

  $generated=tvs_radar_process_discovery('normal',5,[
    'force_retry'=>true,
    'max_candidates'=>count($ids),
    'max_generated'=>count($ids),
    'only_ids'=>$ids,
    'editorial_rule_version'=>'1.2',
    'reprocess_reason'=>'retroactive_rule_upgrade_pilot',
    'retroactive_stage'=>'pilot',
    'audit_backlog'=>true,
    'skip_editor_retry'=>true
  ]);

  $metrics=(array)($GLOBALS['TVS_RADAR_LAST_PROCESS_METRICS']??[]);
  $processed=(int)($metrics['processed_count']??0);
  $persisted=(int)($metrics['persisted_count']??0);
  $persistenceFailures=max(0,count($ids)-$persisted);
  $idLookup=array_fill_keys($ids,true);
  $skippedState=0;
  $hardRuleViolations=0;

  foreach(tvs_queue_read() as $row){
    $sourceCandidateId=(string)($row['source_candidate_id']??'');
    if($sourceCandidateId==='' || !isset($idLookup[$sourceCandidateId])) continue;

    $package=(array)($row['fact_package']??[]);
    $sourceUrl=trim((string)($row['source_url']??''));
    if(
      empty($package['source_original_resolved'])
      || empty($package['core_4w_ok'])
      || $sourceUrl===''
      || tvs_radar_is_google_news_url($sourceUrl)
    ){
      $skippedState++;
    }

    $city=(string)($row['city']??'');
    if(!in_array($city,tvs_radar_allowed_cities(),true)) $hardRuleViolations++;
    if($sourceUrl==='' || tvs_radar_is_google_news_url($sourceUrl)) $hardRuleViolations++;
  }

  // Este fluxo nunca chama publicação; a checagem fica explícita no contrato do piloto.
  $autoPublished=0;
  $approved=(
    count($ids)>0
    && $processed===count($ids)
    && $skippedState===0
    && $hardRuleViolations===0
    && $autoPublished===0
    && $persistenceFailures===0
  );

  $result=[
    'selected'=>count($ids),
    'generated'=>$generated,
    'ids'=>$ids,
    'processed'=>$processed,
    'persisted'=>$persisted,
    'persistence_failures'=>$persistenceFailures,
    'skipped_state_transition'=>$skippedState,
    'hard_rule_violations'=>$hardRuleViolations,
    'auto_published'=>$autoPublished,
    'pilot_status'=>$approved?'aprovado':'bloqueado'
  ];

  $gatePayload=array_merge([
    'generated_at'=>date('c'),
    'rule_version'=>'1.2',
    'retroactive_stage'=>'pilot',
    'run_id'=>(string)($metrics['run_id']??('pilot_'.date('Ymd_His'))),
    'started_at'=>(string)($metrics['started_at']??date('c')),
    'finished_at'=>date('c'),
    'mode'=>'controlled_pilot',
    'selected_count'=>count($ids),
    'processed_count'=>$processed,
    'persisted_count'=>$persisted,
    'changed_count'=>(int)($metrics['changed_count']??0),
    'unchanged_count'=>(int)($metrics['unchanged_count']??0),
    'error_count'=>(int)($metrics['error_count']??0),
    'cursor_before'=>$metrics['cursor_before']??null,
    'cursor_after'=>$metrics['cursor_after']??null
  ],$result);

  tvs_save_json_file(dirname(__DIR__).'/data/radar_backlog_pilot_v12.json',$gatePayload);
  if($approved){
    tvs_save_json_file(dirname(__DIR__).'/data/radar_backlog_gate_v12.json',$gatePayload);
  }
  // Persiste também o veredito completo na telemetria usada pelo painel.
  tvs_radar_record_run_telemetry($gatePayload);

  return $result;
}

function tvs_radar_pilot_payload_approved_v12($pilot){
  if(!is_array($pilot)) return false;
  return (string)($pilot['pilot_status']??'')==='aprovado'
    && (int)($pilot['selected']??0)>0
    && (int)($pilot['processed']??0)===(int)($pilot['selected']??0)
    && (int)($pilot['persisted']??0)===(int)($pilot['selected']??0)
    && (int)($pilot['persistence_failures']??0)===0
    && (int)($pilot['skipped_state_transition']??0)===0
    && (int)($pilot['hard_rule_violations']??0)===0
    && (int)($pilot['auto_published']??0)===0;
}

function tvs_radar_last_approved_pilot_v12(){
  $files=[
    dirname(__DIR__).'/data/radar_backlog_gate_v12.json',
    dirname(__DIR__).'/data/radar_backlog_pilot_v12.json'
  ];
  foreach($files as $file){
    $pilot=tvs_read_json_file($file);
    if(tvs_radar_pilot_payload_approved_v12($pilot)) return $pilot;
  }

  // Fallback canônico: usa a mesma telemetria persistida que alimenta o painel.
  // Isso evita bloquear o lote quando um marcador isolado fica obsoleto.
  $telemetry=tvs_read_json_file(tvs_radar_run_telemetry_file());
  if(is_array($telemetry)){
    for($i=count($telemetry)-1;$i>=0;$i--){
      $row=$telemetry[$i]??null;
      if(!is_array($row)) continue;
      if((string)($row['retroactive_stage']??'')!=='pilot') continue;
      if(tvs_radar_pilot_payload_approved_v12($row)) return $row;
    }
  }
  return null;
}

function tvs_radar_pilot_allows_backlog_v12(){
  return is_array(tvs_radar_last_approved_pilot_v12());
}

function tvs_radar_backlog_candidate_eligible_v13($cand){
  if(!is_array($cand)) return false;
  if((int)($cand['pipeline_attempts']??0)>100) return false;

  $audit=(string)($cand['entry_audit_status']??'');
  if(!in_array($audit,['aprovada_para_enriquecimento','precisa_resolver_fonte'],true)) return false;

  $stage=(string)($cand['pipeline_stage']??'pauta_encontrada');
  if(in_array($stage,[
    'pronta_para_redacao','revisao_manual_pipeline','expirada_sem_enriquecimento'
  ],true)) return false;

  if(!tvs_radar_enrichment_due($cand)) return false;

  $isGoogle=tvs_radar_is_google_news_url($cand['url']??$cand['source_url']??'');
  // O resolvedor só considera a fonte definitivamente esgotada na 6ª tentativa.
  // O seletor do backlog precisa usar o mesmo limite para não abandonar a pauta
  // duas tentativas antes de ela atingir o estado final auditável.
  if($isGoogle && (int)($cand['source_resolution_attempts']??0)>=6) return false;

  $package=(array)($cand['fact_package']??[]);
  if($package){
    $decision=tvs_radar_factually_ready($package);
    if(!empty($decision['ready'])) return true;

    $sf=(int)($decision['sf']??0);
    $sourceResolved=!empty($decision['source_original_resolved']);
    $coreOk=!empty($decision['core_4w_ok']);
    $trusted=!empty($decision['trusted_source']);
    $usable=!empty($decision['content_usable']);

    if(!$sourceResolved) return (int)($cand['source_resolution_attempts']??0)<6;
    if(($sf>=40 && $sf<60) || ($sf>=60 && (!$coreOk || !$trusted))){
      return (int)($cand['second_source_attempts']??0)<3;
    }
    if(!$usable) return (int)($cand['extraction_attempts']??0)<3;
    return (int)($cand['factual_gate_attempts']??0)<3;
  }

  return true;
}

function tvs_radar_select_backlog_ids_v13($limit=20){
  global $cities;
  $limit=max(1,min(100,(int)$limit));
  $discovery=tvs_radar_discovery_read();
  $byCity=[]; foreach($cities as $city) $byCity[$city]=[];

  foreach($discovery as $cand){
    if(!tvs_radar_backlog_candidate_eligible_v13($cand)) continue;
    $city=(string)($cand['radar_requested_city']??$cand['city']??'');
    if(!isset($byCity[$city])) continue;

    $isGoogle=tvs_radar_is_google_news_url($cand['url']??$cand['source_url']??'');
    $package=(array)($cand['fact_package']??[]);
    $resolved=!empty($package['source_original_resolved']) || !$isGoogle;
    $byCity[$city][]=[
      'id'=>(string)($cand['id']??''),
      'resolved'=>$resolved?1:0,
      'sf'=>(int)($cand['sf_score']??($package['sf_score']??0)),
      'attempts'=>(int)($cand['pipeline_attempts']??0),
      'updated'=>(string)($cand['pipeline_updated_at']??$cand['pipeline_created_at']??'')
    ];
  }

  foreach($byCity as &$items){
    usort($items,function($a,$b){
      if($a['resolved']!==$b['resolved']) return $b['resolved']<=>$a['resolved'];
      if($a['sf']!==$b['sf']) return $b['sf']<=>$a['sf'];
      if($a['attempts']!==$b['attempts']) return $a['attempts']<=>$b['attempts'];
      return strcmp($a['updated'],$b['updated']);
    });
  }
  unset($items);

  $ids=[];
  while(count($ids)<$limit){
    $progress=false;
    foreach($cities as $city){
      if(count($ids)>=$limit) break;
      if(empty($byCity[$city])) continue;
      $row=array_shift($byCity[$city]);
      if(!empty($row['id'])) $ids[]=$row['id'];
      $progress=true;
    }
    if(!$progress) break;
  }
  return $ids;
}

function tvs_radar_run_backlog_batch_v13($limit=20){
  $ids=tvs_radar_select_backlog_ids_v13($limit);
  if(!$ids){
    $result=[
      'allowed'=>1,'selected'=>0,'processed'=>0,'persisted'=>0,
      'generated'=>0,'advanced'=>0,'stalled'=>0,'errors'=>0,
      'status'=>'sem_pautas_elegiveis','reasons'=>[]
    ];
    tvs_radar_record_run_telemetry([
      'run_id'=>'backlog_v13_'.date('Ymd_His'),
      'started_at'=>date('c'),'finished_at'=>date('c'),
      'mode'=>'controlled_backlog','retroactive_stage'=>'backlog_v13',
      'selected_count'=>0,'processed_count'=>0,'persisted_count'=>0,
      'changed_count'=>0,'unchanged_count'=>0,'error_count'=>0,
      'advanced_count'=>0,'stalled_count'=>0,'rule_version'=>'1.3'
    ]);
    return $result;
  }

  $beforeRows=tvs_radar_discovery_read();
  $before=[];
  foreach($beforeRows as $row){
    $id=(string)($row['id']??'');
    if($id!=='' && in_array($id,$ids,true)){
      $before[$id]=[
        'stage'=>(string)($row['pipeline_stage']??'pauta_encontrada'),
        'attempts'=>(int)($row['pipeline_attempts']??0)
      ];
    }
  }

  $batchToken='backlog_v13_'.date('Ymd_His').'_'.substr(md5(uniqid('',true)),0,8);
  $generated=tvs_radar_process_discovery('normal',5,[
    'force_retry'=>true,
    'max_candidates'=>count($ids),
    'max_generated'=>count($ids),
    'only_ids'=>$ids,
    'editorial_rule_version'=>'1.3',
    'reprocess_reason'=>$batchToken,
    'retroactive_stage'=>'backlog_v13',
    'audit_backlog'=>false,
    'skip_editor_retry'=>true
  ]);

  $metrics=(array)($GLOBALS['TVS_RADAR_LAST_PROCESS_METRICS']??[]);
  $afterRows=tvs_radar_discovery_read();
  $after=[];
  foreach($afterRows as $row){
    $id=(string)($row['id']??'');
    if($id!=='') $after[$id]=$row;
  }

  $queueIds=[];
  foreach(tvs_queue_read() as $row){
    $id=(string)($row['source_candidate_id']??'');
    if($id!=='') $queueIds[$id]=true;
  }
  $discardedIds=[];
  $discarded=tvs_read_json_file(dirname(__DIR__).'/data/pautas_descartadas.json');
  if(is_array($discarded)) foreach($discarded as $row){
    $id=(string)($row['original_id']??'');
    if($id!=='') $discardedIds[$id]=true;
  }

  $advanced=0; $stalled=0; $reasons=[];
  foreach($ids as $id){
    if(isset($queueIds[$id])){
      $advanced++;
      $reasons['fila_editorial']=($reasons['fila_editorial']??0)+1;
      continue;
    }
    if(isset($discardedIds[$id])){
      $advanced++;
      $reasons['descartada_com_motivo']=($reasons['descartada_com_motivo']??0)+1;
      continue;
    }
    if(isset($after[$id])){
      $newStage=(string)($after[$id]['pipeline_stage']??'pauta_encontrada');
      $oldStage=(string)($before[$id]['stage']??'pauta_encontrada');
      if($newStage!==$oldStage){
        $advanced++;
        $reasons[$newStage]=($reasons[$newStage]??0)+1;
      } else {
        $stalled++;
        $reason=trim((string)($after[$id]['pipeline_reason']??'sem_avanco_de_estado'));
        if($reason==='') $reason='sem_avanco_de_estado';
        $reason=mb_substr($reason,0,140,'UTF-8');
        $reasons[$reason]=($reasons[$reason]??0)+1;
      }
      continue;
    }
    $stalled++;
    $reasons['estado_nao_localizado']=($reasons['estado_nao_localizado']??0)+1;
  }

  // A telemetria do lote deve refletir todos os IDs selecionados que receberam
  // um desfecho observável (fila, descarte ou estado persistido), não apenas os IDs
  // que passaram pelo contador interno do process_discovery.
  $selectedCount=count($ids);
  $handledCount=$advanced+$stalled;
  $processed=$handledCount;
  $persisted=$handledCount;
  $errors=max(0,$selectedCount-$handledCount);
  $result=[
    'allowed'=>1,
    'selected'=>$selectedCount,
    'processed'=>$processed,
    'persisted'=>$persisted,
    'generated'=>$generated,
    'advanced'=>$advanced,
    'stalled'=>$stalled,
    'errors'=>$errors,
    'first_item_id'=>(string)($metrics['first_item_id']??''),
    'last_item_id'=>(string)($metrics['last_item_id']??''),
    'status'=>($processed===count($ids) && $errors===0 && $persisted===count($ids))
      ? 'lote_concluido' : 'lote_parcial',
    'reasons'=>$reasons
  ];

  $stateFile=dirname(__DIR__).'/data/radar_backlog_batches_v13.json';
  $state=tvs_read_json_file($stateFile);
  if(!is_array($state)) $state=[];
  $state[]=['executed_at'=>date('c'),'rule_version'=>'1.3','batch_token'=>$batchToken]+$result;
  if(count($state)>120) $state=array_slice($state,-120);
  tvs_save_json_file($stateFile,array_values($state));

  tvs_radar_record_run_telemetry([
    'run_id'=>$batchToken,
    'started_at'=>(string)($metrics['started_at']??date('c')),
    'finished_at'=>date('c'),
    'mode'=>'controlled_backlog',
    'retroactive_stage'=>'backlog_v13',
    'selected_count'=>count($ids),
    'processed_count'=>$processed,
    'persisted_count'=>$persisted,
    'changed_count'=>$advanced,
    'unchanged_count'=>$stalled,
    'error_count'=>$errors,
    'advanced_count'=>$advanced,
    'stalled_count'=>$stalled,
    'reasons'=>$reasons,
    'first_item_id'=>(string)($metrics['first_item_id']??''),
    'last_item_id'=>(string)($metrics['last_item_id']??''),
    'cursor_before'=>$metrics['cursor_before']??null,
    'cursor_after'=>$metrics['cursor_after']??null,
    'rule_version'=>'1.3'
  ]);

  return $result;
}

// Compatibilidade temporária para chamadas antigas; sem gate de piloto.
function tvs_radar_run_backlog_batch_v12($limit=20){
  return tvs_radar_run_backlog_batch_v13($limit);
}

function tvs_radar_simulate_backlog_v11(){
  global $newsFile;
  $discovery=tvs_radar_discovery_read();
  $approval=tvs_queue_read();
  $news=tvs_read_json_file($newsFile); if(!is_array($news)) $news=[];

  $seen=[];
  foreach(array_merge($approval,$news) as $row){
    $seen[tvs_radar_discovery_key($row)]=1;
  }

  $metrics=[
    'total'=>count($discovery),
    'sf_ge_70'=>0,
    'sf_60_69_gate_pass'=>0,
    'sf_40_59'=>0,
    'sf_lt_40'=>0,
    'source_original_resolved'=>0,
    'google_unresolved'=>0,
    'trusted_source'=>0,
    'untrusted_source'=>0,
    'freshness_ok'=>0,
    'freshness_blocked'=>0,
    'core_4w_ok'=>0,
    'core_4w_missing'=>0,
    'hard_blocked'=>0,
    'potential_duplicates'=>0
  ];
  $rows=[];

  $GLOBALS['TVS_RADAR_DRY_RUN']=1;
  try{
    foreach($discovery as $cand){
      $city=(string)($cand['radar_requested_city']??$cand['city']??'');
      [$simCand,$mat,$package]=tvs_radar_enrich_candidate($cand,$city);
      $decision=tvs_radar_factually_ready($package);
      $sf=(int)($decision['sf']??0);

      if($sf>=70) $metrics['sf_ge_70']++;
      elseif($sf>=60) {
        if(!empty($decision['ready'])) $metrics['sf_60_69_gate_pass']++;
      } elseif($sf>=40) $metrics['sf_40_59']++;
      else $metrics['sf_lt_40']++;

      if(!empty($decision['source_original_resolved'])) $metrics['source_original_resolved']++;
      if(tvs_radar_is_google_news_url($simCand['url']??'')) $metrics['google_unresolved']++;
      if(!empty($decision['trusted_source'])) $metrics['trusted_source']++; else $metrics['untrusted_source']++;
      if(!empty($decision['freshness_ok'])) $metrics['freshness_ok']++; else $metrics['freshness_blocked']++;
      if(!empty($decision['core_4w_ok'])) $metrics['core_4w_ok']++; else $metrics['core_4w_missing']++;

      $hardReason='';
      $regionOk=tvs_radar_candidate_region_ok(
        array_merge($simCand,['description'=>($simCand['description']??'').' '.($mat['text']??'')]),
        $city,
        $hardReason
      );
      $qualityReason='';
      $qualityOk=tvs_material_quality_ok($mat,$qualityReason);
      $hardBlocked=(!$regionOk || !$qualityOk || empty($decision['freshness_ok']));
      if($hardBlocked) $metrics['hard_blocked']++;

      $key=tvs_radar_discovery_key($simCand);
      $duplicate=isset($seen[$key]);
      if($duplicate) $metrics['potential_duplicates']++;

      $rows[]=[
        'id'=>$cand['id']??'',
        'city'=>$city,
        'title'=>$cand['title']??'',
        'sf'=>$sf,
        'ready'=>(int)($decision['ready']??0),
        'source_original_resolved'=>(int)($decision['source_original_resolved']??0),
        'google_unresolved'=>tvs_radar_is_google_news_url($simCand['url']??'')?1:0,
        'trusted_source'=>(int)($decision['trusted_source']??0),
        'freshness_ok'=>(int)($decision['freshness_ok']??0),
        'core_4w_ok'=>(int)($decision['core_4w_ok']??0),
        'hard_blocked'=>$hardBlocked?1:0,
        'hard_reason'=>$hardBlocked?trim($hardReason.' '.$qualityReason):'',
        'potential_duplicate'=>$duplicate?1:0
      ];
    }
  } finally {
    unset($GLOBALS['TVS_RADAR_DRY_RUN']);
  }

  return ['metrics'=>$metrics,'rows'=>$rows];
}

function tvs_radar_normalize_terminal_states_v13(){
  $items=tvs_radar_discovery_read();
  if(!$items) return ['source_final'=>0,'manual_review'=>0,'changed'=>0];

  $sourceFinal=0; $manualReview=0; $changed=0;
  foreach($items as &$item){
    if(!is_array($item)) continue;

    $stage=(string)($item['pipeline_stage']??'');
    $url=trim((string)($item['url']??$item['source_url']??''));
    $isGoogle=tvs_radar_is_google_news_url($url);
    $sourceStatus=(string)($item['url_resolution_status']??'');
    $sourceAttempts=(int)($item['source_resolution_attempts']??0);

    // Backlog legado acumulou pipeline_attempts muito acima do limite antes de
    // existir contador por etapa. Para Google News ainda não resolvido, seis
    // ciclos totais já são evidência suficiente de esgotamento operacional.
    $legacyResolutionExhausted=$isGoogle && (int)($item['pipeline_attempts']??0)>=6;

    if($isGoogle && ($sourceStatus==='unresolved_final' || $sourceAttempts>=6 || $legacyResolutionExhausted)){
      $sourceFinal++;
      if($stage!=='fonte_esgotada'){
        $item['pipeline_stage']='fonte_esgotada';
        $item['pipeline_reason']='Fonte original esgotou as tentativas/TTL; preservada fora do backlog ativo para auditoria.';
        $item['enrichment_next_retry_at']='';
        $item['pipeline_updated_at']=date('c');
        $changed++;
      }
      continue;
    }

    if(!$isGoogle && !in_array($stage,['revisao_manual_pipeline','expirada_sem_enriquecimento'],true)){
      $exhausted=(
        (int)($item['factual_gate_attempts']??0)>=3
        || (int)($item['second_source_attempts']??0)>=3
        || (int)($item['extraction_attempts']??0)>=3
        || (int)($item['reporter_generation_attempts']??0)>=3
      );
      if($exhausted){
        $manualReview++;
        $item['pipeline_stage']='revisao_manual_pipeline';
        $item['pipeline_reason']='Etapa automática esgotou o limite de tentativas; pauta retirada do ciclo automático e preservada para revisão manual do pipeline.';
        $item['enrichment_next_retry_at']='';
        $item['pipeline_updated_at']=date('c');
        $changed++;
      }
    } elseif($stage==='revisao_manual_pipeline'){
      $manualReview++;
    }
  }
  unset($item);

  if($changed>0) tvs_radar_discovery_save($items);

  return ['source_final'=>$sourceFinal,'manual_review'=>$manualReview,'changed'=>$changed];
}

function tvs_radar_update_queue($perCity=15,$mode='normal'){
  global $TVS_RADAR_MODE;
  $oldMode=$TVS_RADAR_MODE ?? 'normal';
  $TVS_RADAR_MODE=$mode==='volume'?'volume':'normal';

  // O tempo deixa de ser regra editorial. Cada ciclo primeiro abastece todas as cidades,
  // salva a fila persistente e depois processa em rodízio. Se o ciclo terminar, o próximo
  // continua da fila salva sem reiniciar por Sumaré nem abandonar as demais cidades.
  if(PHP_SAPI==='cli') @set_time_limit(0);
  else @set_time_limit(tvs_radar_is_volume_mode()?120:90);

  tvs_radar_enforce_queue_rules(true);

  // Normaliza estados terminais antes de qualquer nova seleção. Itens com fonte
  // esgotada ou etapa automática já no limite deixam de inflar o backlog ativo.
  $terminalNormalization=tvs_radar_normalize_terminal_states_v13();

  // Antes de aceitar novas pautas, elimina o estado legado "sem auditoria".
  // A coleta nova já nasce auditada; esta varredura garante a mesma regra
  // para qualquer item antigo ainda presente no backlog.
  $entryAuditRecovery=tvs_radar_audit_missing_entries_v12(100);

  $discoveryAdded=tvs_radar_collect_discovery($mode,tvs_radar_is_volume_mode()?20:12);
  $target=max(5,min(10,(int)$perCity));
  $generated=tvs_radar_process_discovery($mode,$target);

  $st=tvs_radar_status();
  $st['terminal_normalization_last_cycle']=$terminalNormalization;
  $st['entry_audit_recovery_last_cycle']=$entryAuditRecovery;
  $st['pipeline_discovered_last_cycle']=$discoveryAdded;
  $st['pipeline_generated_last_cycle']=$generated;
  $st['pipeline_pending']=count(tvs_radar_discovery_read());
  $st['pipeline_updated_at']=date('c');
  tvs_radar_save_status($st);

  $TVS_RADAR_MODE=$oldMode;
  return $generated;
}
function tvs_publish_from_queue($id,$post){
  global $newsFile, $TVS_PUBLISH_ERROR;
  $TVS_PUBLISH_ERROR='';
  $queue=tvs_queue_read(); $found=null; $newq=[];
  foreach($queue as $item){ if(($item['id']??'')===$id) $found=$item; else $newq[]=$item; }
  if(!$found){ $TVS_PUBLISH_ERROR='Matéria não encontrada na fila.'; return false; }

  $humanReview=!empty($post['human_review']);
  if(empty($found['ai_editor_processed'])){
    $TVS_PUBLISH_ERROR='A matéria ainda não passou pelo Editor IA.';
    return false;
  }

  // Aprovação direta e revisão humana obedecem ao Editor IA obrigatório.
  // A revisão humana pode ajustar conteúdo, mas não substitui a etapa editorial
  // automatizada nem os requisitos factuais críticos.
  $title=trim($post['title']??$found['title']??'');
  if(function_exists('tvs_editorial_clean_title')) $title=tvs_editorial_clean_title($title,$post['source']??$found['source']??'');
  $body=trim($post['body']??$found['body']??'');
  $candidate=$found;
  foreach(['title','subtitle','summary','body','category','city','source','source_url','image','image_credit','seo_title','meta_description','slug','instagram_caption','whatsapp_text'] as $f){
    if(isset($post[$f])) $candidate[$f]=$post[$f];
  }
  $candidate['title']=$title;
  $candidate['body']=$body;

  if($humanReview){
    $critical=[];
    if($title==='' || mb_strlen($title,'UTF-8')<10) $critical[]='título ausente ou curto';
    if($body==='' || mb_strlen(strip_tags($body),'UTF-8')<80) $critical[]='texto insuficiente';
    $city=trim((string)($candidate['city']??''));
    if(!in_array($city,tvs_radar_allowed_cities(),true)) $critical[]='cidade inválida';
    $sourceUrl=trim((string)($candidate['source_url']??''));
    if($sourceUrl==='' || tvs_radar_is_google_news_url($sourceUrl)) $critical[]='fonte original não resolvida';
    $age=tvs_radar_infer_queue_age_days($candidate);
    if(!is_numeric($age)) $critical[]='data da matéria não confirmada';
    else {
      $queueText=($candidate['subtitle']??'').' '.($candidate['summary']??'').' '.$body;
      $limit=tvs_radar_temporal_exception($title,$queueText)?7:3;
      if((int)$age>$limit) $critical[]='matéria fora da janela editorial';
    }
    if($critical){
      $TVS_PUBLISH_ERROR='Publicação bloqueada: '.implode('; ',$critical).'.';
      return false;
    }
  }

  if(function_exists('tvs_radar_queue_item_readiness')){
    $readiness=tvs_radar_queue_item_readiness($candidate);
    if(empty($readiness['ready'])){
      $TVS_PUBLISH_ERROR='Publicação bloqueada: '.implode('; ',(array)($readiness['reasons']??['conteúdo incompleto'])).'.';
      return false;
    }
  } elseif($title==='' || mb_strlen($body,'UTF-8')<180){
    $TVS_PUBLISH_ERROR='Publicação bloqueada: título ou texto insuficiente.';
    return false;
  }
  $news=tvs_read_json_file($newsFile); if(!is_array($news)) $news=[];
  $publishedImage=trim($post['image']??$found['image']??'');
  if(!tvs_is_valid_image_url($publishedImage)) $publishedImage='';
  $news[]=['id'=>uniqid('news_'),'title'=>$title,'subtitle'=>trim($post['subtitle']??$found['subtitle']??''),'summary'=>trim($post['summary']??$found['summary']??''),'body'=>$body,'category'=>trim($post['category']??$found['category']??'Cidade'),'city'=>trim($post['city']??$found['city']??'Região'),'source'=>trim($post['source']??$found['source']??'Fonte consultada'),'source_url'=>trim($post['source_url']??$found['source_url']??''),'image'=>$publishedImage,'image_status'=>$publishedImage!==''?'verified':'missing','home_eligible'=>$publishedImage!==''?1:0,'editorial_state'=>'published','image_credit'=>trim($post['image_credit']??$found['image_credit']??tvs_image_credit_from_source($found['source']??$post['source']??'Fonte consultada', $publishedImage)),'tags'=>is_array($found['tags']??null)?$found['tags']:array_filter(array_map('trim',explode(',',(string)($post['tags']??'')))),'seo_title'=>trim($post['seo_title']??$found['seo_title']??$title),'meta_description'=>trim($post['meta_description']??$found['meta_description']??''),'slug'=>trim($post['slug']??$found['slug']??tvs_slug($title)),'instagram_caption'=>trim($post['instagram_caption']??$found['instagram_caption']??''),'whatsapp_text'=>trim($post['whatsapp_text']??$found['whatsapp_text']??''),'human_review_override'=>$humanReview?1:0,'human_reviewed_at'=>$humanReview?date('c'):null,'approved_by'=>$humanReview?'human_editor':'editorial_pipeline','views'=>0,'shares'=>0,'published_at'=>date('c'),'created_at'=>date('c')];
  tvs_save_json_file($newsFile,$news); tvs_queue_save($newq); return true;
}

function tvs_selected_ids_from_post(){
  $ids=$_POST['ids']??[];
  if(!is_array($ids)) $ids=[$ids];
  $ids=array_map('strval',$ids);
  $ids=array_values(array_unique(array_filter($ids,function($id){ return trim($id)!==''; })));
  return $ids;
}
function tvs_publish_many_from_queue($ids){
  $ok=0; $queue=tvs_queue_read(); $blocked=[];
  foreach($queue as $q){ if(in_array(($q['id']??''),$ids,true) && !tvs_radar_can_direct_approve($q)) $blocked[]=$q['id']; }
  foreach($ids as $id){ if(in_array($id,$blocked,true)) continue; if(tvs_publish_from_queue($id,[])) $ok++; }
  return $ok;
}
function tvs_discard_many_from_queue($ids){
  $lookup=array_fill_keys($ids,true); $removed=0; $queue=tvs_queue_read(); $new=[];
  foreach($queue as $q){
    if(isset($lookup[$q['id']??''])){
      $q['discard_origin']='bulk_manual';
      tvs_radar_discard($q,$q['city']??'Região','Descartada manualmente em lote pelo editor.');
      $removed++; continue;
    }
    $new[]=$q;
  }
  tvs_queue_save($new); return $removed;
}
function tvs_mark_many_for_review($ids){
  $lookup=array_fill_keys($ids,true); $changed=0; $queue=tvs_queue_read();
  foreach($queue as &$q){ if(isset($lookup[$q['id']??''])){ $q['review_level']='precisa_revisao'; $q['editorial_status']='Revisão'; $changed++; } }
  unset($q); tvs_queue_save($queue); return $changed;
}

function tvs_is_invalid_generated_article($item){
  $title=$item['title']??''; $url=$item['source_url']??($item['url']??'');
  $text=($item['subtitle']??'').' '.($item['summary']??'').' '.($item['body']??'');
  if(tvs_is_non_news_candidate($title,$url,$text)) return true;
  if(tvs_radar_has_generic_text($title.' '.$text)) return true;
  if(function_exists('tvs_is_institutional_profile_text') && tvs_is_institutional_profile_text($title,$url,$text)) return true;
  return false;
}
function tvs_clean_invalid_generated_content(){
  global $queueFile, $newsFile;
  $removedQueue=0; $removedNews=0;
  $queue=tvs_queue_read(); $newq=[];
  foreach($queue as $q){ if(tvs_is_invalid_generated_article($q)){ $removedQueue++; continue; } $newq[]=$q; }
  tvs_queue_save($newq);
  $news=tvs_read_json_file($newsFile); if(!is_array($news)) $news=[]; $newn=[];
  foreach($news as $n){ if(tvs_is_invalid_generated_article($n)){ $removedNews++; continue; } $newn[]=$n; }
  tvs_save_json_file($newsFile,$newn);
  tvs_radar_enforce_queue_rules(true);
  return [$removedQueue,$removedNews];
}

function tvs_reprocess_discarded_pautas($limit=12,$mode='normal'){
  global $TVS_RADAR_MODE;
  $oldMode=$TVS_RADAR_MODE ?? 'normal';
  $TVS_RADAR_MODE=$mode==='volume'?'volume':'normal';
  $file=dirname(__DIR__).'/data/pautas_descartadas.json';
  $discarded=tvs_read_json_file($file);
  if(!is_array($discarded)) $discarded=[];
  $queue=tvs_queue_read();
  $seen=[];
  foreach($queue as $q){ if(!empty($q['source_url'])) $seen[$q['source_url']]=1; }
  $kept=[]; $ok=0; $checked=0;
  foreach($discarded as $cand){
    if($checked>=$limit){ $kept[]=$cand; continue; }
    $checked++;
    $city=$cand['city']??'Região';
    $url=$cand['url']??'';
    $title=$cand['title']??'';
    if(!$url || isset($seen[$url]) || (function_exists('tvs_is_skip_or_navigation_title') && tvs_is_skip_or_navigation_title($title))){
      $kept[]=$cand;
      continue;
    }
    $article=tvs_generate_ready_article($city,$cand);
    if(is_array($article) && !empty($article['title']) && !empty($article['body'])){
      $article['review_level']='precisa_revisao';
      $article['editorial_status']='Revisão';
      $queue[]=$article;
      $seen[$url]=1;
      $ok++;
    } else {
      $kept[]=$cand;
    }
  }
  tvs_queue_save($queue);
  tvs_save_json_file($file,array_values($kept));
  $TVS_RADAR_MODE=$oldMode;
  return [$ok, max(0,count($discarded)-count($kept)-$ok)];
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $action=$_POST['action']??'';
  if($action==='update_radar'){
    $cfg=tvs_radar_config();
    $perCity=max(1,min(40,(int)($cfg['per_city']??20)));
    $n=tvs_radar_update_queue($perCity,'normal');
    $pipeline=tvs_radar_status();
    $pending=(int)($pipeline['pipeline_pending']??0);
    $found=(int)($pipeline['pipeline_discovered_last_cycle']??0);
    $st=array_merge($pipeline,['last_run'=>date('c'),'last_mode'=>'manual','last_generated'=>$n,'last_message'=>"{$n} matéria(s) pronta(s); {$pending} pauta(s) no pipeline; {$found} descoberta(s) neste ciclo."]);
    tvs_radar_save_status($st);
    $notice="Radar atualizado. Os números abaixo mostram o snapshot atual do pipeline.";
  } elseif($action==='update_radar_volume'){
    $cfg=tvs_radar_config();
    $perCity=max(25,min(60,(int)($cfg['per_city']??25)));
    $n=tvs_radar_update_queue($perCity,'volume');
    $pipeline=tvs_radar_status();
    $pending=(int)($pipeline['pipeline_pending']??0);
    $found=(int)($pipeline['pipeline_discovered_last_cycle']??0);
    $st=array_merge($pipeline,['last_run'=>date('c'),'last_mode'=>'volume_maximo','last_generated'=>$n,'last_message'=>"{$n} matéria(s) pronta(s); {$pending} pauta(s) no pipeline; {$found} descoberta(s) neste ciclo."]);
    tvs_radar_save_status($st);
    $notice="Modo Volume Máximo concluído. Os números abaixo mostram o snapshot atual do pipeline.";
  } elseif($action==='save_settings'){
    $cfg=tvs_radar_config();
    $cfg['auto_daily']=!empty($_POST['auto_daily']);
    $cfg['per_city']=max(1,min(40,(int)($_POST['per_city']??20)));
    tvs_radar_save_config($cfg);
    $notice='Configurações do Radar salvas.';
  } elseif($action==='approve'){
    if(tvs_publish_from_queue($_POST['id']??'',$_POST)){ header('Location: noticias.php?published=1'); exit; }
    else {
      global $TVS_PUBLISH_ERROR;
      $error=$TVS_PUBLISH_ERROR!==''?$TVS_PUBLISH_ERROR:'Não foi possível aprovar/publicar esta matéria.';
    }
  } elseif($action==='bulk_approve'){
    $ids=tvs_selected_ids_from_post();
    if(!$ids){ $error='Selecione pelo menos uma matéria.'; }
    else { $ok=tvs_publish_many_from_queue($ids); $notice=$ok.' matéria(s) aprovada(s) e publicada(s).'; if($ok===0) $error='Nenhuma matéria foi publicada. Verifique se os itens ainda estão na fila.'; }
  } elseif($action==='bulk_discard'){
    $ids=tvs_selected_ids_from_post();
    if(!$ids){ $error='Selecione pelo menos uma matéria.'; }
    else { $removed=tvs_discard_many_from_queue($ids); $notice=$removed.' matéria(s) descartada(s).'; }
  } elseif($action==='bulk_review'){
    $ids=tvs_selected_ids_from_post();
    if(!$ids){ $error='Selecione pelo menos uma matéria.'; }
    else { $changed=tvs_mark_many_for_review($ids); $notice=$changed.' matéria(s) enviada(s) para revisão.'; }
  } elseif($action==='simulate_backlog'){
    $simulation=tvs_radar_simulate_backlog_v11();
    $metrics=(array)($simulation['metrics']??[]);
    $reportFile=dirname(__DIR__).'/data/radar_backlog_simulation_v12.json';
    $simulationAt=date('c');
    tvs_save_json_file($reportFile,['generated_at'=>$simulationAt,'rule_version'=>'1.3','metrics'=>$metrics,'rows'=>$simulation['rows']??[]]);
    tvs_radar_record_run_telemetry([
      'run_id'=>'simulation_'.date('Ymd_His'),
      'started_at'=>$simulationAt,
      'finished_at'=>date('c'),
      'mode'=>'dry_run',
      'retroactive_stage'=>'simulation',
      'selected_count'=>(int)($metrics['total']??0),
      'processed_count'=>(int)($metrics['total']??0),
      'changed_count'=>0,
      'unchanged_count'=>(int)($metrics['total']??0),
      'persisted_count'=>0,
      'error_count'=>0,
      'first_item_id'=>'',
      'last_item_id'=>'',
      'cursor_before'=>null,
      'cursor_after'=>null,
      'rule_version'=>'1.3'
    ]);
    $notice='Simulação somente leitura concluída: '.(int)($metrics['total']??0).' pauta(s); '
      .(int)($metrics['sf_ge_70']??0).' com SF ≥70; '
      .(int)($metrics['sf_60_69_gate_pass']??0).' em SF 60–69 com gates; '
      .(int)($metrics['google_unresolved']??0).' Google News não resolvido(s); '
      .(int)($metrics['hard_blocked']??0).' bloqueada(s) por regra dura.';
  } elseif($action==='pilot_backlog'){
    $notice='O piloto retroativo foi encerrado. O Radar 1.3 processa diretamente as pautas auditadas pendentes em lotes controlados.';
  } elseif($action==='process_backlog_batch'){
    $batch=tvs_radar_run_backlog_batch_v13(20);
    if(($batch['status']??'')==='sem_pautas_elegiveis'){
      $notice='Backlog 1.3: não há pautas elegíveis para processamento neste momento. Itens em espera respeitam retry/TTL e não são forçados.';
    } else {
      $reasonText='';
      foreach((array)($batch['reasons']??[]) as $reason=>$count){
        $reasonText.=' · '.(int)$count.' '.(string)$reason;
      }
      $notice='Backlog 1.3 '.strtoupper((string)($batch['status']??'indefinido')).': '
        .(int)($batch['selected']??0).' selecionada(s); '
        .(int)($batch['processed']??0).' processada(s); '
        .(int)($batch['persisted']??0).' persistida(s); '
        .(int)($batch['advanced']??0).' avançaram; '
        .(int)($batch['stalled']??0).' permaneceram no mesmo estado; '
        .(int)($batch['generated']??0).' chegaram à fila editorial; '
        .(int)($batch['errors']??0).' erro(s).'.$reasonText
        .' Nenhuma publicação automática foi executada.';
    }
  } elseif($action==='clean_invalid'){
    [$rq,$rn]=tvs_clean_invalid_generated_content();
    $notice='Limpeza editorial concluída: '.$rq.' item(ns) removido(s) da fila e '.$rn.' matéria(s) removida(s) das publicadas.';
  } elseif($action==='reprocess_discarded'){
    [$ok,$drop]=tvs_reprocess_discarded_pautas(12,'normal');
    $notice='Reprocessamento concluído: '.$ok.' pauta(s) voltaram para revisão. Clique novamente se quiser tentar mais descartadas.';
  } elseif($action==='reprocess_discarded_volume'){
    [$ok,$drop]=tvs_reprocess_discarded_pautas(36,'volume');
    $notice='Reprocessamento em Volume Máximo concluído: '.$ok.' pauta(s) voltaram para revisão.';
  } elseif($action==='discard'){
    $id=$_POST['id']??''; $queue=tvs_queue_read(); $new=[]; $found=null;
    foreach($queue as $q){ if(($q['id']??'')===$id){ $found=$q; continue; } $new[]=$q; }
    if($found){ $found['discard_origin']='manual'; tvs_radar_discard($found,$found['city']??'Região','Descartada manualmente pelo editor.'); tvs_queue_save($new); $notice='Matéria descartada e preservada no Log Editorial.'; }
    else $error='Matéria não encontrada na fila.';
  } elseif($action==='save_edit'){
    $id=$_POST['id']??''; $queue=tvs_queue_read();
    foreach($queue as &$q){
      if(($q['id']??'')===$id){
        $previousImage=trim((string)($q['image']??''));

        foreach(['title','subtitle','summary','body','category','city','source','source_url','image','image_credit','seo_title','meta_description','slug','instagram_caption','whatsapp_text'] as $f){
          if(isset($_POST[$f])) $q[$f]=$_POST[$f];
        }

        $q['tags']=array_filter(array_map('trim',explode(',',(string)($_POST['tags']??''))));

        $savedImage=trim((string)($q['image']??''));

        // Quando o editor salva uma imagem válida, a pendência é resolvida.
        if(
          $savedImage!=='' &&
          function_exists('tvs_is_valid_image_url') &&
          tvs_is_valid_image_url($savedImage) &&
          ($savedImage!==$previousImage || !empty($q['image_review_required']))
        ){
          $q['image_source_type']='manual:editor';
          $q['image_review_required']=0;
          $q['image_review_reason']='';
          $q['review_level']='precisa_revisao';
          $q['editorial_status']='Imagem corrigida';
        }
      }
    }
    unset($q);
    tvs_queue_save($queue);
    $notice='Edição salva. A publicação só será liberada após o Editor IA e a validação editorial concluírem.';
  }

  if($notice!=='') $_SESSION['tvs_flash_notice']=$notice;
  if($error!=='') $_SESSION['tvs_flash_error']=$error;
  header('Location: radar-regional.php');
  exit;
}

// Importante: não executa RSS/Gemini no simples carregamento da página.
// Isso evita erro 504 em hospedagem compartilhada/nginx.
// Para atualização automática real, use admin/cron_radar.php no cPanel; para teste, use o botão Atualizar Agora.
$cfg=tvs_radar_config();
if(defined('TVS_RADAR_CRON') && TVS_RADAR_CRON){ return; }
$radarCfg=tvs_radar_config();
$radarStatus=tvs_radar_status();
$queue=tvs_queue_read();

// Reclassifica a fila visível por prontidão editorial. Itens incompletos permanecem
// preservados para correção, mas nunca aparecem como publicáveis/aprováveis.
$queueReadinessMigrated=false;
foreach($queue as &$readinessItem){
  if(!function_exists('tvs_radar_queue_item_readiness')) break;
  $readiness=tvs_radar_queue_item_readiness($readinessItem);
  if(empty($readiness['ready'])){
    $reasons=$readiness['reasons']??[];
    $readinessItem['queue_status']='processing';
    $readinessItem['queue_pending_reasons']=$reasons;
    $readinessItem['publication_eligible']=0;
    $readinessItem['review_level']='precisa_revisao';
    $readinessItem['editorial_status']='Conteúdo incompleto';
    $queueReadinessMigrated=true;
  } else {
    $readinessItem['queue_status']='ready';
    $readinessItem['queue_pending_reasons']=[];
  }
}
unset($readinessItem);
if($queueReadinessMigrated) tvs_queue_save($queue);

/*
 * Migração defensiva de filas legadas:
 * cards antigos podiam carregar assets/cat-*.svg como se fossem imagem válida.
 * Esses arquivos são apenas fallback visual da TV Sumaré e não podem liberar publicação.
 */
$queueImageMigrated=false;
foreach($queue as &$legacyItem){
  $legacyImage=trim((string)($legacyItem['image']??''));
  $legacyType=trim((string)($legacyItem['image_source_type']??''));
  $isLegacyFallback=
    preg_match('~(?:^|/)(?:assets/)?cat-[^/]+\.svg(?:\?|$)~i',$legacyImage)
    || preg_match('~logo-tv-sumare|placeholder~i',$legacyImage)
    || in_array($legacyType,['category:fallback','default_or_legacy'],true);

  if($isLegacyFallback){
    $legacyItem['image']='';
    $legacyItem['image_source_type']='missing:source';
    $legacyItem['image_review_required']=1;
    $legacyItem['image_review_reason']='Imagem jornalística da matéria não foi confirmada; selecione uma imagem válida antes de publicar.';
    $legacyItem['review_level']='precisa_revisao';
    $legacyItem['editorial_status']='Revisão de imagem';
    $queueImageMigrated=true;
  }
}
unset($legacyItem);
if($queueImageMigrated) tvs_queue_save($queue);

$byCity=[]; foreach($cities as $c) $byCity[$c]=[];
$sensitiveQueue=[]; $imageReviewQueue=[]; $normalQueue=[]; $processingQueue=[];
foreach($queue as $q){
  $readiness=function_exists('tvs_radar_queue_item_readiness')
    ? tvs_radar_queue_item_readiness($q)
    : ['ready'=>!empty($q['ai_editor_processed'])];

  if(empty($q['ai_editor_processed']) || empty($readiness['ready'])){
    $q['queue_status']='processing';
    $q['queue_pending_reasons']=array_values(array_unique(array_filter(array_merge(
      (array)($q['queue_pending_reasons']??[]),
      (array)($readiness['reasons']??[]),
      empty($q['ai_editor_processed'])?['Editor IA ainda não concluído']:[]
    ))));
    $processingQueue[]=$q;
    continue;
  }

  if(!empty($q['sensitive_review_required']) || ($q['review_level']??'')==='revisao_obrigatoria'){
    $sensitiveQueue[]=$q;
    continue;
  }
  if(!empty($q['image_review_required'])){
    $imageReviewQueue[]=$q;
    continue;
  }
  $normalQueue[]=$q;
  $byCity[$q['city']??'Região'][]=$q;
}
$editId=$_GET['edit']??''; $editItem=null; foreach($queue as $q){ if(($q['id']??'')===$editId){$editItem=$q; break;} }
$editReadiness=$editItem && function_exists('tvs_radar_queue_item_readiness')
  ? tvs_radar_queue_item_readiness($editItem)
  : ['ready'=>!empty($editItem['ai_editor_processed'])];
$editCanApprove=$editItem && !empty($editItem['ai_editor_processed']) && !empty($editReadiness['ready']);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Matérias para Aprovação | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=132"><style>.queue-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.matter{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:14px;box-shadow:0 8px 22px rgba(15,23,42,.06)}.matter img{width:100%;height:150px;object-fit:cover;border-radius:14px;background:#eef2ff}.matter h3{margin:10px 0 6px;font-size:18px}.matter p{color:#475569;font-size:14px}.badge{display:inline-flex;border-radius:999px;background:#eef2ff;color:#1d4ed8;padding:5px 9px;font-size:12px;font-weight:800;margin:6px 5px 6px 0}.city-block{margin:24px 0}.matter-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.edit-form{background:#fff;border-radius:18px;padding:18px;border:1px solid #e5e7eb}.edit-form input,.edit-form textarea,.edit-form select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:12px;margin:5px 0 12px}.edit-form textarea{min-height:320px}.muted{color:#64748b}.settings-box{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:14px;margin:14px 0}.settings-inline{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.settings-inline label{display:flex;flex-direction:column;font-size:13px;color:#334155}.settings-inline input[type=number]{width:110px;padding:10px;border:1px solid #cbd5e1;border-radius:12px}.settings-inline .check{flex-direction:row;gap:8px;align-items:center}.top-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.bulk-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.bulk-row .check,.bulk-check{display:flex;align-items:center;gap:7px;font-weight:800;color:#334155}.bulk-check{margin-bottom:8px}.bulk-check input{width:18px;height:18px}@media(max-width:1000px){.queue-grid{grid-template-columns:1fr}.matter img{height:190px}}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Centro de Redação • Radar 2.0</span><h1>Matérias para Aprovação</h1><p class="muted">O Radar abastece a redação com mais opções. Você aprova o que achar relevante para a TV Sumaré.</p></div><div class="top-actions"><form method="post" action="radar-regional.php" class="radar-action-form"><?=tvs_csrf_field()?><input type="hidden" name="action" value="update_radar"><button class="btn orange" type="submit" data-busy-label="Atualizando Radar…">Atualizar Agora</button></form><form method="post" action="radar-regional.php" class="radar-action-form"><?=tvs_csrf_field()?><input type="hidden" name="action" value="update_radar_volume"><button class="btn secondary" type="submit" data-busy-label="Ativando volume…">Modo Volume Máximo</button></form></div></div>
<div class="settings-box"><form method="post" action="radar-regional.php" class="settings-inline radar-action-form"><?=tvs_csrf_field()?><input type="hidden" name="action" value="save_settings"><label class="check"><input type="checkbox" name="auto_daily" value="1" <?=!empty($radarCfg['auto_daily'])?'checked':''?>> Atualização automática diária</label><label>Meta de matérias por cidade<input type="number" min="1" max="40" name="per_city" value="<?=h($radarCfg['per_city']??20)?>"></label><button class="btn secondary" type="submit">Salvar configuração</button><span class="muted">Última atualização: <?=!empty($radarStatus['last_run'])?h(date('d/m/Y H:i',strtotime($radarStatus['last_run']))):'ainda não executada'?> <?=!empty($radarStatus['last_mode'])?'• '.h($radarStatus['last_mode']):''?></span></form><div class="radar-command-bar" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px"><form method="post" action="radar-regional.php" class="radar-action-form"><?=tvs_csrf_field()?><input type="hidden" name="action" value="simulate_backlog"><button class="btn secondary" type="submit" data-busy-label="Simulando…">Simular backlog</button></form><form method="post" action="radar-regional.php" class="radar-action-form"><?=tvs_csrf_field()?><input type="hidden" name="action" value="process_backlog_batch"><button class="btn secondary" type="submit" data-busy-label="Processando lote…">Processar próximo lote de 20</button></form><form method="post" action="radar-regional.php" class="radar-action-form"><?=tvs_csrf_field()?><input type="hidden" name="action" value="clean_invalid"><button class="btn secondary" type="submit" data-busy-label="Limpando…">Limpar matérias inválidas</button></form><span class="muted">Radar 1.3: backlog contínuo em lotes de 20 sobre pautas auditadas e elegíveis. Sem gate de piloto e sem publicação automática.</span></div><div id="radar-command-status" class="muted" style="margin-top:8px" aria-live="polite"></div></div>
<?php if(!empty($radarStatus['last_source_resolution_run'])): ?><div class="notice" style="background:#eff6ff;border-color:#bfdbfe;color:#1e3a8a">Resolvedor automático de fontes: último ciclo <?=h(date('d/m/Y H:i',strtotime($radarStatus['last_source_resolution_run'])))?> · <?= (int)($radarStatus['last_source_resolution_processed']??0) ?> processada(s) · <?= (int)($radarStatus['last_source_resolution_resolved']??0) ?> resolvida(s) · <?= (int)($radarStatus['last_source_resolution_remaining_due']??0) ?> ainda vencida(s) para nova tentativa. A fila roda automaticamente a cada ciclo do Radar.</div><?php endif; ?>
<?php if($notice): ?><div class="notice"><?=h($notice)?></div><?php endif; ?><?php if($error): ?><div class="notice error"><?=h($error)?></div><?php endif; ?>
<?php if($editItem): $tags=is_array($editItem['tags']??null)?implode(', ',$editItem['tags']):($editItem['tags']??''); ?>
<section class="edit-form"><h2><?= $editCanApprove ? 'Editar matéria antes de aprovar' : 'Matéria em processamento editorial' ?></h2><?php if(!$editCanApprove): ?><div class="notice error">Esta matéria ainda não está liberada para aprovação. <?=h(implode(' · ',array_values(array_unique(array_filter(array_merge((array)($editReadiness['reasons']??[]),empty($editItem['ai_editor_processed'])?['Editor IA ainda não concluído']:[]))))))?></div><?php endif; ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=h($editItem['id'])?>"><input type="hidden" name="human_review" value="1"><label>Título</label><input name="title" value="<?=h($editItem['title']??'')?>"><label>Subtítulo</label><input name="subtitle" value="<?=h($editItem['subtitle']??'')?>"><label>Resumo</label><input name="summary" value="<?=h($editItem['summary']??'')?>"><label>Cidade</label><input name="city" value="<?=h($editItem['city']??'')?>"><label>Categoria</label><input name="category" value="<?=h($editItem['category']??'')?>"><label>Imagem</label><input name="image" value="<?=h($editItem['image']??'')?>"><label>Crédito da imagem</label><input name="image_credit" value="<?=h($editItem['image_credit']??'')?>"><label>Texto completo</label><textarea name="body"><?=h($editItem['body']??'')?></textarea><label>Fonte</label><input name="source" value="<?=h($editItem['source']??'')?>"><label>URL da fonte</label><input name="source_url" value="<?=h($editItem['source_url']??'')?>"><label>Tags</label><input name="tags" value="<?=h($tags)?>"><label>SEO title</label><input name="seo_title" value="<?=h($editItem['seo_title']??'')?>"><label>Meta description</label><input name="meta_description" value="<?=h($editItem['meta_description']??'')?>"><label>Slug</label><input name="slug" value="<?=h($editItem['slug']??'')?>"><label>Legenda Instagram</label><textarea name="instagram_caption" style="min-height:120px"><?=h($editItem['instagram_caption']??'')?></textarea><label>Texto WhatsApp</label><textarea name="whatsapp_text" style="min-height:100px"><?=h($editItem['whatsapp_text']??'')?></textarea><div class="matter-actions"><button class="btn" type="submit" name="action" value="save_edit">Salvar edição</button><?php if($editCanApprove): ?><button class="btn orange" type="submit" name="action" value="approve" onclick="return confirm('Aprovar e publicar exatamente esta versão revisada?')">Aprovar e publicar</button><?php else: ?><a class="btn secondary" href="drafts.php">Ver em Revisões Pendentes</a><?php endif; ?><a class="btn secondary" href="radar-regional.php">Voltar</a></div></form></section>
<?php else: ?>
<?php
$discarded=tvs_read_json_file(dirname(__DIR__).'/data/pautas_descartadas.json');
$sourcePendingKeys=[];
$sourceTerminalKeys=[];
$manualPipelineKeys=[];
$sourceStageCounts=[
  'precisa_resolver_fonte'=>0,
  'aguardando_fonte'=>0,
  'fonte_resolvida'=>0,
  'aguardando_enriquecimento'=>0,
  'enriquecimento_ativo'=>0
];
foreach(tvs_radar_discovery_read() as $processingItem){
  $processingKey=tvs_radar_discovery_key($processingItem);
  $stage=(string)($processingItem['pipeline_stage']??'');

  // Estados terminais continuam auditáveis, mas não pertencem ao backlog ativo.
  if($stage==='fonte_esgotada'){
    $sourceTerminalKeys[$processingKey]=1;
    continue;
  }
  if($stage==='revisao_manual_pipeline'){
    $manualPipelineKeys[$processingKey]=1;
    continue;
  }

  // O painel trabalha com pautas únicas pela mesma chave usada pela deduplicação.
  // Assim o total de fonte/enriquecimento fecha exatamente com a soma dos subestados.
  if(isset($sourcePendingKeys[$processingKey])) continue;
  $sourcePendingKeys[$processingKey]=1;
  if(isset($sourceStageCounts[$stage])){
    $sourceStageCounts[$stage]++;
    continue;
  }
  $url=trim((string)($processingItem['url']??$processingItem['source_url']??''));
  $package=(array)($processingItem['fact_package']??[]);
  if($url==='' || tvs_radar_is_google_news_url($url)){
    $sourceStageCounts['precisa_resolver_fonte']++;
  } elseif(!empty($package['source_original_resolved'])){
    $sourceStageCounts['fonte_resolvida']++;
  } else {
    $sourceStageCounts['enriquecimento_ativo']++;
  }
}
$editorPendingKeys=[];
foreach($processingQueue as $processingItem){
  $processingKey=tvs_radar_discovery_key($processingItem);
  if(!isset($sourcePendingKeys[$processingKey])) $editorPendingKeys[$processingKey]=1;
}
$totalSourcePending=count($sourcePendingKeys);
$totalSourceTerminal=count($sourceTerminalKeys);
$totalManualPipeline=count($manualPipelineKeys);
$totalEditorPending=count($editorPendingKeys);
$totalReady=count($normalQueue);
$totalSensitive=count($sensitiveQueue);
$totalImageReview=count($imageReviewQueue);
$totalPipelineCurrent=$totalSourcePending+$totalEditorPending+$totalReady+$totalSensitive+$totalImageReview;
$totalAuditTerminal=$totalSourceTerminal+$totalManualPipeline;

$factBlocks=['sem_auditoria'=>0,'fonte_nao_resolvida'=>0,'texto_insuficiente'=>0,'quatro_w_incompleto'=>0,'fonte_nao_confiavel'=>0,'fora_janela'=>0,'sem_interesse_editorial'=>0];
foreach(tvs_radar_discovery_read() as $diagItem){
  $diagStage=(string)($diagItem['pipeline_stage']??'');
  if(in_array($diagStage,['fonte_esgotada','revisao_manual_pipeline','expirada_sem_enriquecimento'],true)) continue;
  $entryStatus=(string)($diagItem['entry_audit_status']??'');
  $audit=(array)($diagItem['fact_gate_audit']??[]);
  if($entryStatus===''){ $factBlocks['sem_auditoria']++; continue; }
  if(!$audit){
    if($entryStatus==='precisa_resolver_fonte') $factBlocks['fonte_nao_resolvida']++;
    continue;
  }
  if(empty($audit['source_original_resolved'])) $factBlocks['fonte_nao_resolvida']++;
  if(empty($audit['content_usable'])) $factBlocks['texto_insuficiente']++;
  if(empty($audit['core_4w_ok'])) $factBlocks['quatro_w_incompleto']++;
  if(empty($audit['trusted_source'])) $factBlocks['fonte_nao_confiavel']++;
  if(empty($audit['freshness_ok'])) $factBlocks['fora_janela']++;
  if(empty($audit['editorial_interest'])) $factBlocks['sem_interesse_editorial']++;
}
$editorBlocks=[];
foreach($processingQueue as $diagItem){
  $k=tvs_radar_discovery_key($diagItem);
  if(isset($sourcePendingKeys[$k])) continue;
  $validation=tvs_radar_queue_item_readiness($diagItem);
  $label=!empty($diagItem['ai_editor_processed'])
    ? implode(' · ',(array)($validation['reasons']??['Validação editorial pendente']))
    : trim((string)($diagItem['ai_editor_last_error_label']??'Aguardando nova tentativa'));
  $editorBlocks[$label]=($editorBlocks[$label]??0)+1;
}
$runTelemetry=tvs_read_json_file(tvs_radar_run_telemetry_file());
$lastRunTelemetry=is_array($runTelemetry) && $runTelemetry ? end($runTelemetry) : [];
?><div class="notice">Pipeline ativo: <?=$totalPipelineCurrent?> pauta(s) — <?=$totalSourcePending?> em fonte/enriquecimento · <?=$totalEditorPending?> em processamento editorial · <?=$totalSensitive?> em revisão obrigatória · <?=$totalImageReview?> em revisão de imagem · <?=$totalReady?> pronta(s) para aprovação.<?php if($totalAuditTerminal>0): ?> Fora do backlog ativo: <?=$totalSourceTerminal?> fonte(s) esgotada(s) · <?=$totalManualPipeline?> em revisão manual do pipeline.<?php endif; ?></div><div class="cards"><div class="stat"><span>Prontas para aprovação</span><b><?=$totalReady?></b><small>Editor IA e validação concluídos, sem pendência adicional</small></div><div class="stat"><span>Fonte / enriquecimento ativo</span><b><?=$totalSourcePending?></b><small>resolver: <?=$sourceStageCounts['precisa_resolver_fonte']?> · aguardando fonte: <?=$sourceStageCounts['aguardando_fonte']?> · fonte resolvida: <?=$sourceStageCounts['fonte_resolvida']?> · enriquecendo: <?=$sourceStageCounts['enriquecimento_ativo']?> · aguardando enriquecimento: <?=$sourceStageCounts['aguardando_enriquecimento']?></small></div><div class="stat"><span>Processamento editorial</span><b><?=$totalEditorPending?></b><small><a href="drafts.php">ver matérias e motivos</a></small></div><div class="stat"><span>Revisão obrigatória</span><b><?=$totalSensitive?></b><small>pautas sensíveis ou de alto impacto</small></div><div class="stat"><span>Revisão de imagem</span><b><?=$totalImageReview?></b><small>texto pronto; imagem precisa ser confirmada</small></div></div>
<div class="settings-box"><strong>Diagnóstico do gargalo</strong><div style="margin-top:8px;display:flex;gap:7px;flex-wrap:wrap"><?php
$factLabels=['sem_auditoria'=>'Ainda sem auditoria','fonte_nao_resolvida'=>'Fonte não resolvida','texto_insuficiente'=>'Conteúdo factual insuficiente','quatro_w_incompleto'=>'4W incompleto','fonte_nao_confiavel'=>'Fonte não confiável','fora_janela'=>'Fora da janela','sem_interesse_editorial'=>'Interesse editorial não detectado'];
foreach($factBlocks as $key=>$count){ if($count>0): ?><span class="badge"><?=h($factLabels[$key])?>: <?=$count?></span><?php endif; }
foreach($editorBlocks as $label=>$count){ if($count>0): ?><span class="badge" style="background:#f5f3ff;color:#6d28d9">Pendência editorial — <?=h($label)?>: <?=$count?></span><?php endif; }
?></div><small class="muted">Uma pauta pode aparecer em mais de um motivo factual; os cards superiores continuam mutuamente exclusivos.</small></div>
<?php if($lastRunTelemetry): ?><div class="settings-box"><strong>Telemetria da última execução</strong><div style="margin-top:8px;display:flex;gap:7px;flex-wrap:wrap"><span class="badge">Etapa: <?=h($lastRunTelemetry['retroactive_stage']??'normal')?></span><span class="badge">Selecionadas: <?=h((string)($lastRunTelemetry['selected_count']??0))?></span><span class="badge">Processadas: <?=h((string)($lastRunTelemetry['processed_count']??0))?></span><span class="badge">Persistidas: <?=h((string)($lastRunTelemetry['persisted_count']??0))?></span><span class="badge">Alteradas: <?=h((string)($lastRunTelemetry['changed_count']??0))?></span><span class="badge">Erros: <?=h((string)($lastRunTelemetry['error_count']??0))?></span><span class="badge">Cursor: <?=h((string)($lastRunTelemetry['cursor_before']??'-'))?> → <?=h((string)($lastRunTelemetry['cursor_after']??'-'))?></span></div><small class="muted">Run <?=h($lastRunTelemetry['run_id']??'')?> · início <?=h($lastRunTelemetry['started_at']??'')?> · fim <?=h($lastRunTelemetry['finished_at']??'')?> · regra <?=h($lastRunTelemetry['rule_version']??'')?></small></div><?php endif; ?>
<?php if($sensitiveQueue): ?><section class="city-block"><h2>Revisão obrigatória <small class="muted">(<?=count($sensitiveQueue)?>)</small></h2><div class="queue-grid"><?php foreach($sensitiveQueue as $m): ?><article class="matter"><span class="badge" style="background:#fef2f2;color:#b91c1c">Revisão obrigatória</span><span class="badge"><?=h($m['editorial_status']??'Revisão')?></span><?php if(isset($m['editorial_score'])): ?><span class="badge">Score <?=h($m['editorial_score'])?></span><?php endif; ?><h3><?=h($m['title']??'Sem título')?></h3><p><?=h($m['subtitle']??($m['summary']??''))?></p><a class="btn orange" href="?edit=<?=h($m['id'])?>">Revisar</a></article><?php endforeach; ?></div></section><?php endif; ?>
<?php if($imageReviewQueue): ?><section class="city-block"><h2>Revisão de imagem <small class="muted">(<?=count($imageReviewQueue)?>)</small></h2><div class="queue-grid"><?php foreach($imageReviewQueue as $m): ?><article class="matter"><span class="badge" style="background:#fff7ed;color:#c2410c">Imagem pendente</span><h3><?=h($m['title']??'Sem título')?></h3><p><?=h($m['image_review_reason']??'Revisar imagem antes da publicação.')?></p><a class="btn orange" href="?edit=<?=h($m['id'])?>">Corrigir imagem</a></article><?php endforeach; ?></div></section><?php endif; ?>
<form id="bulk-form" method="post" class="settings-box bulk-row" onsubmit="return confirm('Aplicar a ação nas matérias selecionadas?');"><?=tvs_csrf_field()?><label class="check"><input type="checkbox" id="select-all-radar"> Selecionar todas visíveis</label><button class="btn orange" type="submit" name="action" value="bulk_approve">Aprovar selecionadas</button><button class="btn secondary" type="submit" name="action" value="bulk_review">Enviar para revisão</button><button class="btn secondary" type="submit" name="action" value="bulk_discard">Descartar selecionadas</button><span class="muted">Use os checkboxes dos cards para operar várias matérias de uma vez.</span></form>
<?php foreach($cities as $city): $items=array_slice($byCity[$city]??[],0,20); ?>
<section class="city-block"><h2><?=h($city)?> <small class="muted">(<?=count($items)?>)</small></h2><?php if(!$items): ?><p class="muted">Nenhuma matéria aguardando aprovação para esta cidade.</p><?php else: ?><div class="queue-grid"><?php foreach($items as $m): ?><article class="matter"><label class="bulk-check"><input type="checkbox" class="radar-select" form="bulk-form" name="ids[]" value="<?=h($m['id'])?>"> Selecionar</label><?php if(!empty($m['image']) && empty($m['image_review_required'])): ?><img src="<?=h(tvs_admin_img($m['image'], $m['category']??'Cidade'))?>" onerror="this.closest('.matter').querySelector('.image-pending').style.display='flex';this.style.display='none';" alt=""><div class="image-pending" style="display:none;height:150px;align-items:center;justify-content:center;border-radius:14px;background:#f8fafc;border:1px dashed #cbd5e1;color:#64748b;font-weight:700;text-align:center;padding:16px">Imagem indisponível<br>revisar antes de publicar</div><?php else: ?><div class="image-pending" style="height:150px;display:flex;align-items:center;justify-content:center;border-radius:14px;background:#f8fafc;border:1px dashed #cbd5e1;color:#64748b;font-weight:700;text-align:center;padding:16px">Imagem pendente<br>selecione antes de publicar</div><?php endif; ?><?php if(!empty($m['image_credit'])): ?><small class="muted" style="display:block;margin:4px 0 8px"><?=h($m['image_credit'])?></small><?php endif; ?><span class="badge"><?=h($m['category']??'Cidade')?></span><?php if(($m['review_level']??'')==='precisa_revisao'): ?><span class="badge" style="background:#fff7ed;color:#c2410c">Precisa revisão</span><?php endif; ?><?php if(!empty($m['image_review_required'])): ?><span class="badge" title="<?=h($m['image_review_reason']??'Revisar imagem')?>" style="background:#fef2f2;color:#b91c1c">Revisar imagem</span><?php endif; ?><span class="badge"><?=h($m['editorial_status']??'Publicável')?></span><?php if(!empty($m['queue_pending_reasons'])): ?><span class="badge" style="background:#fff7ed;color:#c2410c" title="<?=h(implode(' · ',$m['queue_pending_reasons']))?>">Conteúdo incompleto</span><?php endif; ?><?php if(isset($m['editorial_score'])): ?><span class="badge" style="background:#ecfeff;color:#0e7490">Score <?=h($m['editorial_score'])?></span><?php endif; ?><?php if(!empty($m['radar_requested_city']) && $m['radar_requested_city']!==($m['city']??'')): ?><span class="badge" style="background:#eff6ff;color:#1d4ed8">Detectada: <?=h($m['city']??'')?> </span><?php endif; ?><span class="badge"><?=h($m['source']??'Fonte')?></span><h3><?=h($m['title']??'Sem título')?></h3><p><?=h($m['subtitle']??($m['summary']??''))?></p><div class="matter-actions"><?php if(tvs_radar_can_direct_approve($m)): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?=h($m['id'])?>"><button class="btn orange" type="submit">Aprovar</button></form><?php else: ?><a class="btn secondary" href="?edit=<?=h($m['id'])?>">Revisar antes</a><?php endif; ?><a class="btn" href="?edit=<?=h($m['id'])?>">Editar</a><form method="post" onsubmit="return confirm('Descartar esta matéria?');"><?=tvs_csrf_field()?><input type="hidden" name="action" value="discard"><input type="hidden" name="id" value="<?=h($m['id'])?>"><button class="btn secondary" type="submit">Descartar</button></form></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php endforeach; ?>
<?php endif; ?></main></div><script>
document.addEventListener('DOMContentLoaded',function(){
  var all=document.getElementById('select-all-radar');
  if(all){
    all.addEventListener('change',function(){
      document.querySelectorAll('.radar-select').forEach(function(cb){cb.checked=all.checked;});
    });
  }

  var commandStatus=document.getElementById('radar-command-status');
  document.querySelectorAll('form.radar-action-form').forEach(function(form){
    form.addEventListener('submit',function(ev){
      if(form.dataset.submitting==='1'){
        ev.preventDefault();
        return;
      }

      var btn=form.querySelector('button[type="submit"]');
      var action=form.querySelector('input[name="action"]');
      var actionName=action?action.value:'ação';

      form.dataset.submitting='1';
      if(btn){
        btn.dataset.originalLabel=btn.textContent;
        btn.textContent=btn.dataset.busyLabel||'Processando…';
        btn.setAttribute('aria-busy','true');
        btn.disabled=true;
      }
      if(commandStatus){
        commandStatus.textContent='Comando enviado: '+actionName+'. Aguarde a conclusão e o recarregamento da página.';
      }

      // Garante envio nativo mesmo quando o navegador/tema interfere no clique.
      window.setTimeout(function(){
        if(!form.dataset.nativeSubmitted){
          form.dataset.nativeSubmitted='1';
        }
      },0);
    });
  });
});
</script></body></html>