<?php
require_once __DIR__.'/../includes/outbound_guard.php';
$TVS_RADAR_IS_CLI_CRON = defined('TVS_RADAR_CRON') && TVS_RADAR_CRON && PHP_SAPI === 'cli';
if(!$TVS_RADAR_IS_CLI_CRON){
  require_once __DIR__.'/auth.php';
  require_login();
}
require_once dirname(__DIR__).'/config.php';
require_once __DIR__.'/gemini.php';
require_once __DIR__.'/monitor_lib.php';
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
  if($age===null) return ['ok'=>true,'age'=>null,'label'=>'Data não identificada','force_review'=>false];
  $isException=tvs_radar_temporal_exception($cand['title']??'', ($cand['description']??'').' '.($cand['text']??''));
  if($isException) return ['ok'=>true,'age'=>$age,'label'=>$age<=3?'Atual':($age<=15?'Válida por serviço/evento':'Exceção temporal'),'force_review'=>$age>7];
  if($age>15) return ['ok'=>false,'age'=>$age,'label'=>'Antiga +15 dias','force_review'=>true];
  if($age>7) return ['ok'=>true,'age'=>$age,'label'=>'Antiga: revisar','force_review'=>true];
  if($age>3) return ['ok'=>true,'age'=>$age,'label'=>'Esta semana','force_review'=>false];
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
function tvs_radar_candidate_region_ok($cand,$requestedCity,&$reason=''){
  $title=$cand['title']??''; $desc=$cand['description']??'';
  $fact=tvs_radar_fact_text($cand);
  if(tvs_radar_stale_event_signal($title,$desc)){ $reason='Evento antigo/sazonal detectado'; return false; }
  if(tvs_radar_has_outside_city_signal($fact)){ $reason='Fora da região monitorada'; return false; }
  $mentionsAllowed=tvs_radar_text_mentions_allowed_city($fact);
  // Google Notícias só entra se o título/resumo mencionar a cidade monitorada; query/source/url não contam.
  if(tvs_is_google_news_candidate($cand) && !$mentionsAllowed){ $reason='Google News sem cidade monitorada no título/resumo'; return false; }
  // Fonte oficial local pode entrar sem cidade explícita no título, desde que não seja Google/agregador.
  if(!$mentionsAllowed && (tvs_is_google_news_candidate($cand) || !tvs_radar_source_matches_city($cand,$requestedCity))){ $reason='Sem cidade monitorada identificável no fato'; return false; }
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
  if(tvs_radar_has_outside_city_signal($fact)) return 0;

  $score=0;
  // 1) Recorte regional: precisa haver cidade no fato ou fonte oficial local.
  if(tvs_radar_text_mentions_allowed_city($fact)) $score+=25;
  elseif(in_array($city,tvs_radar_allowed_cities(),true)) $score+=10;

  // 2) Qualidade da fonte.
  if(preg_match('~\b(prefeitura|c[aâ]mara|governo\s+sp|defesa\s+civil|secretaria|hospital|ubs|pat)\b~iu',$all)) $score+=14;
  elseif(preg_match('~\b(g1|eptv|cbn|correio|rac|jornal|portal)\b~iu',$all)) $score+=7;

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
  if(!empty($m['image_review_required'])) return false;
  if(!empty($m['sensitive_review_required'])) return false;
  return ($m['review_level']??'')!=='revisao_obrigatoria'
    && ($m['editorial_status']??'')!=='Descartar';
}
function tvs_radar_enforce_queue_rules($save=true){
  $queue=tvs_queue_read(); $new=[]; $removed=0; $changed=0;
  foreach($queue as $q){
    $reason='';
    $cand=['title'=>$q['title']??'','description'=>($q['subtitle']??'').' '.($q['summary']??'').' '.($q['body']??''),'url'=>$q['source_url']??'','source'=>$q['source']??'','source_type'=>$q['source']??'','city'=>$q['city']??''];
    $city=$q['city']??'';
    if(!in_array($city,tvs_radar_allowed_cities(),true)){ $removed++; tvs_radar_log_event($q['title']??'', $q['source']??'', $city, 'DESCARTADA', 'Cidade fora da lista monitorada', $q['source_url']??''); continue; }
    if(!tvs_radar_candidate_region_ok($cand,$city,$reason)){ $removed++; tvs_radar_log_event($q['title']??'', $q['source']??'', $city, 'DESCARTADA', $reason, $q['source_url']??''); continue; }
    $age=$q['age_days']??null;
    $score=tvs_radar_editorial_score($q['title']??'', $city, $q['category']??'', $q['source']??'', ($q['subtitle']??'').' '.($q['summary']??'').' '.($q['body']??''), $q['source_url']??'', is_numeric($age)?(int)$age:null);
    $sensitive=tvs_radar_sensitive_topic($q['title']??'', ($q['subtitle']??'').' '.($q['summary']??'').' '.($q['body']??''));
    $st=tvs_radar_status_from_score($score,$sensitive);
    if($st['review_level']==='descartar'){ $removed++; tvs_radar_log_event($q['title']??'', $q['source']??'', $city, 'DESCARTADA', 'Score editorial insuficiente: '.$score, $q['source_url']??''); continue; }
    if(($q['editorial_score']??null)!==$score || ($q['editorial_status']??'')!==$st['editorial_status']) $changed++;
    $q['editorial_score']=$score; $q['review_level']=$st['review_level']; $q['editorial_status']=$st['editorial_status'];
    $q['sensitive_review_required']=!empty($st['sensitive']) ? 1 : 0;
    if(!empty($st['sensitive'])) $q['sensitive_review_reason']='Pauta sensível ou de alto impacto: revisão humana obrigatória antes da publicação.';
    elseif(isset($q['sensitive_review_reason'])) $q['sensitive_review_reason']='';
    $new[]=$q;
  }
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


function tvs_radar_normalize_title_for_match($title){
  $title=tvs_lower(tvs_clean_text((string)$title));
  $title=preg_replace('~[^\p{L}\p{N}]+~u',' ',$title);
  return trim(preg_replace('~\s+~u',' ',$title));
}

function tvs_radar_title_match_score($expected,$candidate){
  $a=tvs_radar_normalize_title_for_match($expected);
  $b=tvs_radar_normalize_title_for_match($candidate);

  if($a==='' || $b==='') return 0;
  if($a===$b) return 100;

  $wa=array_values(array_unique(array_filter(
    explode(' ',$a),
    static fn($word)=>tvs_strlen($word)>=4
  )));

  $wb=array_values(array_unique(array_filter(
    explode(' ',$b),
    static fn($word)=>tvs_strlen($word)>=4
  )));

  if(!$wa || !$wb) return 0;

  $common=count(array_intersect($wa,$wb));
  $base=max(1,min(count($wa),count($wb)));

  return (int)round(($common/$base)*100);
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
    $urls[]=$domain.'/noticias/'.$citySlug;
    $urls[]=$domain.'/categoria/'.$citySlug;
  }

  $urls[]=$domain;

  return array_values(array_unique($urls));
}


function tvs_radar_is_article_path($url,$title='',$city=''){
  $path=(string)(parse_url((string)$url,PHP_URL_PATH)??'');
  $path=trim($path,'/');

  if($path==='') return false;

  $segments=array_values(array_filter(explode('/',$path)));

  if(count($segments)<2) return false;

  // Páginas de listagem, categoria, cidade, autor, pesquisa ou arquivo.
  if(preg_match(
    '~(?:^|/)(category|categoria|tag|tags|author|autor|search|'
    .'busca|page|pagina|arquivo|archive|editoria|secao|seção|'
    .'cidades?|noticias?|notícias?)(?:/|$)~iu',
    $path
  )){
    return false;
  }

  $last=(string)end($segments);

  if($last==='') return false;

  $citySlug=tvs_slug((string)$city);

  if(
    $citySlug!=='' &&
    (
      $path===$citySlug ||
      $last===$citySlug
    )
  ){
    return false;
  }

  // Slug muito curto tende a ser seção.
  $slugWords=array_values(array_filter(
    preg_split('~[-_]+~',$last)
  ));

  if(count($slugWords)<4){
    return false;
  }

  // O slug precisa manter relação suficiente com o título.
  $slugText=str_replace(['-','_'],' ',$last);
  $slugScore=tvs_radar_title_match_score($title,$slugText);

  return $slugScore>=45;
}

function tvs_radar_validate_resolved_article($url,$expectedTitle,$city=''){
  if(!tvs_radar_external_url_is_valid($url)){
    return [
      'ok'=>false,
      'reason'=>'URL externa inválida'
    ];
  }

  if(!tvs_radar_is_article_path($url,$expectedTitle,$city)){
    return [
      'ok'=>false,
      'reason'=>'URL corresponde a seção, categoria ou página genérica'
    ];
  }

  $article=tvs_extract_article($url,$expectedTitle);

  $articleTitle=trim((string)($article['title']??''));
  $body=trim((string)($article['body']??''));

  $titleScore=tvs_radar_title_match_score(
    $expectedTitle,
    $articleTitle
  );

  if($titleScore<60){
    return [
      'ok'=>false,
      'reason'=>'Título da página não corresponde à pauta',
      'title_score'=>$titleScore
    ];
  }

  if(tvs_strlen($body)<180){
    return [
      'ok'=>false,
      'reason'=>'Página sem conteúdo jornalístico suficiente',
      'title_score'=>$titleScore
    ];
  }

  $cityText=tvs_lower(
    $articleTitle.' '.
    tvs_substr($body,0,1800)
  );

  if(
    trim((string)$city)!=='' &&
    stripos($cityText,tvs_lower((string)$city))===false
  ){
    return [
      'ok'=>false,
      'reason'=>'Cidade esperada não encontrada no artigo',
      'title_score'=>$titleScore
    ];
  }

  return [
    'ok'=>true,
    'article'=>$article,
    'title_score'=>$titleScore
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

    $slugText=str_replace(
      ['-','_'],
      ' ',
      basename($normalizedPath)
    );

    $slugScore=tvs_radar_title_match_score(
      $title,
      $slugText
    );

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

function tvs_radar_resolve_google_news_url($url){
  static $cache=[];

  $url=trim((string)$url);

  if(!tvs_radar_is_google_news_url($url)){
    return $url;
  }

  if(isset($cache[$url])){
    return $cache[$url];
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
    return $cache[$url]=$effective;
  }

  if($html!==''){
    // Redirecionamento por meta refresh.
    if(preg_match(
      '~<meta\b[^>]*http-equiv=["\']?refresh["\']?[^>]*content=["\'][^"\']*url=([^"\']+)["\']~iu',
      $html,
      $m
    )){
      $candidate=trim(html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));

      if(tvs_radar_external_url_is_valid($candidate)){
        return $cache[$url]=$candidate;
      }
    }

    // URL canônica externa.
    if(preg_match_all('~<link\b[^>]*>~is',$html,$tags)){
      foreach($tags[0] as $tag){
        if(
          preg_match('~\brel=["\'][^"\']*canonical[^"\']*["\']~i',$tag) &&
          preg_match('~\bhref=["\']([^"\']+)["\']~i',$tag,$m)
        ){
          $candidate=html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8');

          if(tvs_radar_external_url_is_valid($candidate)){
            return $cache[$url]=$candidate;
          }
        }
      }
    }

    // Último recurso: primeiro endereço externo com aparência de matéria.
    if(preg_match_all(
      '~https?://[^\s"\'<>\\\\]+~iu',
      html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8'),
      $links
    )){
      foreach(array_unique($links[0]) as $candidate){
        $candidate=rtrim($candidate,').,;');

        if(!tvs_radar_external_url_is_valid($candidate)) continue;

        $path=(string)(parse_url($candidate,PHP_URL_PATH)??'');

        if(
          preg_match(
            '~/(noticia|noticias|materia|cidade|politica|economia|'
            .'esporte|cultura|educacao|saude|emprego|concursos?|'
            .'campinas|sumare|hortolandia|paulinia|americana|nova-odessa)/~iu',
            $path
          )
        ){
          return $cache[$url]=$candidate;
        }
      }
    }
  }

  return $cache[$url]=$url;
}

function tvs_radar_resolve_candidate_urls($items){
  foreach($items as &$item){
    $current=trim((string)($item['url']??''));

    if(!tvs_radar_is_google_news_url($current)){
      continue;
    }

    $resolved='';
    $method='';

    /*
     * Primeira tentativa: domínio informado pelo próprio RSS.
     * É o método mais confiável porque limita a pesquisa ao veículo correto.
     */
    $sourceDomain=trim((string)($item['source_domain']??''));

    if($sourceDomain!==''){
      $resolved=tvs_radar_find_article_on_source(
        $sourceDomain,
        $item['title']??'',
        $item['city']??''
      );

      if($resolved!==''){
        $method='source_domain_title_match';
      }
    }

    /*
     * Segunda tentativa: redirecionamento ou dados internos do Google.
     */
    if($resolved===''){
      $googleResolved=tvs_radar_resolve_google_news_url($current);

      if(
        $googleResolved!=='' &&
        $googleResolved!==$current
      ){
        $resolved=$googleResolved;
        $method='google_news_resolution';
      }
    }

    /*
     * Bing permanece apenas como último recurso. A fila nunca é alterada
     * quando a busca não devolve uma correspondência segura.
     */
    if($resolved===''){
      $bingResolved=tvs_radar_resolve_by_bing_news(
        $item['title']??'',
        $item['city']??'',
        $item['source']??''
      );

      if($bingResolved!==''){
        $resolved=$bingResolved;
        $method='bing_news_title_match';
      }
    }

    if(
      $resolved!=='' &&
      $resolved!==$current &&
      tvs_radar_external_url_is_valid($resolved)
    ){
      $item['google_news_url']=$current;
      $item['url']=$resolved;
      $item['source_url']=$resolved;
      $item['url_resolved_at']=date(DATE_ATOM);
      $item['url_resolution_required']=0;
      $item['url_resolution_method']=$method;
    } else {
      $item['url_resolution_required']=1;
    }
  }

  unset($item);

  return $items;
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
        $desc=tvs_clean_text((string)($it->description??$it->summary??''));

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
            'published_at'=>(string)($it->pubDate??$it->published??'')
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
    '~\b(pra[cç]a|revitaliza[cç][aã]o|obra|obras|'
    .'pavimenta[cç][aã]o|recape|ilumina[cç][aã]o|'
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

function tvs_radar_candidates_for_city($city){
  $volumeMode=tvs_radar_is_volume_mode();
  $fontes=tvs_read_json_file(dirname(__DIR__).'/data/fontes.json');
  $items=[]; $officialCount=0;
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
  // Google Notícias por cidade sempre entra como complemento de abastecimento editorial.
  if(count($items)<($volumeMode?60:30)){
    foreach(tvs_radar_google_news($city,$volumeMode?60:36) as $it){ $it['priority']=$volumeMode?4:3; $items[]=$it; }
  }
  $unique=[]; $seen=[];
  foreach($items as $it){
    $url=$it['url']??''; $title=$it['title']??'';
    if(!$url || !$title || isset($seen[$url])) continue;
    if(tvs_is_boilerplate($title)) continue;

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

    $maxAgeDays=tvs_radar_is_volume_mode()?7:3;
    $age=$time['age']??null;

    // Eventos, agendas, campanhas, inscrições e serviços com prazo
    // podem continuar válidos além do corte comum de notícias.
    $temporalLabel=(string)($time['label']??'');
    $isTemporalException=(
      $temporalLabel!=='' &&
      $temporalLabel!=='Atual'
    );

    $effectiveMaxAge=$isTemporalException
      ? 15
      : $maxAgeDays;

    if(
      !$time['ok'] ||
      (is_numeric($age) && (int)$age>$effectiveMaxAge)
    ){
      tvs_radar_log_event(
        $title,
        $it['source']??'Fonte',
        $city,
        'DESCARTADA',
        'Matéria antiga: '
          .(is_numeric($age)?$age:'data desconhecida')
          .' dias; limite aplicado '
          .$effectiveMaxAge
          .' dias'
          .($isTemporalException?' para evento/serviço':''),
        $url
      );
      continue;
    }

    if(
      $isTemporalException &&
      is_numeric($age) &&
      (int)$age>$maxAgeDays
    ){
      $it['force_review']=true;
      $it['temporal_label']=$temporalLabel;
      $it['age_days']=(int)$age;
    }

    if(!is_numeric($age)){
      $it['force_review']=true;
      $it['temporal_label']='Data não confirmada';
      $it['age_days']=null;
    } elseif(!empty($time['force_review'])){
      $it['force_review']=true;
      $it['temporal_label']=$time['label'];
      $it['age_days']=$age;
    } else {
      $it['age_days']=$age;
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

  // Resolve apenas o pequeno conjunto final aprovado pela régua.
  return tvs_radar_resolve_candidate_urls($selected);
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

  // Google News e alguns portais redirecionam/ bloqueiam extração; tentar abrir todos derruba o Radar por timeout.
  // Para esses casos, usamos o RSS como pauta e deixamos a IA/ fallback editorial construir matéria revisável.
  if(!tvs_is_google_news_candidate($cand) && $url){
    $ex=tvs_extract_article($url,$title);
    if(is_array($ex) && (tvs_strlen(($ex['body']??'').($ex['description']??''))>80)){
      $a=$ex;
      if(empty($a['title'])) $a['title']=$title;
    }
  }
  $text=trim(($a['description']??'')."\n\n".($a['body']??''));
  if(tvs_strlen($text)<80) $text=$desc;
  if(tvs_strlen($text)<40) $text=$title;
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
    $article['image']=tvs_best_image('', '', $article['category']??'Cidade');
    $article['image_source_type']=$article['image_source_type']??'category:fallback';
    $article['image_review_required']=1;
    $article['image_review_reason']=$article['image_review_reason']??'Imagem original ausente ou rejeitada; usando imagem ilustrativa da categoria';
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
  $material="CIDADE: {$city}\nCATEGORIA: {$category}\nFONTE: ".($cand['source']??'Fonte consultada')."\nURL: ".($cand['url']??'')."\nTÍTULO ORIGINAL: ".($mat['title']??'')."\n\n".$facts."\n\nCONTEÚDO COMPLETO COLETADO:\n".tvs_substr($mat['text']??'',0,10000);

  // Modo produtivo: itens do Google News com texto curto entram como nota revisável,
  // sem gastar chamada Gemini em cada manchete. Isso aumenta volume sem causar 504.
  $isGoogle = function_exists('tvs_is_google_news_candidate') ? tvs_is_google_news_candidate($cand) : false;
  if($isGoogle && tvs_strlen($mat['text']??'') < 320){
    $result=tvs_build_reviewable_article_without_ai($city,$category,$cand,$mat);
  } else {
    $result=gemini_reporter_article($gemini_api_key??'', $material, ['city'=>$city,'theme'=>$category,'category'=>$category]);
    if(!$result){
      // Se a IA falhar, entra em revisão quando houver material real suficiente.
      $result=tvs_build_reviewable_article_without_ai($city,$category,$cand,$mat);
    }
  }
  if(!$result){
    $result=tvs_build_reviewable_article_without_ai($city,$category,$cand,$mat);
  }
  if(!$result){
    tvs_radar_log_event($cand['title']??'', $cand['source']??'Fonte consultada', $city, 'REVISÃO', 'IA indisponível: enviado para revisão quando houver pauta mínima', $cand['url']??'');
    return null;
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
    $result['image_review_required']=1;
    $result['image_review_reason']='Imagem jornalística da matéria não foi confirmada; revisar antes de publicar.';
    if($result['image_source_type']==='') $result['image_source_type']='category:fallback';
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

  if(!empty($result['image_review_required'])){
    $result['review_level']='precisa_revisao';
    $result['editorial_status']='Revisão de imagem';
  }
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
function tvs_radar_update_queue($perCity=15,$mode='normal'){
  // HostGator/nginx pode retornar 504 quando uma requisição fica muito tempo processando.
  // O Radar agora processa em micro-lotes curtos. Clique em Atualizar Agora mais de uma vez se quiser abastecer mais.
  global $TVS_RADAR_MODE;
  $oldMode=$TVS_RADAR_MODE ?? 'normal';
  $TVS_RADAR_MODE=$mode==='volume'?'volume':'normal';
  @set_time_limit(tvs_radar_is_volume_mode()?55:38);
  $started=microtime(true);
  $queue=tvs_queue_read(); $seen=[]; $count=0;
  foreach($queue as $q){ if(!empty($q['source_url'])) $seen[$q['source_url']]=1; }
  global $cities;
  foreach($cities as $city){
    if((microtime(true)-$started)>(tvs_radar_is_volume_mode()?46:30)) break;
    $cityCount=0; foreach($queue as $q){ if(($q['city']??'')===$city && ($q['status']??'aguardando')==='aguardando') $cityCount++; }
    if($cityCount>=$perCity) continue;
    $attempts=0;
    foreach(tvs_radar_candidates_for_city($city) as $cand){
      $attempts++;
      if($attempts>(tvs_radar_is_volume_mode()?54:24)) break;
      if($cityCount>=$perCity) break;
      $url=$cand['url']??''; if(!$url || isset($seen[$url])) continue;
      $article=tvs_generate_ready_article($city,$cand);
      if(is_array($article) && !empty($article['title']) && !empty($article['body'])){ $queue[]=$article; tvs_radar_log_event($article['title']??'', $article['source']??($cand['source']??'Fonte'), $city, ($article['editorial_status']??'APROVADA'), 'Entrou na fila editorial', $url); $seen[$url]=1; $cityCount++; $count++; } else { $seen[$url]=1; }
      if($count>=(tvs_radar_is_volume_mode()?72:36) || (microtime(true)-$started)>(tvs_radar_is_volume_mode()?49:32)) break 2; // proteção contra timeout em hospedagem compartilhada
    }
  }
  tvs_queue_save($queue); tvs_radar_enforce_queue_rules(true); $TVS_RADAR_MODE=$oldMode; return $count;
}
function tvs_publish_from_queue($id,$post){
  global $newsFile;
  $queue=tvs_queue_read(); $found=null; $newq=[];
  foreach($queue as $item){ if(($item['id']??'')===$id) $found=$item; else $newq[]=$item; }
  if(!$found) return false;

  // Proteção real no servidor: matéria com imagem pendente não pode ser
  // publicada até que o editor salve uma imagem válida.
  if(!empty($found['image_review_required'])) return false;

  $title=trim($post['title']??$found['title']??'');
  $body=trim($post['body']??$found['body']??'');
  if($title==='' || $body==='') return false;
  $news=tvs_read_json_file($newsFile); if(!is_array($news)) $news=[];
  $news[]=['id'=>uniqid('news_'),'title'=>$title,'subtitle'=>trim($post['subtitle']??$found['subtitle']??''),'summary'=>trim($post['summary']??$found['summary']??''),'body'=>$body,'category'=>trim($post['category']??$found['category']??'Cidade'),'city'=>trim($post['city']??$found['city']??'Região'),'source'=>trim($post['source']??$found['source']??'Fonte consultada'),'source_url'=>trim($post['source_url']??$found['source_url']??''),'image'=>tvs_best_image('', trim($post['image']??$found['image']??''), trim($post['category']??$found['category']??'Cidade')) ,'image_credit'=>trim($post['image_credit']??$found['image_credit']??tvs_image_credit_from_source($found['source']??$post['source']??'Fonte consultada', $found['image']??$post['image']??'')),'tags'=>is_array($found['tags']??null)?$found['tags']:array_filter(array_map('trim',explode(',',(string)($post['tags']??'')))),'seo_title'=>trim($post['seo_title']??$found['seo_title']??$title),'meta_description'=>trim($post['meta_description']??$found['meta_description']??''),'slug'=>trim($post['slug']??$found['slug']??tvs_slug($title)),'instagram_caption'=>trim($post['instagram_caption']??$found['instagram_caption']??''),'whatsapp_text'=>trim($post['whatsapp_text']??$found['whatsapp_text']??''),'views'=>0,'shares'=>0,'published_at'=>date('c'),'created_at'=>date('c')];
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
    $st=['last_run'=>date('c'),'last_mode'=>'manual','last_generated'=>$n,'last_message'=>$n>0?"{$n} matéria(s) pronta(s) para aprovação.":'Nenhuma matéria entrou na fila agora.'];
    tvs_radar_save_status($st);
    $notice=$n>0 ? "Radar atualizado manualmente: {$n} matéria(s) pronta(s) para aprovação." : 'Radar atualizado manualmente. Nenhuma matéria entrou na fila agora. Verifique fontes, chave Gemini e pautas descartadas para os motivos.';
  } elseif($action==='update_radar_volume'){
    $cfg=tvs_radar_config();
    $perCity=max(25,min(60,(int)($cfg['per_city']??25)));
    $n=tvs_radar_update_queue($perCity,'volume');
    $st=['last_run'=>date('c'),'last_mode'=>'volume_maximo','last_generated'=>$n,'last_message'=>$n>0?"{$n} pauta(s) entraram em revisão no Modo Volume Máximo.":'Nenhuma nova pauta entrou no Modo Volume Máximo.'];
    tvs_radar_save_status($st);
    $notice=$n>0 ? "Modo Volume Máximo executado: {$n} pauta(s) enviada(s) para revisão." : 'Modo Volume Máximo executado. Nenhuma nova pauta entrou agora.';
  } elseif($action==='save_settings'){
    $cfg=tvs_radar_config();
    $cfg['auto_daily']=!empty($_POST['auto_daily']);
    $cfg['per_city']=max(1,min(40,(int)($_POST['per_city']??20)));
    tvs_radar_save_config($cfg);
    $notice='Configurações do Radar salvas.';
  } elseif($action==='approve'){
    if(tvs_publish_from_queue($_POST['id']??'',$_POST)){ header('Location: noticias.php?published=1'); exit; }
    else $error='Não foi possível aprovar/publicar. Confira título e texto.';
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
    $notice='Edição salva. Agora você pode aprovar quando quiser.';
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
$byCity=[]; foreach($cities as $c) $byCity[$c]=[];
$sensitiveQueue=[]; $imageReviewQueue=[]; $normalQueue=[];
foreach($queue as $q){
  if(($q['status']??'aguardando')!=='aguardando') continue;
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
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Matérias para Aprovação | TV Sumaré</title><link rel="stylesheet" href="admin.css?v=132"><style>.queue-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.matter{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:14px;box-shadow:0 8px 22px rgba(15,23,42,.06)}.matter img{width:100%;height:150px;object-fit:cover;border-radius:14px;background:#eef2ff}.matter h3{margin:10px 0 6px;font-size:18px}.matter p{color:#475569;font-size:14px}.badge{display:inline-flex;border-radius:999px;background:#eef2ff;color:#1d4ed8;padding:5px 9px;font-size:12px;font-weight:800;margin:6px 5px 6px 0}.city-block{margin:24px 0}.matter-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.edit-form{background:#fff;border-radius:18px;padding:18px;border:1px solid #e5e7eb}.edit-form input,.edit-form textarea,.edit-form select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:12px;margin:5px 0 12px}.edit-form textarea{min-height:320px}.muted{color:#64748b}.settings-box{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:14px;margin:14px 0}.settings-inline{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.settings-inline label{display:flex;flex-direction:column;font-size:13px;color:#334155}.settings-inline input[type=number]{width:110px;padding:10px;border:1px solid #cbd5e1;border-radius:12px}.settings-inline .check{flex-direction:row;gap:8px;align-items:center}.top-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.bulk-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.bulk-row .check,.bulk-check{display:flex;align-items:center;gap:7px;font-weight:800;color:#334155}.bulk-check{margin-bottom:8px}.bulk-check input{width:18px;height:18px}@media(max-width:1000px){.queue-grid{grid-template-columns:1fr}.matter img{height:190px}}</style></head><body><div class="admin"><?php include __DIR__.'/_menu.php'; ?><main class="main"><div class="top"><div><span class="eyebrow">Centro de Redação • Radar 2.0</span><h1>Matérias para Aprovação</h1><p class="muted">O Radar abastece a redação com mais opções. Você aprova o que achar relevante para a TV Sumaré.</p></div><div class="top-actions"><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="update_radar"><button class="btn orange" type="submit" onclick="return confirm('Atualizar o Radar agora? Isso pode levar alguns segundos.');">Atualizar Agora</button></form><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="update_radar_volume"><button class="btn secondary" type="submit" onclick="return confirm('Ativar Modo Volume Máximo? Mais pautas entrarão como revisão humana, não como publicação automática.');">Modo Volume Máximo</button></form></div></div>
<div class="settings-box"><form method="post" class="settings-inline"><?=tvs_csrf_field()?><input type="hidden" name="action" value="save_settings"><label class="check"><input type="checkbox" name="auto_daily" value="1" <?=!empty($radarCfg['auto_daily'])?'checked':''?>> Atualização automática diária</label><label>Meta de matérias por cidade<input type="number" min="1" max="40" name="per_city" value="<?=h($radarCfg['per_city']??20)?>"></label><button class="btn secondary" type="submit">Salvar configuração</button><span class="muted">Última atualização: <?=!empty($radarStatus['last_run'])?h(date('d/m/Y H:i',strtotime($radarStatus['last_run']))):'ainda não executada'?> <?=!empty($radarStatus['last_mode'])?'• '.h($radarStatus['last_mode']):''?></span></form><div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px"><form method="post" onsubmit="return confirm('Remover automaticamente matérias realmente inválidas como menu, rodapé e texto genérico?');"><?=tvs_csrf_field()?><input type="hidden" name="action" value="clean_invalid"><button class="btn secondary" type="submit">Limpar matérias inválidas</button></form><form method="post" onsubmit="return confirm('Tentar reprocessar pautas descartadas e devolver o que for aproveitável para revisão?');"><?=tvs_csrf_field()?><input type="hidden" name="action" value="reprocess_discarded"><button class="btn secondary" type="submit">Reprocessar descartadas</button></form><form method="post" onsubmit="return confirm('Reprocessar mais descartadas com régua flexível? Tudo voltará como revisão humana.');"><?=tvs_csrf_field()?><input type="hidden" name="action" value="reprocess_discarded_volume"><button class="btn secondary" type="submit">Reprocessar em volume</button></form><span class="muted">Use volume quando precisar abastecer a redação; aprove apenas o que estiver bom.</span></div></div>
<?php if($notice): ?><div class="notice"><?=h($notice)?></div><?php endif; ?><?php if($error): ?><div class="notice error"><?=h($error)?></div><?php endif; ?>
<?php if($editItem): $tags=is_array($editItem['tags']??null)?implode(', ',$editItem['tags']):($editItem['tags']??''); ?>
<section class="edit-form"><h2>Editar matéria antes de aprovar</h2><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="save_edit"><input type="hidden" name="id" value="<?=h($editItem['id'])?>"><label>Título</label><input name="title" value="<?=h($editItem['title']??'')?>"><label>Subtítulo</label><input name="subtitle" value="<?=h($editItem['subtitle']??'')?>"><label>Resumo</label><input name="summary" value="<?=h($editItem['summary']??'')?>"><label>Cidade</label><input name="city" value="<?=h($editItem['city']??'')?>"><label>Categoria</label><input name="category" value="<?=h($editItem['category']??'')?>"><label>Imagem</label><input name="image" value="<?=h($editItem['image']??'')?>"><label>Crédito da imagem</label><input name="image_credit" value="<?=h($editItem['image_credit']??'')?>"><label>Texto completo</label><textarea name="body"><?=h($editItem['body']??'')?></textarea><label>Fonte</label><input name="source" value="<?=h($editItem['source']??'')?>"><label>URL da fonte</label><input name="source_url" value="<?=h($editItem['source_url']??'')?>"><label>Tags</label><input name="tags" value="<?=h($tags)?>"><label>SEO title</label><input name="seo_title" value="<?=h($editItem['seo_title']??'')?>"><label>Meta description</label><input name="meta_description" value="<?=h($editItem['meta_description']??'')?>"><label>Slug</label><input name="slug" value="<?=h($editItem['slug']??'')?>"><label>Legenda Instagram</label><textarea name="instagram_caption" style="min-height:120px"><?=h($editItem['instagram_caption']??'')?></textarea><label>Texto WhatsApp</label><textarea name="whatsapp_text" style="min-height:100px"><?=h($editItem['whatsapp_text']??'')?></textarea><div class="matter-actions"><button class="btn" type="submit">Salvar edição</button></form><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?=h($editItem['id'])?>"><button class="btn orange" type="submit">Aprovar e publicar</button></form><a class="btn secondary" href="radar-regional.php">Voltar</a></div></section>
<?php else: ?>
<?php $discarded=tvs_read_json_file(dirname(__DIR__).'/data/pautas_descartadas.json'); ?><div class="cards"><div class="stat"><span>Fila comum</span><b><?=count($normalQueue)?></b><small>matérias aguardando decisão</small></div><div class="stat"><span>Revisão obrigatória</span><b><?=count($sensitiveQueue)?></b><small>pautas sensíveis ou de alto impacto</small></div><div class="stat"><span>Revisão de imagem</span><b><?=count($imageReviewQueue)?></b><small>imagem precisa ser confirmada</small></div><div class="stat"><span>Pautas descartadas</span><b><?=count($discarded)?></b><small>institucionais, duplicadas ou fora da região</small></div></div>
<?php if($sensitiveQueue): ?><section class="city-block"><h2>Revisão obrigatória <small class="muted">(<?=count($sensitiveQueue)?>)</small></h2><div class="queue-grid"><?php foreach($sensitiveQueue as $m): ?><article class="matter"><span class="badge" style="background:#fef2f2;color:#b91c1c">Revisão obrigatória</span><span class="badge"><?=h($m['editorial_status']??'Revisão')?></span><?php if(isset($m['editorial_score'])): ?><span class="badge">Score <?=h($m['editorial_score'])?></span><?php endif; ?><h3><?=h($m['title']??'Sem título')?></h3><p><?=h($m['subtitle']??($m['summary']??''))?></p><a class="btn orange" href="?edit=<?=h($m['id'])?>">Revisar</a></article><?php endforeach; ?></div></section><?php endif; ?>
<?php if($imageReviewQueue): ?><section class="city-block"><h2>Revisão de imagem <small class="muted">(<?=count($imageReviewQueue)?>)</small></h2><div class="queue-grid"><?php foreach($imageReviewQueue as $m): ?><article class="matter"><span class="badge" style="background:#fff7ed;color:#c2410c">Imagem pendente</span><h3><?=h($m['title']??'Sem título')?></h3><p><?=h($m['image_review_reason']??'Revisar imagem antes da publicação.')?></p><a class="btn orange" href="?edit=<?=h($m['id'])?>">Corrigir imagem</a></article><?php endforeach; ?></div></section><?php endif; ?>
<form id="bulk-form" method="post" class="settings-box bulk-row" onsubmit="return confirm('Aplicar a ação nas matérias selecionadas?');"><?=tvs_csrf_field()?><label class="check"><input type="checkbox" id="select-all-radar"> Selecionar todas visíveis</label><button class="btn orange" type="submit" name="action" value="bulk_approve">Aprovar selecionadas</button><button class="btn secondary" type="submit" name="action" value="bulk_review">Enviar para revisão</button><button class="btn secondary" type="submit" name="action" value="bulk_discard">Descartar selecionadas</button><span class="muted">Use os checkboxes dos cards para operar várias matérias de uma vez.</span></form>
<?php foreach($cities as $city): $items=array_slice($byCity[$city]??[],0,20); ?>
<section class="city-block"><h2><?=h($city)?> <small class="muted">(<?=count($items)?>)</small></h2><?php if(!$items): ?><p class="muted">Nenhuma matéria aguardando aprovação para esta cidade.</p><?php else: ?><div class="queue-grid"><?php foreach($items as $m): ?><article class="matter"><label class="bulk-check"><input type="checkbox" class="radar-select" form="bulk-form" name="ids[]" value="<?=h($m['id'])?>"> Selecionar</label><img src="<?=h(tvs_admin_img($m['image']??'', $m['category']??'Cidade'))?>" onerror="this.src='<?=h(tvs_admin_img('', $m['category']??'Cidade'))?>'" alt=""><?php if(!empty($m['image_credit'])): ?><small class="muted" style="display:block;margin:4px 0 8px"><?=h($m['image_credit'])?></small><?php endif; ?><span class="badge"><?=h($m['category']??'Cidade')?></span><?php if(($m['review_level']??'')==='precisa_revisao'): ?><span class="badge" style="background:#fff7ed;color:#c2410c">Precisa revisão</span><?php endif; ?><?php if(!empty($m['image_review_required'])): ?><span class="badge" title="<?=h($m['image_review_reason']??'Revisar imagem')?>" style="background:#fef2f2;color:#b91c1c">Revisar imagem</span><?php endif; ?><span class="badge"><?=h($m['editorial_status']??'Publicável')?></span><?php if(isset($m['editorial_score'])): ?><span class="badge" style="background:#ecfeff;color:#0e7490">Score <?=h($m['editorial_score'])?></span><?php endif; ?><?php if(!empty($m['radar_requested_city']) && $m['radar_requested_city']!==($m['city']??'')): ?><span class="badge" style="background:#eff6ff;color:#1d4ed8">Detectada: <?=h($m['city']??'')?> </span><?php endif; ?><span class="badge"><?=h($m['source']??'Fonte')?></span><h3><?=h($m['title']??'Sem título')?></h3><p><?=h($m['subtitle']??($m['summary']??''))?></p><div class="matter-actions"><?php if(tvs_radar_can_direct_approve($m)): ?><form method="post"><?=tvs_csrf_field()?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?=h($m['id'])?>"><button class="btn orange" type="submit">Aprovar</button></form><?php else: ?><a class="btn secondary" href="?edit=<?=h($m['id'])?>">Revisar antes</a><?php endif; ?><a class="btn" href="?edit=<?=h($m['id'])?>">Editar</a><form method="post" onsubmit="return confirm('Descartar esta matéria?');"><?=tvs_csrf_field()?><input type="hidden" name="action" value="discard"><input type="hidden" name="id" value="<?=h($m['id'])?>"><button class="btn secondary" type="submit">Descartar</button></form></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php endforeach; ?>
<?php endif; ?></main></div><script>document.addEventListener('DOMContentLoaded',function(){var all=document.getElementById('select-all-radar');if(!all)return;all.addEventListener('change',function(){document.querySelectorAll('.radar-select').forEach(function(cb){cb.checked=all.checked;});});});</script></body></html>
