<?php
require_once __DIR__.'/outbound_guard.php';
// TV Sumaré Enterprise 1.1-A — Assistente de Produção IA
// Helper único para sugestões, roteiro, fila, HeyGen e publicação no TV Play.

if (!function_exists('tvp_root')) {
  function tvp_root(){ return dirname(__DIR__); }
  function tvp_data_path($file){ return tvp_root().'/data/'.$file; }
  function tvp_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
  function tvp_read_json($file){ $p=tvp_data_path($file); if(!file_exists($p)) return []; $d=json_decode((string)@file_get_contents($p), true); return is_array($d)?$d:[]; }
  function tvp_write_json($file,$data){ $p=tvp_data_path($file); if(!is_dir(dirname($p))) @mkdir(dirname($p),0775,true); @file_put_contents($p,json_encode(array_values($data),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
  function tvp_clean($s){ $s=strip_tags((string)$s); $s=html_entity_decode($s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); return preg_replace('/\s+/u',' ',trim($s)); }
  function tvp_strlen($s){ return function_exists('mb_strlen') ? mb_strlen((string)$s,'UTF-8') : strlen((string)$s); }
  function tvp_substr($s,$a,$b=null){ return function_exists('mb_substr') ? mb_substr((string)$s,$a,$b,'UTF-8') : substr((string)$s,$a,$b); }
  function tvp_value($a,$keys,$fallback=''){ foreach((array)$keys as $k){ if(isset($a[$k]) && trim((string)$a[$k])!=='') return $a[$k]; } return $fallback; }
  function tvp_site_url(){ $u=trim((string)($GLOBALS['site_url']??'')); return rtrim($u ?: 'https://tvsumare.com.br','/'); }
  function tvp_abs_url($u){ $u=trim((string)$u); if($u==='') return ''; if(preg_match('~^https?://~i',$u)) return $u; return tvp_site_url().'/'.ltrim($u,'/'); }
}

if (!function_exists('tvp_news_id')) {
  function tvp_news_id($n){ return (string)tvp_value($n,['id','news_id','codigo'], md5(json_encode($n))); }
  function tvp_news_title($n){ return tvp_clean(tvp_value($n,['title','titulo'],'Sem título')); }
  function tvp_news_city($n){ return tvp_clean(tvp_value($n,['city','cidade'],'Região')); }
  function tvp_news_category($n){ return tvp_clean(tvp_value($n,['category','categoria'],'Notícia')); }
  function tvp_news_body($n){ return tvp_clean(tvp_value($n,['body','texto','content','texto_completo','summary','resumo','subtitle','subtitulo'],'')); }
  function tvp_news_source($n){ return tvp_clean(tvp_value($n,['source','fonte'],'Fonte consultada')); }
  function tvp_news_image($n){ return trim((string)tvp_value($n,['image','imagem','thumb','thumbnail'],'assets/cat-cidade.svg')); }
}

if (!function_exists('tvp_video_score')) {
  function tvp_text_lc($s){ return function_exists('mb_strtolower') ? mb_strtolower((string)$s,'UTF-8') : strtolower((string)$s); }
  function tvp_news_age_days($n){
    $raw = tvp_value($n,['published_at','created_at','date','data','updated_at'],'');
    $ts = $raw ? strtotime((string)$raw) : 0;
    if(!$ts) return 0;
    return max(0,(int)floor((time()-$ts)/86400));
  }
  function tvp_is_sensitive_topic($job){
    $txt=tvp_text_lc(($job['title']??'').' '.($job['category']??'').' '.($job['script']??'').' '.($job['body']??'').' '.($job['summary']??''));
    if(preg_match('~suic[ií]dio|feminic[ií]dio|homic[ií]dio|assassinato|latroc[ií]nio|estupro|abuso\s+sexual|viol[eê]ncia\s+dom[eé]stica|opera[cç][aã]o\s+policial|crime\s+organizado|tr[aá]fico|pris[aã]o|preso~u',$txt)) return true;
    if(preg_match('~(morte|morre|morreu|morto|morta|agress[aã]o|mordidas?|viol[eê]ncia|investiga[cç][aã]o).*(crian[cç]a|beb[eê]|adolescente|menor)~u',$txt)) return true;
    if(preg_match('~(crian[cç]a|beb[eê]|adolescente|menor).*(morte|morre|morreu|morto|morta|agress[aã]o|mordidas?|viol[eê]ncia|investiga[cç][aã]o)~u',$txt)) return true;
    return false;
  }
  function tvp_extract_vagas_number($text){
    $t=tvp_text_lc(tvp_clean((string)$text));
    if(preg_match('~(\d+(?:[\.,]\d+)?)\s*(mil)\s+vagas~u',$t,$m)) return (int)(floatval(str_replace(',','.',$m[1]))*1000);
    if(preg_match('~(\d+)\s+vagas~u',$t,$m)) return (int)$m[1];
    return 0;
  }
  function tvp_video_score($n){
    $title=tvp_news_title($n); $body=tvp_news_body($n); $cat=tvp_news_category($n); $source=tvp_news_source($n); $city=tvp_news_city($n);
    $txt = tvp_text_lc($title.' '.$body.' '.$cat);
    $all = tvp_text_lc($title.' '.$body.' '.$cat.' '.$source.' '.$city);
    $score = 0;

    if(preg_match('~sumar[eé]|hortol[aâ]ndia|paul[ií]nia|nova\s+odessa|americana|campinas~u',$all)) $score += 22;
    if(preg_match('~prefeitura|c[aâ]mara|governo|secretaria|pat|hospital|defesa civil|g1|eptv|cbn|jornal|portal~u',$all)) $score += 10;

    if(preg_match('~emprego|vagas|processo seletivo|concurso|inscri[cç][oõ]es abertas|pat|recrutamento|capacita[cç][aã]o|curso profissionalizante~u',$txt)) $score += 22;
    if(preg_match('~investimento|empresa|ind[uú]stria|com[eé]rcio|economia|empreendedor|neg[oó]cios|petrobras|replan|mercado livre|contrata[cç][oõ]es|desenvolvimento econ[oô]mico~u',$txt)) $score += 18;
    if(preg_match('~sa[uú]de|vacina|hospital|upa|dengue|mutir[aã]o|atendimento|campanha de sa[uú]de~u',$txt)) $score += 20;
    if(preg_match('~educa[cç][aã]o|escola|creche|alunos|matr[ií]cula|fies|faculdade|universidade|unicamp~u',$txt)) $score += 18;
    if(preg_match('~obra|tr[aâ]nsito|interdi[cç][aã]o|transporte|[aá]gua|energia|servi[cç]o|defesa civil|estiagem~u',$txt)) $score += 15;
    if(preg_match('~festival|show|evento|cultura|esporte|programa[cç][aã]o~u',$txt)) $score += 10;
    if(preg_match('~pol[ií]cia|pris[aã]o|preso|opera[cç][aã]o|acidente|homic[ií]dio|assassinato|tr[aá]fico~u',$txt)) $score += 3;

    $vagas=tvp_extract_vagas_number($txt);
    if($vagas>=1500) $score+=22; elseif($vagas>=500) $score+=14; elseif($vagas>=100) $score+=6; elseif($vagas>0) $score+=3;
    if(preg_match('~abre|anuncia|oferece|realiza|lan[cç]a|divulga|inscri[cç][oõ]es|recrutamento|feir[aã]o|mutir[aã]o|campanha|edital|atendimento~u',$txt)) $score += 8;

    $age=tvp_news_age_days($n);
    if($age<=1) $score+=14; elseif($age<=3) $score+=10; elseif($age<=7) $score+=4; elseif($age<=15) $score-=15; elseif($age>15) $score-=45;
    $len = tvp_strlen($body);
    if($len>900) $score += 5; elseif($len<160) $score -= 12;
    if(tvp_is_sensitive_topic($n)) $score = min($score,45);
    return max(0,min(100,$score));
  }
  function tvp_video_priority($score){ if($score>=85) return 'prioridade_maxima'; if($score>=70) return 'destaque'; if($score>=50) return 'publicavel'; if($score>=30) return 'revisao'; return 'baixa'; }
  function tvp_avatar_profile_for_category($category){
    $c=tvp_text_lc($category);
    if(preg_match('~emprego|economia|negócio|negocio|empresa|investimento|comércio|comercio|indústria|industria~u',$c)) return 'negocios_empregos';
    if(preg_match('~saúde|saude|educação|educacao|obras|mobilidade|serviço|servico|defesa civil~u',$c)) return 'servicos_publicos';
    return 'cristian_editor';
  }
  function tvp_video_engine_decide($item,$requested='auto'){
    $requested=tvp_text_lc(trim((string)$requested));
    if(in_array($requested,['veo','heygen'],true)) return $requested;
    $txt=tvp_text_lc(($item['title']??'').' '.($item['category']??'').' '.($item['summary']??'').' '.($item['editorial_style']??''));
    if(preg_match('~últimas? notícias|ultimas? noticias|última hora|ultima hora|boletim|apresentador|apresentadora|âncora|ancora|repórter|reporter|avatar~u',$txt)) return 'heygen';
    return 'veo';
  }
  function tvp_video_engine_label($engine){ return $engine==='heygen'?'HeyGen • apresentador':'VEO • vídeo visual'; }
}

if (!function_exists('tvp_load_video_jobs')) {
  function tvp_load_video_jobs(){ return tvp_read_json('videos_ia.json'); }
  function tvp_save_video_jobs($jobs){ tvp_write_json('videos_ia.json',$jobs); }
  function tvp_find_job($id,&$jobs=null,&$idx=null){ $jobs=tvp_load_video_jobs(); foreach($jobs as $i=>$j){ if(($j['id']??'')===$id){ $idx=$i; return $j; } } return null; }
  function tvp_job_exists_for_news($newsId){ foreach(tvp_load_video_jobs() as $j){ if(($j['news_id']??'')===$newsId && !in_array(($j['status']??''),['cancelado','erro_descartado'],true)) return true; } return false; }
  function tvp_create_video_job($news,$origin='manual',$suggested=false,$requestedEngine='auto'){
    $newsId=tvp_news_id($news); if($newsId==='' || tvp_job_exists_for_news($newsId)) return ['ok'=>false,'error'=>'Essa notícia já está na fila de vídeos.'];
    $score=tvp_video_score($news);
    $engine=tvp_video_engine_decide($news,$requestedEngine);
    $job=[
      'id'=>'vjob_'.date('YmdHis').'_'.substr(md5($newsId.microtime(true)),0,6),
      'news_id'=>$newsId,
      'title'=>tvp_news_title($news),
      'city'=>tvp_news_city($news),
      'category'=>tvp_news_category($news),
      'source'=>tvp_news_source($news),
      'source_url'=>tvp_value($news,['source_url','url','link'],''),
      'image'=>tvp_news_image($news),
      'summary'=>tvp_clean(tvp_value($news,['summary','resumo','subtitle','subtitulo'],'')),
      'body'=>tvp_news_body($news),
      'published_at'=>tvp_value($news,['published_at','created_at','date','data','updated_at'],''),
      'score'=>$score,
      'priority'=>tvp_video_priority($score),
      'video_engine'=>$engine,
      'presenter_required'=>$engine==='heygen',
      'presenter_profile'=>$engine==='heygen'?tvp_avatar_profile_for_category(tvp_news_category($news)):'',
      'origin'=>$origin,
      'status'=>$suggested?'sugerido':'roteiro_pendente',
      'script'=>'',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    $jobs=tvp_load_video_jobs(); array_unshift($jobs,$job); tvp_save_video_jobs($jobs); return ['ok'=>true,'job'=>$job];
  }
  function tvp_generate_top_suggestions($limit=3){
    $news=tvp_read_json('noticias.json');
    usort($news,function($a,$b){ return tvp_video_score($b)<=>tvp_video_score($a); });
    $made=0; $errors=[];
    foreach($news as $n){ if($made>=$limit) break; $score=tvp_video_score($n); if($score<75 || tvp_is_sensitive_topic($n)) continue; if(tvp_job_exists_for_news(tvp_news_id($n))) continue; $r=tvp_create_video_job($n,'sugestao_ia',true); if($r['ok']) $made++; else $errors[]=$r['error']; }
    return ['ok'=>true,'created'=>$made,'errors'=>$errors];
  }
}

if (!function_exists('tvp_generate_script')) {
  function tvp_presenter_label($profile){
    if($profile==='negocios_empregos') return 'Repórter 1';
    if($profile==='servicos_publicos') return 'Repórter 2';
    return 'Cristian Schibelsky — Editor Responsável';
  }
  function tvp_script_opening_for_profile($profile,$cat){
    if($profile==='negocios_empregos') return 'Boa noite. O boletim de Empregos e Negócios da TV Sumaré começa com uma oportunidade para quem busca trabalho na região.';
    if($profile==='servicos_publicos') return 'Boa noite. A TV Sumaré traz uma atualização de serviço público para moradores da região.';
    return 'Boa noite. A TV Sumaré acompanha os principais fatos de Sumaré e da região.';
  }
  function tvp_polish_script($script,$job){
    $script=tvp_clean($script);
    // Remove aberturas artificiais típicas de IA e frases de atendimento.
    $bad=[
      'É um prazer ter você conosco na TV Sumaré.',
      'E um prazer ter você conosco na TV Sumaré.',
      'É um prazer ter você conosco.',
      'E um prazer ter você conosco.',
      'Olá, telespectadores.',
      'Boa noite, telespectadores da TV Sumaré.'
    ];
    $script=str_replace($bad,'',$script);
    $script=preg_replace('/\s+/u',' ',trim($script));
    $profile=$job['presenter_profile']??tvp_avatar_profile_for_category($job['category']??'');
    // Evita roteiro que começa truncado direto pelo título, como "Sumaré com duas mil vagas...".
    if(!preg_match('/^(Boa noite|Olá|A TV Sumaré|Confira|Nesta edição)/iu',$script)){
      $script=tvp_script_opening_for_profile($profile,$job['category']??'').' '.$script;
    }
    if(stripos($script,'Cristian Schibelsky')===false){
      $script.=' Edição: Cristian Schibelsky, Editor Responsável da TV Sumaré.';
    }
    return tvp_clean($script);
  }
  function tvp_local_script($job){
    $title=tvp_clean($job['title']??'Atualização regional');
    $city=tvp_clean($job['city']??'região');
    $cat=tvp_clean($job['category']??'notícias');
    $source=tvp_clean($job['source']??'fonte consultada');
    $profile=$job['presenter_profile']??tvp_avatar_profile_for_category($cat);
    $txt=tvp_text_lc($title.' '.$cat.' '.($job['body']??'').' '.($job['summary']??''));
    if($profile==='negocios_empregos'){
      $script="Boa noite. A TV Sumaré traz uma notícia importante para quem procura uma oportunidade no mercado de trabalho.\n\n".
        "{$title}.\n\n".
        "A informação envolve {$city} e reforça o movimento de geração de emprego, renda e desenvolvimento regional.\n\n".
        "Segundo informações de {$source}, os interessados devem acompanhar os canais oficiais para confirmar prazos, requisitos, documentos e formas de atendimento.\n\n".
        "A TV Sumaré segue acompanhando as oportunidades de emprego e negócios em toda a região.\n\n".
        "Eu sou Cristian Schibelsky. Até o próximo boletim.";
      return tvp_clean($script);
    }
    if($profile==='servicos_publicos'){
      $script="Boa noite. A TV Sumaré traz uma atualização de serviço público para moradores de {$city}.\n\n".
        "{$title}.\n\n".
        "O assunto está ligado à área de {$cat} e pode impactar diretamente a rotina da população.\n\n".
        "Segundo informações de {$source}, moradores devem acompanhar os canais oficiais para novas orientações, datas, locais de atendimento e demais detalhes.\n\n".
        "Eu sou Cristian Schibelsky. Até o próximo boletim.";
      return tvp_clean($script);
    }
    return tvp_clean("Boa noite. A TV Sumaré acompanha os principais fatos de {$city} e região.\n\n{$title}.\n\nSegundo informações de {$source}, o tema integra a cobertura regional da TV Sumaré e deve ser acompanhado pelos moradores.\n\nEu sou Cristian Schibelsky. Até o próximo boletim.");
  }
  function tvp_core_generate_text($prompt){
    $base=rtrim((string)(getenv('CENTRO_IA_URL')?:''),'/');
    $token=trim((string)(getenv('CENTRO_IA_INTERNAL_TOKEN')?:''));
    if($base==='' || $token==='') return ['ok'=>false,'error'=>'Core IA não configurado.'];
    $url=$base.'/api/internal/centro-ia/execute';
    $opts=tvs_outbound_curl_options($url,45);
    if($opts===null) return ['ok'=>false,'error'=>'Core IA bloqueado pela política de saída.'];
    $payload=['project_id'=>'tvsumare','capability'=>'editorial_generation','input'=>['user'=>(string)$prompt,'temperature'=>0.18]];
    $ch=curl_init($url);
    curl_setopt_array($ch,$opts+[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_POST=>true,
      CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','Authorization: Bearer '.$token,'X-Vitrine-Project: tvsumare'],
      CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    ]);
    $res=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($res===false || $res==='') return ['ok'=>false,'http'=>$http,'error'=>'Core IA sem resposta. '.$err];
    $json=json_decode((string)$res,true);
    if($http<200 || $http>=300 || empty($json['ok'])) return ['ok'=>false,'http'=>$http,'error'=>'Core IA HTTP '.$http];
    $text=trim((string)($json['output_text']??''));
    return $text!=='' ? ['ok'=>true,'text'=>$text,'model'=>$json['model']??null] : ['ok'=>false,'error'=>'Core IA retornou texto vazio.'];
  }
  function tvp_generate_script($job){
    $profile=$job['presenter_profile']??tvp_avatar_profile_for_category($job['category']??'');
    $presenter=tvp_presenter_label($profile);
    $opening=tvp_script_opening_for_profile($profile,$job['category']??'');
    $body=tvp_clean(($job['body']??'').' '.($job['summary']??''));
    $prompt="Você é redator-chefe de telejornal regional da TV Sumaré. Gere APENAS o texto final que será falado pelo apresentador em vídeo, sem markdown, sem tópicos, sem rótulos e sem explicar o formato.\n\n".
      "APRESENTADOR/ASSINATURA: {$presenter}.\n".
      "ABERTURA OBRIGATÓRIA: {$opening}\n\n".
      "OBJETIVO: roteiro natural de telejornal regional, com 60 a 90 segundos, frases curtas, pausas naturais e linguagem humana.\n\n".
      "ESTRUTURA INTERNA DO TEXTO, mas sem escrever estes títulos: abertura curta, informação principal, contexto regional, serviço ao cidadão quando houver e encerramento natural. Não pronuncie domínio, URL, ponto com ou ponto br.\n\n".
      "REGRAS IMPORTANTES: não invente fatos, números, datas ou locais; não use sensacionalismo; não use 'é um prazer ter você conosco'; não use 'telespectadores'; não use frases genéricas como 'informação importante' sem detalhar o fato; não mencione IA; não faça acusações além do que a fonte informa; não comece o roteiro de forma truncada repetindo só o título; use tom profissional, próximo e claro.\n\n".
      "Quando a editoria for Empregos, Economia ou Negócios, destaque oportunidade, orientação ao cidadão, canais oficiais e impacto econômico regional.\n".
      "Quando a pauta for sensível, use tom neutro, responsável e evite exposição indevida.\n\n".
      "DADOS DA MATÉRIA:\n".
      "Título: ".($job['title']??'')."\n".
      "Cidade: ".($job['city']??'')."\n".
      "Categoria: ".($job['category']??'')."\n".
      "Fonte: ".($job['source']??'')."\n".
      "URL: ".($job['source_url']??'')."\n".
      "Conteúdo disponível: {$body}\n\n".
      "Finalize obrigatoriamente com: 'Eu sou Cristian Schibelsky. Até o próximo boletim.'";
    $script='';
    $core=tvp_core_generate_text($prompt);
    if(!empty($core['ok'])) $script=$core['text']??'';
    if($script==='' && trim((string)($GLOBALS['gemini_api_key']??''))!=='' && function_exists('tvs_gemini_generate_text')){
      $r=tvs_gemini_generate_text($GLOBALS['gemini_api_key'],$prompt,['temperature'=>0.18,'maxOutputTokens'=>1100],35);
      if(!empty($r['ok'])) $script=$r['text']??'';
    }
    $script=tvp_polish_script($script,$job);
    if(tvp_strlen($script)<220) $script=tvp_local_script($job);
    return tvp_polish_script($script,$job);
  }
}

if (!function_exists('tvp_heygen_config')) {
  function tvp_heygen_config(){
    if(function_exists('tvs_heygen_load_config')) return tvs_heygen_load_config([]);
    return [
      'heygen_api_key'=>$GLOBALS['heygen_api_key']??'',
      'heygen_avatar_id'=>$GLOBALS['heygen_avatar_id']??'',
      'heygen_voice_id'=>$GLOBALS['heygen_voice_id']??'',
      'heygen_style_id'=>$GLOBALS['heygen_style_id']??'',
      'heygen_brand_kit_id'=>$GLOBALS['heygen_brand_kit_id']??'',
      'heygen_orientation'=>$GLOBALS['heygen_orientation']??'landscape',
      'heygen_incognito_mode'=>$GLOBALS['heygen_incognito_mode']??'0',
      'heygen_callback_token'=>$GLOBALS['heygen_callback_token']??''
    ];
  }
  function tvp_http($method,$endpoint,$payload=null,$timeout=45){
    $cfg=tvp_heygen_config(); $key=trim((string)($cfg['heygen_api_key']??''));
    if($key==='') return ['ok'=>false,'error'=>'HeyGen sem chave configurada.'];
    if(!function_exists('curl_init')) return ['ok'=>false,'error'=>'cURL não está habilitado.'];
    $headers=['x-api-key: '.$key,'Accept: application/json'];
    $url='https://api.heygen.com'.$endpoint;
    $outboundOptions=tvs_outbound_curl_options($url,(int)$timeout);
    if($outboundOptions===null) return ['ok'=>false,'error'=>'URL HeyGen bloqueada pela politica de saida.'];
    $ch=curl_init($url);
    $opts=$outboundOptions+[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers];
    if($payload!==null){ $headers[]='Content-Type: application/json'; $opts[CURLOPT_HTTPHEADER]=$headers; $opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    curl_setopt_array($ch,$opts); $res=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if(is_string($res) && strlen($res)>2097152) return ['ok'=>false,'http'=>$http,'error'=>'Resposta HeyGen excedeu o limite seguro.'];
    if($res===false || $res==='') return ['ok'=>false,'http'=>$http,'error'=>'HeyGen sem resposta. '.$err];
    $json=json_decode($res,true);
    if($http>=400) return ['ok'=>false,'http'=>$http,'error'=>'HeyGen HTTP '.$http.': '.substr($res,0,700),'raw'=>$json?:$res];
    return ['ok'=>true,'http'=>$http,'data'=>$json?:[]];
  }
  function tvp_video_agent_prompt($job){
    $cfg=tvp_heygen_config(); $orientation=(($cfg['heygen_orientation']??'landscape')==='portrait')?'vertical 9:16':'landscape 16:9';
    $profile=$job['presenter_profile']??'cristian_editor';
    $presenter = $profile==='negocios_empregos' ? 'apresentador de Empregos e Negócios' : ($profile==='servicos_publicos' ? 'apresentadora de Serviços Públicos' : 'Cristian Schibelsky, Editor Responsável');
    return "Crie um vídeo jornalístico regional para a TV Sumaré.\n\nFormato: {$orientation}. Duração: 60 a 90 segundos. Tom: telejornal regional, natural, profissional e sem sensacionalismo. Use legendas, cortes limpos e identidade visual de emissora local.\n\nIdentidade na tela: TV SUMARÉ — BOLETIM REGIONAL.\nGC/assinatura: {$presenter}.\nEncerramento: Produção TV Sumaré IA News. Editor: Cristian Schibelsky.\n\nTítulo: ".($job['title']??'')."\nCidade: ".($job['city']??'')."\nCategoria: ".($job['category']??'')."\nFonte: ".($job['source']??'')."\n\nTexto falado obrigatório:\n".($job['script']??'')."\n\nNão invente dados. Não mencione IA. Finalize sem pronunciar domínio ou URL. O domínio deve aparecer apenas como texto visual na tela final.";
  }
  function tvp_send_heygen($job){
    $cfg=tvp_heygen_config();
    $payload=['prompt'=>tvp_video_agent_prompt($job),'mode'=>'generate','incognito_mode'=>in_array((string)($cfg['heygen_incognito_mode']??'0'),['1','true','on'],true)];
    foreach(['avatar_id'=>'heygen_avatar_id','voice_id'=>'heygen_voice_id','style_id'=>'heygen_style_id','brand_kit_id'=>'heygen_brand_kit_id'] as $api=>$local){ $v=trim((string)($cfg[$local]??'')); if($v!=='') $payload[$api]=$v; }
    if(in_array(($cfg['heygen_orientation']??'landscape'),['landscape','portrait'],true)) $payload['orientation']=$cfg['heygen_orientation'];
    // Correção CTO: não enviar arquivos externos para a HeyGen nesta fase.
    // URLs de RSS/prefeituras/portais podem bloquear download e causar: Invalid URL in files[0].
    // O primeiro fluxo operacional deve usar apenas prompt + avatar + voz.
    $token=trim((string)($cfg['heygen_callback_token']??'')); if($token!==''){ $payload['callback_url']=tvp_abs_url('api/heygen-callback.php?token='.rawurlencode($token)); $payload['callback_id']=$job['id']??''; }
    $r=tvp_http('POST','/v3/video-agents',$payload,60); if(!$r['ok']) return $r;
    $d=$r['data']['data']??($r['data']??[]);
    return ['ok'=>true,'session_id'=>$d['session_id']??'','video_id'=>$d['video_id']??'','status'=>$d['status']??'gerando','raw'=>$r['data']];
  }
  function tvp_check_heygen($job){
    $videoId=trim((string)($job['heygen_video_id']??'')); $sessionId=trim((string)($job['heygen_session_id']??'')); $out=[];
    if($sessionId!==''){ $r=tvp_http('GET','/v3/video-agents/'.rawurlencode($sessionId),null,35); if(!$r['ok']) return $r; $d=$r['data']['data']??($r['data']??[]); $out['session_status']=$d['status']??''; $out['progress']=$d['progress']??null; if($videoId==='' && !empty($d['video_id'])) $videoId=$d['video_id']; }
    if($videoId!==''){ $r=tvp_http('GET','/v3/videos/'.rawurlencode($videoId),null,35); if(!$r['ok']) return $r; $d=$r['data']['data']??($r['data']??[]); $out['video_id']=$videoId; $out['video_status']=$d['status']??''; $out['video_url']=$d['video_url']??''; $out['captioned_video_url']=$d['captioned_video_url']??''; $out['thumb']=$d['thumbnail_url']??''; $out['failure_message']=$d['failure_message']??($d['failure_code']??''); }
    if($sessionId==='' && $videoId==='') return ['ok'=>false,'error'=>'Job sem session_id ou video_id.'];
    $out['ok']=true; return $out;
  }
}

if (!function_exists('tvp_veo_config')) {
  function tvp_veo_config(){
    return [
      'api_key'=>trim((string)($GLOBALS['gemini_api_key']??getenv('GEMINI_API_KEY')?:'')),
      'base_url'=>rtrim((string)(getenv('GEMINI_VEO_BASE_URL')?:'https://generativelanguage.googleapis.com/v1beta'),'/'),
      'model'=>trim((string)(getenv('GEMINI_VEO_MODEL')?:'veo-3.1-generate-preview')),
      'aspect_ratio'=>'16:9',
      'resolution'=>'720p',
      'duration_seconds'=>8
    ];
  }
  function tvp_veo_http_json($method,$path,$payload=null,$timeout=45){
    $cfg=tvp_veo_config(); if($cfg['api_key']==='') return ['ok'=>false,'error'=>'Gemini/VEO sem chave configurada.'];
    if(!function_exists('curl_init')) return ['ok'=>false,'error'=>'cURL indisponível.'];
    $url=$cfg['base_url'].'/'.ltrim((string)$path,'/');
    $outbound=tvs_outbound_curl_options($url,$timeout); if($outbound===null) return ['ok'=>false,'error'=>'URL VEO bloqueada pela política de saída.'];
    $headers=['Accept: application/json','x-goog-api-key: '.$cfg['api_key']];
    $ch=curl_init($url); $opts=$outbound+[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers];
    if($payload!==null){ $headers[]='Content-Type: application/json'; $opts[CURLOPT_HTTPHEADER]=$headers; $opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    curl_setopt_array($ch,$opts); $res=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($res===false || $res==='') return ['ok'=>false,'http'=>$http,'error'=>'VEO sem resposta. '.$err];
    $json=json_decode((string)$res,true);
    if($http<200 || $http>=300) return ['ok'=>false,'http'=>$http,'error'=>'VEO HTTP '.$http.': '.substr((string)$res,0,900),'raw'=>$json?:$res];
    return ['ok'=>true,'http'=>$http,'data'=>is_array($json)?$json:[]];
  }
  function tvp_veo_scene_prompts($job){
    $title=tvp_clean($job['title']??'Notícia regional');
    $city=tvp_clean($job['city']??'Região');
    $cat=tvp_clean($job['category']??'Notícia');
    $context=tvp_substr(tvp_clean(($job['summary']??'').' '.($job['body']??'').' '.($job['script']??'')),0,800);
    $guard='Conteúdo visual jornalístico ilustrativo, sem simular filmagem documental real de um fato que não foi gravado. Não mostrar pessoas públicas identificáveis, não inventar placas, documentos, declarações, números ou locais específicos. Sem apresentador falando para a câmera. Sem texto ilegível. Estética de TV regional brasileira, realista e profissional, 16:9.';
    return [
      $guard." Cena 1: abertura visual contextual de {$city}, tema {$cat}. Pauta: {$title}. Contexto: {$context}. Movimento de câmera suave, plano de estabelecimento, áudio ambiente discreto.",
      $guard." Cena 2: b-roll editorial relacionado ao assunto {$title}, mostrando elementos genéricos e verificáveis do tema {$cat}, sem recriar o acontecimento como registro real. Contexto: {$context}. Cortes limpos, linguagem audiovisual jornalística.",
      $guard." Cena 3: encerramento visual para boletim da TV Sumaré sobre {$title}, com composição limpa, espaço seguro para identidade gráfica sobreposta depois, sem apresentador e sem fala. Contexto regional: {$city}."
    ];
  }
  function tvp_hub_http_json($path,$payload,$timeout=60){
    $base=rtrim((string)(getenv('MARKETING_ENGINE_URL')?:'https://marketing.hml.vitrineiapro.com.br'),'/');
    $token=trim((string)(getenv('MARKETING_ENGINE_TOKEN')?:''));
    if($token==='') return ['ok'=>false,'error'=>'Token do Hub IA não configurado.'];
    $url=$base.'/'.ltrim((string)$path,'/');
    $outbound=tvs_outbound_curl_options($url,$timeout); if($outbound===null) return ['ok'=>false,'error'=>'Hub IA bloqueado pela política de saída.'];
    $ch=curl_init($url); curl_setopt_array($ch,$outbound+[
      CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
      CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','Authorization: Bearer '.$token],
      CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    ]);
    $res=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($res===false || $res==='') return ['ok'=>false,'http'=>$http,'error'=>'Hub IA sem resposta. '.$err];
    $json=json_decode((string)$res,true);
    if($http<200 || $http>=300) return ['ok'=>false,'http'=>$http,'error'=>'Hub IA HTTP '.$http.': '.substr((string)$res,0,900),'raw'=>$json?:$res];
    return ['ok'=>true,'http'=>$http,'data'=>is_array($json)?$json:[]];
  }
  function tvp_send_veo($job){
    $ops=[];
    foreach(tvp_veo_scene_prompts($job) as $i=>$prompt){
      $r=tvp_hub_http_json('/api/internal/marketing/media/video',[
        'project_id'=>(string)($job['id']??'tvsumare-video'),
        'brand'=>'TV Sumaré',
        'idea'=>$prompt,
        'objective'=>'informar',
        'channel'=>'portal',
        'format'=>'reportagem_visual',
        'title'=>(string)($job['title']??'TV Sumaré'),
        'caption'=>(string)($job['summary']??''),
        'cta'=>'Acompanhe a TV Sumaré',
        'aspect_ratio'=>'16:9',
        'duration_seconds'=>8
      ],60);
      if(empty($r['ok'])) return ['ok'=>false,'error'=>'Falha no Hub IA ao iniciar cena '.($i+1).': '.($r['error']??'erro VEO')];
      $op=trim((string)($r['data']['job_ref']??'')); if($op==='') return ['ok'=>false,'error'=>'Hub IA não retornou job_ref para a cena '.($i+1).'.'];
      $ops[]=$op;
    }
    return ['ok'=>true,'operations'=>$ops,'status'=>'gerando','provider'=>'hub_veo'];
  }
  function tvp_veo_download($url,$dest){
    $cfg=tvp_veo_config(); $url=trim((string)$url);
    if(!preg_match('~^https://~i',$url)) return ['ok'=>false,'error'=>'URL de render VEO inválida.'];
    $outbound=tvs_outbound_curl_options($url,120); if($outbound===null) return ['ok'=>false,'error'=>'Download VEO bloqueado pela política de saída.'];
    $fp=@fopen($dest,'wb'); if(!$fp) return ['ok'=>false,'error'=>'Falha ao criar arquivo temporário VEO.'];
    $ch=curl_init($url); curl_setopt_array($ch,$outbound+[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Accept: video/mp4,video/*;q=0.9,*/*;q=0.1','x-goog-api-key: '.$cfg['api_key']]]);
    $ok=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); fclose($fp);
    $size=is_file($dest)?(int)filesize($dest):0;
    if(!$ok || $http<200 || $http>=300 || $size<10240){ @unlink($dest); return ['ok'=>false,'error'=>'Falha no download VEO HTTP '.$http.'. '.$err]; }
    return ['ok'=>true,'bytes'=>$size];
  }
  function tvp_veo_finalize($uris,$jobId){
    $ffmpeg=trim((string)@shell_exec('command -v ffmpeg 2>/dev/null')); if($ffmpeg==='') return ['ok'=>false,'error'=>'FFmpeg indisponível.'];
    $root=tvp_root(); $dir=$root.'/uploads/videos'; if(!is_dir($dir) && !@mkdir($dir,0775,true)) return ['ok'=>false,'error'=>'Falha ao preparar armazenamento de vídeos.'];
    $safe=preg_replace('/[^a-zA-Z0-9_-]+/','-',(string)$jobId); $safe=trim((string)$safe,'-_')?:'veo-'.date('YmdHis');
    $tmpDir=sys_get_temp_dir().'/tvs-veo-'.$safe.'-'.bin2hex(random_bytes(3)); if(!@mkdir($tmpDir,0700,true)) return ['ok'=>false,'error'=>'Falha ao preparar temporários VEO.'];
    $clips=[];
    foreach(array_values($uris) as $i=>$uri){ $clip=$tmpDir.'/scene-'.$i.'.mp4'; $d=tvp_veo_download($uri,$clip); if(empty($d['ok'])){ foreach($clips as $c) @unlink($c); @rmdir($tmpDir); return $d; } $clips[]=$clip; }
    $list=$tmpDir.'/concat.txt'; $lines=[]; foreach($clips as $c){ $lines[]="file '".str_replace("'","'\\''",$c)."'"; } file_put_contents($list,implode("\n",$lines));
    $joined=$tmpDir.'/joined.mp4';
    $cmd=escapeshellarg($ffmpeg).' -hide_banner -loglevel error -y -f concat -safe 0 -i '.escapeshellarg($list).' -c:v libx264 -preset medium -crf 21 -pix_fmt yuv420p -c:a aac -b:a 160k -movflags +faststart '.escapeshellarg($joined).' 2>&1';
    $out=[]; $code=0; exec($cmd,$out,$code); if($code!==0 || !is_file($joined) || filesize($joined)<10240){ foreach($clips as $c) @unlink($c); @unlink($list); @rmdir($tmpDir); return ['ok'=>false,'error'=>'Falha ao compor cenas VEO: '.substr(implode("\n",$out),0,700)]; }
    $final=$dir.'/'.$safe.'.mp4'; $logo=$root.'/assets/logo-tv-sumare.jpeg';
    if(is_file($logo)){
      $filter='[1:v][0:v]scale2ref=w=main_w*0.16:h=-1[wm][base];[base][wm]overlay=x=W*0.03:y=H*0.04:format=auto[v]';
      $cmd2=escapeshellarg($ffmpeg).' -hide_banner -loglevel error -y -i '.escapeshellarg($joined).' -i '.escapeshellarg($logo).' -filter_complex '.escapeshellarg($filter).' -map '.escapeshellarg('[v]').' -map 0:a? -c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p -c:a aac -b:a 160k -movflags +faststart '.escapeshellarg($final).' 2>&1';
      $out2=[]; $code2=0; exec($cmd2,$out2,$code2);
      if($code2!==0 || !is_file($final) || filesize($final)<10240) @copy($joined,$final);
    } else @copy($joined,$final);
    foreach($clips as $c) @unlink($c); @unlink($list); @unlink($joined); @rmdir($tmpDir);
    if(!is_file($final) || filesize($final)<10240) return ['ok'=>false,'error'=>'Vídeo VEO final não foi criado.'];
    @chmod($final,0644); return ['ok'=>true,'video_url'=>'uploads/videos/'.rawurlencode(basename($final)),'bytes'=>(int)filesize($final)];
  }
  function tvp_check_veo($job){
    $ops=$job['veo_operations']??[]; if(!is_array($ops) || !$ops) return ['ok'=>false,'error'=>'Job VEO sem operações.'];
    $uris=[]; $done=0;
    foreach($ops as $op){
      $r=tvp_hub_http_json('/api/internal/marketing/media/video/refresh',['job_ref'=>$op],35); if(empty($r['ok'])) return $r;
      $d=$r['data']??[]; $status=(string)($d['status']??'processing');
      if($status==='failed') return ['ok'=>false,'error'=>'Hub IA informou falha na geração VEO.'];
      if($status!=='completed') continue;
      $uri=trim((string)($d['asset_url']??'')); if($uri==='') return ['ok'=>false,'error'=>'Hub IA concluiu sem URL do vídeo.'];
      $uris[]=$uri; $done++;
    }
    if($done<count($ops)) return ['ok'=>true,'status'=>'gerando','progress'=>(int)floor(($done/count($ops))*100)];
    $final=tvp_veo_finalize($uris,$job['id']??uniqid('veo_')); if(empty($final['ok'])) return $final;
    return ['ok'=>true,'status'=>'pronto','progress'=>100,'video_url'=>$final['video_url'],'bytes'=>$final['bytes']??0];
  }
}

if (!function_exists('tvp_publish_video')) {
  function tvp_publish_video($job){
    $videos=tvp_read_json('videos.json'); foreach($videos as $v){ if(($v['ia_job_id']??'')===($job['id']??'')) return ['ok'=>true,'message'=>'Vídeo já estava publicado.']; }
    $url=trim((string)(($job['captioned_video_url']??'') ?: ($job['video_url']??''))); if($url==='') return ['ok'=>false,'error'=>'URL do vídeo ausente.'];
    array_unshift($videos,[
      'id'=>'vid_'.date('YmdHis').'_'.substr(md5($job['id']??microtime(true)),0,5),
      'title'=>$job['title']??'TV Sumaré News',
      'category'=>$job['category']??'Notícia',
      'city'=>$job['city']??'Região',
      'description'=>tvp_substr(tvp_clean($job['script']??''),0,220),
      'url'=>$url,
      'thumb'=>$job['thumb']??($job['image']??'assets/cat-cidade.svg'),
      'status'=>'active',
      'featured'=>1,
      'ia_job_id'=>$job['id']??'',
      'created_at'=>date('c')
    ]);
    tvp_write_json('videos.json',$videos); return ['ok'=>true];
  }
}
?>
