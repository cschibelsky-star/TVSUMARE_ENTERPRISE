<?php
require_once __DIR__.'/../includes/outbound_guard.php';
function tvs_strlen($s){ return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
function tvs_substr($s,$start,$len=null){ return function_exists('mb_substr') ? mb_substr($s,$start,$len,'UTF-8') : substr($s,$start,$len); }
function tvs_lower($s){ return function_exists('mb_strtolower') ? mb_strtolower((string)$s,'UTF-8') : strtolower((string)$s); }

function tvs_clean_text($s){
  $s = html_entity_decode($s ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $s = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $s);
  $s = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $s);
  $s = strip_tags($s);
  $s = preg_replace('/\s+/u', ' ', $s);
  return trim($s);
}


function tvs_is_skip_or_navigation_title($title){
  $t = tvs_lower(tvs_clean_text((string)$title));
  $t = trim($t);
  if($t==='') return true;
  $bad='~^(ir para o conte[uú]do|pular para o conte[uú]do|acessar conte[uú]do|conte[uú]do principal|menu principal|abrir menu|fechar menu|in[ií]cio|home|mapa do site)$~iu';
  return preg_match($bad,$t)===1;
}

function tvs_is_institutional_url($url){
  $u = tvs_lower((string)$url);
  if($u==='') return false;
  $bad='~/(portal/)?(secretarias|secretaria|departamentos|departamento|estrutura|organograma|quem-somos|quem_somos|contato|historia|historia-do-municipio|gabinete|expediente|telefones|enderecos|servicos)(/|$|\?)~iu';
  if(preg_match($bad,$u)) return true;
  if(preg_match('~/portal/(secretarias|servicos|estrutura|departamentos)/~iu',$u)) return true;
  return false;
}

function tvs_has_news_action_signal($text){
  $t=tvs_lower(tvs_clean_text((string)$text));
  return preg_match('~\b(abre|abriu|lan[cç]a|lan[cç]ou|inicia|iniciou|realiza|realizou|divulga|divulgou|anuncia|anunciou|entrega|entregou|aprova|aprovou|recebe|recebeu|promove|promoveu|oferece|ofereceu|inscri[cç][oõ]es|vagas|mutir[aã]o|opera[cç][aã]o|campanha|evento|obra|curso|programa|edital|atendimento|interdi[cç][aã]o|convoca|convocou|prorroga|prorrogou|alerta|alertou|calend[aá]rio|programa[cç][aã]o|sele[cç][aã]o|processo seletivo|concurso|audi[eê]ncia|reuni[aã]o|obra|manuten[cç][aã]o|vacina[cç][aã]o|matr[ií]cula|feira|show|festival)\b~iu',$t)===1;
}

function tvs_has_temporal_or_service_signal($text){
  $t=tvs_lower(tvs_clean_text((string)$text));
  return preg_match('~\b(hoje|amanh[aã]|ontem|nesta|neste|semana|segunda|ter[cç]a|quarta|quinta|sexta|s[aá]bado|domingo|202[0-9]|janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro|das \d{1,2}h|\d{1,2}/\d{1,2}|prazo|inscri[cç][oõ]es|vagas|atendimento|programa[cç][aã]o|agenda|edital|comunicado|nota oficial)\b~iu',$t)===1;
}

function tvs_is_institutional_profile_text($title,$url='',$description=''){
  $rawTitle=(string)$title;
  $rawDesc=(string)$description;
  $t=tvs_lower(tvs_clean_text($rawTitle.' '.$rawDesc));
  $u=tvs_lower((string)$url);
  if(tvs_is_skip_or_navigation_title($rawTitle)) return true;

  // Régua produtiva: URL de secretaria/departamento não é lixo automaticamente.
  // Muitas prefeituras publicam notícias dentro dessas rotas. Só bloqueia quando o texto
  // tem cara de perfil permanente, contato, estrutura administrativa ou página estática.
  $hasNewsSignal = tvs_has_news_action_signal($rawTitle.' '.$rawDesc) || tvs_has_temporal_or_service_signal($rawTitle.' '.$rawDesc);

  $profile='~(secretaria municipal .*\b(respons[aá]vel por|tem como objetivo|tem por finalidade|compete|atribui[cç][oõ]es)|\b(respons[aá]vel por planejar|planejar e executar|execu[cç][aã]o das pol[ií]ticas p[uú]blicas|pol[ií]ticas p[uú]blicas de|compet[eê]ncia da secretaria|atribui[cç][oõ]es da secretaria|estrutura administrativa|hor[aá]rio de atendimento|endere[cç]o|telefone|e-mail institucional))~iu';
  if(preg_match($profile,$t) && !$hasNewsSignal) return true;

  $plainDept='~^(secretaria municipal de|secretaria de|departamento de|diretoria de|coordenadoria de)\s+[\p{L}\s,&-]{3,80}$~iu';
  if(preg_match($plainDept, trim($rawTitle)) && !$hasNewsSignal) return true;

  if(tvs_is_institutional_url($u) && !$hasNewsSignal){
    // Mantém o bloqueio apenas para páginas institucionais sem sinal de notícia.
    return true;
  }
  return false;
}

function tvs_extract_meta_content($html, $names){
  $html=(string)$html;
  $names=(array)$names;
  if($html==='' || !preg_match_all('~<meta\b[^>]*>~is',$html,$tags)) return '';
  foreach($tags[0] as $tag){
    foreach($names as $name){
      $q=preg_quote($name,'~');
      if(preg_match('~(?:property|name)=["\']'.$q.'["\']~i',$tag) && preg_match('~content=["\']([^"\']+)["\']~i',$tag,$m)){
        return tvs_clean_text($m[1]);
      }
    }
  }
  return '';
}

function tvs_is_boilerplate($txt){
  $txt = trim((string)$txt);
  if($txt==='') return true;
  if(tvs_strlen($txt) < 45) return true;
  $bad = '~(menu|fechar|facebook|instagram|x-twitter|twitter|youtube|linkedin|whatsapp|cookie|cookies|newsletter|assine|últimas notícias|matérias especiais|agência de notícias|rádio sp|vídeos|dados sobre|editorias|regiões|download|compartilhe|acessibilidade|alto contraste|mapa do site|todos os direitos reservados|política de privacidade|termos de uso|publicidade|clique aqui|javascript|\.cls-|stroke-width)~iu';
  if(preg_match($bad, $txt)){
    // Não descarta parágrafo jornalístico apenas por ter uma palavra comum, mas descarta textos claramente de navegação.
    $words = preg_split('/\s+/u', $txt);
    $navHits = preg_match_all('~(menu|facebook|instagram|youtube|whatsapp|twitter|linkedin|editorias|regiões|fechar|download)~iu', $txt);
    if($navHits >= 2 || count($words) < 18) return true;
  }
  // Linhas com muitas palavras de menu separadas costumam ser cabeçalho/rodapé capturado.
  $menuTerms = preg_match_all('~(Últimas|Editorias|Matérias|Aviso|Artigos|Rádio|Vídeos|Dados|Sobre|Facebook|Instagram|Youtube|Whatsapp|Regiões|Agro|Saúde|Segurança|Turismo|Transporte)~u', $txt);
  if($menuTerms >= 8) return true;
  return false;
}

function tvs_remove_boilerplate_from_html($html){
  $html = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $html);
  $html = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $html);
  $html = preg_replace('~<(nav|header|footer|aside|form|button|svg)\b[^>]*>.*?</\1>~is', ' ', $html);
  $html = preg_replace('~<div[^>]+class=["\'][^"\']*(menu|breadcrumb|share|social|newsletter|cookie|related|sidebar|footer|header|nav|publicidade|ads|download)[^"\']*["\'][^>]*>.*?</div>~is', ' ', $html);
  return $html;
}

function tvs_fetch_url($url){
  if(!function_exists('curl_init') || !function_exists('tvs_outbound_curl_options')) return '';
  $options=tvs_outbound_curl_options($url,6);
  if($options===null) return '';
  $ch=curl_init((string)$url);
  curl_setopt_array($ch,$options+[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_USERAGENT=>'TVSumareBot/2.0',
    CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml,application/rss+xml']
  ]);
  $html=curl_exec($ch);
  $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  if(!is_string($html) || $http<200 || $http>=300) return '';
  return substr($html,0,2097152);
}
function tvs_absolute_url($base, $href){
  if(!$href) return '';
  if(preg_match('~^https?://~i',$href)) return $href;
  if(strpos($href,'//')===0) return 'https:'.$href;
  $p=parse_url($base); if(!$p || empty($p['scheme']) || empty($p['host'])) return $href;
  $root=$p['scheme'].'://'.$p['host'];
  if(substr($href,0,1)==='/') return $root.$href;
  $dir=isset($p['path']) ? preg_replace('~/[^/]*$~','/',$p['path']) : '/';
  return $root.$dir.$href;
}


function tvs_extract_meta_image_from_html($base, $html){
  $html=(string)$html;
  if($html==='') return '';
  // Captura <meta property="og:image" content="..."> em qualquer ordem de atributos.
  if(preg_match_all('~<meta\b[^>]*>~is',$html,$tags)){
    foreach($tags[0] as $tag){
      $isImage = preg_match('~(?:property|name)=["\'](?:og:image|twitter:image|twitter:image:src)["\']~i',$tag);
      if($isImage && preg_match('~content=["\']([^"\']+)["\']~i',$tag,$m)){
        $img=tvs_absolute_url($base, html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));
        if(tvs_is_valid_image_url($img)) return $img;
      }
    }
  }
  // Não usa mais a primeira imagem aleatória do HTML.
  // Sem og:image ou twitter:image confiável, a pauta exige revisão visual.
  return '';
}

function tvs_extract_image_from_rss_description($base, $desc){
  $desc=(string)$desc;
  if($desc==='' || stripos($desc,'<img')===false) return '';
  if(preg_match('~<img\b[^>]*(?:src|data-src)=["\']([^"\']+)["\'][^>]*>~is',$desc,$m)){
    $img=tvs_absolute_url($base, html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));
    if(tvs_is_valid_image_url($img)) return $img;
  }
  return '';
}

function tvs_image_credit_from_source($source, $image=''){
  $source=trim((string)$source);
  $image=trim((string)$image);
  if($image!=='' && !preg_match('~^https?://~i',$image)){
    return 'Imagem ilustrativa: TV Sumaré';
  }
  if($source==='' || $source==='Google Notícias') return 'Imagem: fonte original / divulgação';
  return 'Imagem: '.$source.' / divulgação';
}

function tvs_extract_links($base, $html){
  $items=[];
  preg_match_all('~<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',$html,$m,PREG_SET_ORDER);
  foreach($m as $a){
    $title=tvs_clean_text($a[2]);
    $url=tvs_absolute_url($base,$a[1]);
    if(tvs_strlen($title)<25 || tvs_strlen($title)>200) continue;
    if(preg_match('~(facebook|instagram|twitter|whatsapp|youtube|mailto:|javascript:)~i',$url)) continue;
    if(tvs_is_boilerplate($title)) continue;
    if(tvs_is_institutional_profile_text($title,$url,'')) continue;
    $score=0;
    if(preg_match('~(noticia|news|imprensa|comunicacao|portal|post|materia|conteudo)~i',$url)) $score+=3;
    if(preg_match('~(prefeitura|saude|educacao|obra|cidade|cultura|emprego|defesa|transito|seguranca|evento|servico|campinas|sumare|hortolandia|americana|paulinia|nova-odessa)~iu',$title)) $score+=2;
    $items[]=['title'=>$title,'url'=>$url,'score'=>$score];
  }
  usort($items,function($a,$b){ return $b['score'] <=> $a['score']; });
  $unique=[]; $seen=[];
  foreach($items as $it){ if(isset($seen[$it['url']])) continue; $seen[$it['url']]=1; $unique[]=$it; if(count($unique)>=3) break; }
  return $unique;
}

function tvs_pick_article_html($html){
  $candidates=[];
  foreach(['article','main'] as $tag){
    if(preg_match_all('~<'.$tag.'\b[^>]*>(.*?)</'.$tag.'>~is',$html,$m)){
      foreach($m[1] as $block){ $candidates[]=$block; }
    }
  }
  if(preg_match_all('~<div[^>]+class=["\'][^"\']*(content|article|materia|post|noticia|texto|body)[^"\']*["\'][^>]*>(.*?)</div>~is',$html,$m)){
    foreach($m[2] as $block){ $candidates[]=$block; }
  }
  $best=''; $bestScore=0;
  foreach($candidates as $block){
    $plain=tvs_clean_text(tvs_remove_boilerplate_from_html($block));
    $score=tvs_strlen($plain);
    if($score>$bestScore){ $best=$block; $bestScore=$score; }
  }
  return $best ?: $html;
}


function tvs_portal_decode_base64($value){
  $value=html_entity_decode(trim((string)$value),ENT_QUOTES|ENT_HTML5,'UTF-8');
  if($value==='') return '';

  $decoded=base64_decode($value,true);
  if($decoded===false) return '';

  // O portal pode entregar conteúdo antigo em ISO-8859-1/Windows-1252.
  if(function_exists('mb_check_encoding') && !mb_check_encoding($decoded,'UTF-8')){
    $decoded=mb_convert_encoding($decoded,'UTF-8','Windows-1252,ISO-8859-1');
  }

  // titulo-pagina e subtitulo-pagina são frequentemente strings JSON
  // codificadas em Base64, incluindo aspas e escapes Unicode.
  $trimmed=trim($decoded);

  if(
    strlen($trimmed)>=2 &&
    $trimmed[0]==='"' &&
    substr($trimmed,-1)==='"'
  ){
    $jsonDecoded=json_decode($trimmed,true);

    if(is_string($jsonDecoded)){
      $decoded=$jsonDecoded;
    }
  }

  return trim($decoded);
}

function tvs_extract_html_attribute($tag,$attribute){
  $attribute=preg_quote((string)$attribute,'~');

  if(preg_match(
    '~\b'.$attribute.'\s*=\s*(["\'])(.*?)\1~is',
    (string)$tag,
    $m
  )){
    return html_entity_decode($m[2],ENT_QUOTES|ENT_HTML5,'UTF-8');
  }

  return '';
}

function tvs_expand_sumare_portal_page($url,$html){
  $html=(string)$html;

  if(
    stripos((string)$url,'sumare.sp.gov.br')===false ||
    stripos((string)$url,'/cidadao/noticia/')===false
  ){
    return $html;
  }

  if(!preg_match('~<pagina\b[^>]*>~is',$html,$match)){
    return $html;
  }

  $tag=$match[0];

  $title=tvs_portal_decode_base64(
    tvs_extract_html_attribute($tag,'titulo-pagina')
  );

  $subtitle=tvs_portal_decode_base64(
    tvs_extract_html_attribute($tag,'subtitulo-pagina')
  );

  $contentHtml=tvs_portal_decode_base64(
    tvs_extract_html_attribute($tag,'content')
  );

  $pageDataRaw=tvs_portal_decode_base64(
    tvs_extract_html_attribute($tag,'dados-pagina')
  );

  $pageData=json_decode($pageDataRaw,true);
  if(!is_array($pageData)) $pageData=[];

  $image=trim((string)($pageData['midia_capa']??''));
  $published=trim((string)($pageData['data']??''));

  if($published!==''){
    $dt=DateTime::createFromFormat('d/m/Y H:i:s',$published);
    if($dt instanceof DateTime){
      $published=$dt->format(DateTime::ATOM);
    }
  }

  $body=tvs_normalize_article_body($contentHtml);

  // Corrige palavras coladas por espaços removidos na serialização do portal.
  $body=preg_replace('~(?<=[[:alpha:]À-ÿ])(?=[0-9])~u',' ',$body);
  $body=preg_replace('~(?<=[0-9])(?=[[:alpha:]À-ÿ])~u',' ',$body);

  // Correções recorrentes produzidas pelo HTML serializado do portal.
  $body=preg_replace(
    '~\b(religiosa|cultural|municipal|regional|nacional)(na|no|nas|nos)\b~iu',
    '$1 $2',
    $body
  );

  $body=preg_replace(
    '~\b(de|da|do|das|dos)(comemorações|atividades|eventos|apresentações)\b~iu',
    '$1 $2',
    $body
  );

  $body=preg_replace('~[ \t]{2,}~u',' ',$body);
  $body=trim($body);

  // Injeta JSON-LD normalizado para reaproveitar o pipeline existente.
  $jsonLd=[
    '@context'=>'https://schema.org',
    '@type'=>'NewsArticle',
    'headline'=>tvs_clean_text($title),
    'description'=>tvs_clean_text($subtitle),
    'articleBody'=>$body,
    'image'=>$image,
    'datePublished'=>$published
  ];

  $injected='<script type="application/ld+json">'
    .json_encode(
      $jsonLd,
      JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    )
    .'</script>';

  // Mantém o HTML original para metadados e acrescenta o corpo decodificado.
  return $injected."\n".$html."\n".$contentHtml;
}

function tvs_extract_jsonld_article($baseUrl,$html){
  $result=[
    'title'=>'',
    'description'=>'',
    'body'=>'',
    'image'=>'',
    'published_at'=>''
  ];

  if(!preg_match_all(
    '~<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is',
    (string)$html,
    $matches
  )){
    return $result;
  }

  foreach($matches[1] as $raw){
    $raw=html_entity_decode(trim($raw),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $data=json_decode($raw,true);

    if(!is_array($data)) continue;

    $nodes=[];

    if(isset($data['@graph']) && is_array($data['@graph'])){
      $nodes=$data['@graph'];
    } elseif(array_is_list($data)){
      $nodes=$data;
    } else {
      $nodes=[$data];
    }

    foreach($nodes as $node){
      if(!is_array($node)) continue;

      $type=$node['@type']??'';
      $types=is_array($type)?$type:[$type];
      $isArticle=false;

      foreach($types as $candidateType){
        if(preg_match(
          '~Article|NewsArticle|ReportageNewsArticle|BlogPosting~i',
          (string)$candidateType
        )){
          $isArticle=true;
          break;
        }
      }

      if(!$isArticle && empty($node['articleBody'])) continue;

      $result['title']=tvs_clean_text(
        $node['headline']??$node['name']??''
      );

      $result['description']=tvs_clean_text(
        $node['description']??''
      );

      $body=$node['articleBody']??'';

      if(is_array($body)){
        $body=implode("\n\n",array_map('strval',$body));
      }

      $result['body']=tvs_normalize_article_body((string)$body);

      $image=$node['image']??'';

      if(is_array($image)){
        if(isset($image['url'])){
          $image=$image['url'];
        } elseif(isset($image[0])){
          $first=$image[0];
          $image=is_array($first)?($first['url']??''):$first;
        } else {
          $image='';
        }
      }

      if($image!==''){
        $image=tvs_absolute_url($baseUrl,(string)$image);

        if(tvs_is_valid_image_url($image)){
          $result['image']=$image;
        }
      }

      $result['published_at']=(string)(
        $node['datePublished']??$node['dateCreated']??''
      );

      if(
        $result['title']!=='' ||
        tvs_strlen($result['body'])>=100
      ){
        return $result;
      }
    }
  }

  return $result;
}

function tvs_extract_remote_html_fragments($baseUrl,$html){
  $html=(string)$html;
  $out='';

  if(!preg_match_all(
    '~https?://[^"\']+/static/portal/html/elementos/[^"\']+\.html~i',
    $html,
    $matches
  )){
    return '';
  }

  $urls=array_values(array_unique($matches[0]));
  $count=0;

  foreach($urls as $fragmentUrl){
    if($count>=20) break;

    $fragment=tvs_fetch_url($fragmentUrl);
    if($fragment==='') continue;

    $fragment=tvs_remove_boilerplate_from_html($fragment);
    $plain=tvs_clean_text($fragment);

    if(tvs_strlen($plain)<40) continue;

    $out.="\n".$fragment;
    $count++;
  }

  return $out;
}


function tvs_fix_joined_letters_numbers($text){
  $text=(string)$text;

  // Remove caracteres invisíveis de formatação Unicode.
  $text=preg_replace('~\p{Cf}+~u','',$text);

  // Normaliza espaços especiais vindos de HTML e editores de texto.
  $text=str_replace(
    ["\xC2\xA0","\xE2\x80\xAF","\xE2\x80\x89","\xE2\x80\x8B"],
    ' ',
    $text
  );

  // Separa letras e números colados.
  $text=preg_replace(
    '~([A-Za-zÀ-ÖØ-öø-ÿ])([0-9])~u',
    '$1 $2',
    $text
  );

  $text=preg_replace(
    '~([0-9])([A-Za-zÀ-ÖØ-öø-ÿ])~u',
    '$1 $2',
    $text
  );

  $text=preg_replace('~[ \t]{2,}~u',' ',$text);

  return trim((string)$text);
}


function tvs_extract_direct_meta_image($baseUrl,$html){
  $html=(string)$html;

  if(!preg_match_all('~<meta\b[^>]*>~is',$html,$tags)){
    return '';
  }

  foreach($tags[0] as $tag){
    $isImageMeta=(
      preg_match(
        '~\b(?:property|name)\s*=\s*(["\'])'
        .'(?:og:image|og:image:url|og:image:secure_url|'
        .'twitter:image|twitter:image:src)'
        .'\1~i',
        $tag
      )===1
    );

    if(!$isImageMeta) continue;

    if(!preg_match(
      '~\bcontent\s*=\s*(["\'])(.*?)\1~is',
      $tag,
      $match
    )){
      continue;
    }

    $image=html_entity_decode(
      trim($match[2]),
      ENT_QUOTES|ENT_HTML5,
      'UTF-8'
    );

    if(str_starts_with($image,'//')){
      $scheme=(string)(parse_url($baseUrl,PHP_URL_SCHEME)??'https');
      $image=$scheme.':'.$image;
    } elseif(str_starts_with($image,'/')){
      $scheme=(string)(parse_url($baseUrl,PHP_URL_SCHEME)??'https');
      $host=(string)(parse_url($baseUrl,PHP_URL_HOST)??'');

      if($host!==''){
        $image=$scheme.'://'.$host.$image;
      }
    }

    if(!preg_match('~^https?://~i',$image)) continue;

    if(preg_match(
      '~googleusercontent\.com|gstatic\.com|'
      .'favicon|logo(?:[._-]|$)|sprite|icon|icone|avatar~i',
      $image
    )){
      continue;
    }

    return $image;
  }

  return '';
}

function tvs_find_next_data_image_recursive($value,&$candidates,$path='root'){
  if(!is_array($value)) return;

  foreach($value as $key=>$item){
    $currentPath=$path.'.'.$key;

    if(is_string($item)){
      $keyText=tvs_lower((string)$key);

      if(
        preg_match('~^https?://.+\.(?:jpg|jpeg|png|webp)(?:\?.*)?$~i',$item) &&
        preg_match(
          '~sourceurl|image|imagem|thumbnail|cover|capa|featured|photo|foto|media~i',
          $keyText.' '.$currentPath
        ) &&
        !preg_match(
          '~favicon|logo|sprite|icon|icone|avatar|googleusercontent\.com~i',
          $item
        )
      ){
        $score=10;

        if(preg_match('~(?:600|768|1024|1200|1536)[_x-]~i',$currentPath.' '.$item)){
          $score+=40;
        }

        if(preg_match('~(?:300|227|151)[_x-]~i',$currentPath.' '.$item)){
          $score-=10;
        }

        if(preg_match('~crop|featured|capa|cover~i',$currentPath)){
          $score+=20;
        }

        $candidates[]=[
          'url'=>$item,
          'score'=>$score
        ];
      }
    }

    if(is_array($item)){
      tvs_find_next_data_image_recursive(
        $item,
        $candidates,
        $currentPath
      );
    }
  }
}

function tvs_extract_next_data_image($html){
  $html=(string)$html;

  if(!preg_match(
    '~<script\b[^>]*id=["\']__NEXT_DATA__["\'][^>]*>'
    .'(.*?)</script>~is',
    $html,
    $match
  )){
    return '';
  }

  $json=html_entity_decode(
    trim($match[1]),
    ENT_QUOTES|ENT_HTML5,
    'UTF-8'
  );

  $data=json_decode($json,true);

  if(!is_array($data)) return '';

  $candidates=[];

  tvs_find_next_data_image_recursive(
    $data,
    $candidates
  );

  if(!$candidates) return '';

  usort(
    $candidates,
    static fn($a,$b)=>
      ((int)($b['score']??0)) <=> ((int)($a['score']??0))
  );

  return trim((string)($candidates[0]['url']??''));
}

function tvs_extract_article($url, $fallbackTitle=''){
  $html=tvs_fetch_url($url);
  $html=tvs_expand_sumare_portal_page($url,$html);

  // O portal de Sumaré carrega o conteúdo da notícia em fragmentos HTML externos.
  if(
    stripos((string)$url,'sumare.sp.gov.br')!==false &&
    stripos((string)$url,'/cidadao/noticia/')!==false
  ){
    $remoteFragments=tvs_extract_remote_html_fragments($url,$html);

    if($remoteFragments!==''){
      $html.="\n".$remoteFragments;
    }
  }

  $title=$fallbackTitle;

  $isOfficialNewsRoute=preg_match(
    '~/(cidadao/)?noticia(s)?(/|$)~iu',
    (string)$url
  )===1;

  $jsonld=tvs_extract_jsonld_article($url,$html);

  if(!empty($jsonld['title'])){
    $title=$jsonld['title'];
  }
  $metaTitle=tvs_extract_meta_content($html, ['og:title','twitter:title']);
  if($metaTitle && !tvs_is_skip_or_navigation_title($metaTitle)) $title=$metaTitle;
  elseif(preg_match('~<h1[^>]*>(.*?)</h1>~is',$html,$m) && !tvs_is_skip_or_navigation_title($m[1]??'')) $title=tvs_clean_text($m[1]);
  elseif(preg_match('~<title[^>]*>(.*?)</title>~is',$html,$m) && !tvs_is_skip_or_navigation_title($m[1]??'')) $title=tvs_clean_text($m[1]);
  $title=preg_replace('~\s*[-|]\s*(Agência.*|Governo.*|Prefeitura.*|Portal.*)$~iu','',$title);
  if(tvs_is_skip_or_navigation_title($title)) $title=$fallbackTitle;

  $desc=tvs_extract_meta_content(
    $html,
    ['description','og:description','twitter:description']
  );

  if($desc==='' && !empty($jsonld['description'])){
    $desc=$jsonld['description'];
  }

  if(
    tvs_is_boilerplate($desc) ||
    (!$isOfficialNewsRoute &&
      tvs_is_institutional_profile_text($title,$url,$desc))
  ){
    $desc='';
  }

  $img=!empty($jsonld['image'])
    ? $jsonld['image']
    : tvs_extract_meta_image_from_html($url,$html);

  // Fallback direto para metadados Open Graph e Twitter.
  if($img===''){
    $img=tvs_extract_direct_meta_image($url,$html);
  }

  // Portais Next.js podem manter a capa dentro do __NEXT_DATA__.
  if($img===''){
    $img=tvs_extract_next_data_image($html);
  }

  $articleHtml=tvs_pick_article_html($html);
  $articleHtml=tvs_remove_boilerplate_from_html($articleHtml);
  preg_match_all('~<p[^>]*>(.*?)</p>~is',$articleHtml,$pm);
  $paras=[]; $seen=[];
  foreach($pm[1]??[] as $p){
    $txt=tvs_clean_text($p);
    if(tvs_is_boilerplate($txt)) continue;
    if(tvs_strlen($txt)<70 || tvs_strlen($txt)>1200) continue;
    $key=md5(tvs_lower($txt));
    if(isset($seen[$key])) continue;
    $seen[$key]=1;
    $paras[]=$txt;
    if(count($paras)>=14) break;
  }
  $body=implode("\n\n",$paras);

  // Prioridade para conteúdo jornalístico estruturado em JSON-LD.
  if(!empty($jsonld['body']) && tvs_strlen($jsonld['body'])>=100){
    $body=$jsonld['body'];
  }

  // Fallback para portais públicos que não usam o texto em tags <p>.
  // Extrai o conteúdo textual do bloco principal e reconstrói parágrafos úteis.
  if(tvs_strlen($body)<200){
    $plain=tvs_clean_text($articleHtml);
    $plain=tvs_remove_editorial_artifacts($plain);

    if(!tvs_is_boilerplate($plain) && tvs_strlen($plain)>=200){
      $sentences=preg_split('/(?<=[.!?])\\s+/u',$plain);
      $rebuilt=[];
      $buffer='';

      foreach($sentences as $sentence){
        $sentence=trim($sentence);

        if($sentence==='' || tvs_is_boilerplate($sentence)) continue;
        if(tvs_strlen($sentence)<30) continue;

        $buffer=trim($buffer.' '.$sentence);

        if(tvs_strlen($buffer)>=180){
          $rebuilt[]=$buffer;
          $buffer='';

          if(count($rebuilt)>=12) break;
        }
      }

      if($buffer!=='' && count($rebuilt)<12){
        $rebuilt[]=$buffer;
      }

      if($rebuilt){
        $body=implode("\n\n",$rebuilt);
      }
    }
  }

  // Último recurso: descrição oficial da página.
  if(tvs_strlen($body)<200 && $desc){
    $body=$desc;
  }

  if(
    !$isOfficialNewsRoute &&
    tvs_is_institutional_profile_text(
      $title ?: $fallbackTitle,
      $url,
      $desc.' '.$body
    )
  ){
    return [
      'title'=>$title ?: $fallbackTitle,
      'description'=>'',
      'body'=>'',
      'image'=>$img,
      'url'=>$url,
      'discard_reason'=>'Página institucional detectada'
    ];
  }

  // Normalização final depois de todas as fontes de conteúdo.
  // Separa letras e números que foram colados na serialização do portal.
  $body=preg_replace(
    '~(\p{L})(\d)~u',
    '$1 $2',
    (string)$body
  );

  $body=preg_replace(
    '~(\d)(\p{L})~u',
    '$1 $2',
    $body
  );

  $body=preg_replace('~[ \t]{2,}~u',' ',$body);
  $body=trim($body);

  return [
    'title'=>$title ?: $fallbackTitle,
    'description'=>$desc,
    'body'=>tvs_fix_joined_letters_numbers($body),
    'image'=>$img,
    'url'=>$url,
    'published_at'=>$jsonld['published_at']??''
  ];
}

function tvs_remove_editorial_artifacts($text){
  $text = (string)$text;
  $patterns = [
    '~A TV Sumar[eé] identificou[^\n.]*[.\n]*~iu',
    '~A TV Sumar[eé] preparou[^\n.]*[.\n]*~iu',
    '~A TV Sumar[eé] apurou automaticamente[^\n.]*[.\n]*~iu',
    '~Este rascunho[^\n.]*[.\n]*~iu',
    '~rascunho editorial[^\n.]*[.\n]*~iu',
    '~atualiza[cç][aã]o regional[^\n.]*[.\n]*~iu',
    '~monitor regional da TV Sumar[eé][^\n.]*[.\n]*~iu',
    '~Conte[uú]do gerado automaticamente[^\n.]*[.\n]*~iu',
    '~Antes da publica[cç][aã]o final[^\n.]*[.\n]*~iu',
    '~Moradores interessados devem acompanhar[^\n.]*[.\n]*~iu',
    '~O tema foi classificado[^\n.]*[.\n]*~iu',
    '~Uma informa[cç][aã]o divulgada por[^\n.]*[.\n]*~iu',
    '~A pauta tem rela[cç][aã]o[^\n.]*[.\n]*~iu',
    '~entrou no acompanhamento regional[^\n.]*[.\n]*~iu',
    '~permanece em revis[aã]o editorial[^\n.]*[.\n]*~iu',
    '~As primeiras informa[cç][oõ]es foram publicadas por[^\n.]*revis[aã]o editorial[^\n.]*[.\n]*~iu',
    '~A reda[cç][aã]o pode complementar[^\n.]*[.\n]*~iu',
    '~A informa[cç][aã]o interessa aos moradores[^\n.]*[.\n]*~iu',
  ];
  $text = preg_replace($patterns, '', $text);
  $text = preg_replace('/\n{3,}/', "\n\n", trim($text));
  return trim($text);
}

function tvs_first_sentence($text, $fallback=''){
  $text = trim(tvs_remove_editorial_artifacts(tvs_clean_text($text)));
  if($text === '') return $fallback;
  $parts = preg_split('/(?<=[.!?])\s+/u', $text);
  $sentence = trim($parts[0] ?? $text);
  if(tvs_strlen($sentence) > 220) $sentence = tvs_substr($sentence,0,217).'...';
  return $sentence ?: $fallback;
}


function tvs_normalize_article_body($text){
  $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = preg_replace('~</p>\s*<p[^>]*>~i', "\n\n", $text);
  $text = preg_replace('~<br\s*/?>~i', "\n", $text);
  $text = preg_replace('~</(p|div|li|h2|h3)>~i', "\n\n", $text);
  $text = strip_tags($text);
  $text = tvs_remove_editorial_artifacts($text);
  $lines = preg_split('/\n+/u', $text);
  $out=[];
  foreach($lines as $line){
    $line = trim(preg_replace('/\s+/u',' ', $line));
    if($line==='' || tvs_is_boilerplate($line)) continue;
    $out[]=$line;
  }
  $text = implode("\n\n", $out);
  $text = preg_replace('/\n{3,}/', "\n\n", trim($text));
  return $text;
}

function tvs_clean_article_field($text){
  return trim(tvs_clean_text(tvs_remove_editorial_artifacts((string)$text)));
}

function tvs_sanitize_ai_article($data, $src=null, $article=null){
  if(!is_array($data)) return $data;
  foreach(['title','subtitle','summary','category','seo_title','meta_description'] as $field){
    if(isset($data[$field])) $data[$field] = tvs_clean_article_field($data[$field]);
  }
  if(isset($data['body'])) $data['body'] = tvs_normalize_article_body($data['body']);
  if(isset($data['instagram_caption'])) $data['instagram_caption'] = tvs_clean_article_field($data['instagram_caption']);
  if(isset($data['whatsapp_text'])) $data['whatsapp_text'] = tvs_clean_article_field($data['whatsapp_text']);
  if(empty($data['subtitle']) && $article){
    $data['subtitle'] = tvs_first_sentence($article['description'] ?? $article['body'] ?? '', 'Informações foram divulgadas pela fonte oficial consultada.');
  }
  // Não cria mais fallback genérico como notícia. Se a IA não gerar corpo válido,
  // o Radar descarta a pauta antes de entrar na fila de aprovação.
  if(empty($data['category'])) $data['category']='Cidades';
  if(empty($data['tags']) || !is_array($data['tags'])){
    $data['tags']=[($src['city'] ?? 'Região'), $data['category']];
  }
  return $data;
}

function tvs_local_fallback_article($src, $article){
  $city = $src['city'] ?? 'região';
  $source = $src['name'] ?? 'fonte oficial consultada';
  $rawTitle = trim($article['title'] ?? '');
  $title = $rawTitle ?: (($source).' divulga informação de interesse regional');
  $desc = tvs_first_sentence($article['description'] ?? '', 'Informações foram divulgadas por '.$source.'.');
  $text = tvs_remove_editorial_artifacts($article['body'] ?? '');

  $paras=[];
  if($text){
    $chunks = preg_split('/\n{2,}/', trim($text));
    foreach($chunks as $c){
      $c = tvs_remove_editorial_artifacts($c);
      if($c && !tvs_is_boilerplate($c)) $paras[]=$c;
      if(count($paras)>=6) break;
    }
  }

  $body=[];
  $body[] = $desc;
  if($paras){
    foreach($paras as $p) $body[]=$p;
  } else {
    $body[] = 'Segundo a fonte consultada, o assunto envolve '.$city.' e pode ter reflexos para moradores, serviços públicos ou atividades da região.';
  }
  $body[] = 'Novas informações oficiais poderão detalhar prazos, locais, atendimento ao público ou eventuais desdobramentos do caso.';

  return [
    'title'=>$title,
    'subtitle'=>$desc,
    'body'=>tvs_remove_editorial_artifacts(implode("\n\n",$body)),
    'category'=>'Cidades',
    'tags'=>[$city,'Região',$source]
  ];
}

function tvs_read_json_file($path){
  if(!file_exists($path)) return [];
  $d=json_decode(file_get_contents($path),true);
  return is_array($d)?$d:[];
}
function tvs_save_json_file($path,$data){
  $dir=dirname($path); if(!is_dir($dir)) @mkdir($dir,0775,true);
  file_put_contents($path,json_encode(array_values($data), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
function tvs_get_sources(){
  $file=dirname(__DIR__).'/data/fontes.json';
  $sources=tvs_read_json_file($file);
  if(!$sources){
    $sources=[
      ['type'=>'oficial','city'=>'Sumaré','name'=>'Prefeitura de Sumaré','url'=>'https://sumare.sp.gov.br/','rss'=>''],
      ['type'=>'regional','city'=>'Região','name'=>'G1 Campinas e Região','url'=>'https://g1.globo.com/sp/campinas-regiao/','rss'=>'https://g1.globo.com/rss/g1/sp/campinas-regiao/']
    ];
    tvs_save_json_file($file,$sources);
  }
  return $sources;
}
function tvs_parse_rss($rssUrl, $src){
  if(!function_exists('simplexml_load_string')) return [];

  $xml=tvs_fetch_url($rssUrl);
  if(!$xml) return [];

  libxml_use_internal_errors(true);
  $sx=@simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
  if(!$sx) return [];

  $items=[];
  $nodes=[];

  if(isset($sx->channel->item)) $nodes=$sx->channel->item;
  elseif(isset($sx->entry)) $nodes=$sx->entry;

  foreach($nodes as $it){
    $title=tvs_clean_text((string)($it->title ?? ''));

    $link='';
    if(isset($it->link['href'])){
      $link=(string)$it->link['href'];
    } else {
      $link=(string)($it->link ?? '');
    }

    $rawDesc=(string)($it->description ?? $it->summary ?? $it->content ?? '');
    $desc=tvs_clean_text($rawDesc);
    $pub=(string)($it->pubDate ?? $it->published ?? $it->updated ?? '');

    if(!$title || !$link) continue;

    $image='';
    $imageType='';

    // 1. Media RSS: media:content e media:thumbnail
    $media=$it->children('http://search.yahoo.com/mrss/');

    if(isset($media->content)){
      foreach($media->content as $node){
        $attrs=$node->attributes();
        $candidate=trim((string)($attrs['url'] ?? ''));
        $type=trim((string)($attrs['type'] ?? ''));

        if(
          $candidate!=='' &&
          ($type==='' || stripos($type,'image/')===0) &&
          tvs_is_valid_image_url($candidate)
        ){
          $image=tvs_absolute_url($link,$candidate);
          $imageType='media:content';
          break;
        }
      }
    }

    if($image==='' && isset($media->thumbnail)){
      foreach($media->thumbnail as $node){
        $attrs=$node->attributes();
        $candidate=trim((string)($attrs['url'] ?? ''));

        if($candidate!=='' && tvs_is_valid_image_url($candidate)){
          $image=tvs_absolute_url($link,$candidate);
          $imageType='media:thumbnail';
          break;
        }
      }
    }

    // 2. RSS enclosure
    if($image==='' && isset($it->enclosure)){
      foreach($it->enclosure as $node){
        $attrs=$node->attributes();
        $candidate=trim((string)($attrs['url'] ?? ''));
        $type=trim((string)($attrs['type'] ?? ''));

        if(
          $candidate!=='' &&
          ($type==='' || stripos($type,'image/')===0) &&
          tvs_is_valid_image_url($candidate)
        ){
          $image=tvs_absolute_url($link,$candidate);
          $imageType='enclosure';
          break;
        }
      }
    }

    // 3. Atom link rel="enclosure"
    if($image==='' && isset($it->link)){
      foreach($it->link as $atomLink){
        $attrs=$atomLink->attributes();
        $rel=trim((string)($attrs['rel'] ?? ''));
        $type=trim((string)($attrs['type'] ?? ''));
        $candidate=trim((string)($attrs['href'] ?? ''));

        if(
          $rel==='enclosure' &&
          $candidate!=='' &&
          ($type==='' || stripos($type,'image/')===0) &&
          tvs_is_valid_image_url($candidate)
        ){
          $image=tvs_absolute_url($link,$candidate);
          $imageType='atom:enclosure';
          break;
        }
      }
    }

    // 4. content:encoded
    $encoded='';
    $contentNs=$it->children('http://purl.org/rss/1.0/modules/content/');
    if(isset($contentNs->encoded)){
      $encoded=(string)$contentNs->encoded;
    }

    // 5. Imagem presente na descrição ou no conteúdo completo
    if($image===''){
      $candidate=tvs_extract_image_from_rss_description(
        $link,
        $rawDesc."\n".$encoded
      );

      if($candidate!==''){
        $image=$candidate;
        $imageType='rss:description';
      }
    }

    // 6. Fallback na página original: og:image, twitter:image ou imagem HTML.
    if($image===''){
      $articleHtml=tvs_fetch_url($link);
      if($articleHtml!==''){
        $candidate=tvs_extract_meta_image_from_html($link,$articleHtml);
        if($candidate!==''){
          $image=$candidate;
          $imageType='article:meta_html';
        }
      }
    }

    $items[]=[
      'title'=>$title,
      'url'=>$link,
      'description'=>$desc,
      'published_at'=>$pub,
      'source'=>$src['name']??'',
      'city'=>$src['city']??'Região',
      'image'=>$image,
      'image_source_type'=>$imageType,
      'image_review_required'=>0,
      'image_review_reason'=>''
    ];

    if(count($items)>=5) break;
  }

  // Bloqueia imagem externa repetida em matérias diferentes do mesmo lote.
  // Isso evita aceitar imagens genéricas ou OG incorreto reutilizado pela fonte.
  $imageCounts=[];

  foreach($items as $item){
    $candidate=trim((string)($item['image']??''));
    if($candidate==='') continue;
    $key=strtolower($candidate);
    $imageCounts[$key]=($imageCounts[$key]??0)+1;
  }

  foreach($items as &$item){
    $candidate=trim((string)($item['image']??''));
    if($candidate==='') continue;

    $key=strtolower($candidate);

    if(($imageCounts[$key]??0)>1){
      $item['image_review_required']=1;
      $item['image_review_reason']='Imagem repetida em matérias diferentes do mesmo lote';

      // Não publica automaticamente uma imagem possivelmente incorreta.
      $item['image']='';
      $item['image_source_type']='rejected:duplicate_batch';
    }
  }
  unset($item);

  return $items;
}
function tvs_capture_source_items($src){
  if(!empty($src['rss'])){
    $items=tvs_parse_rss($src['rss'],$src);
    if($items) return $items;
  }
  $home=tvs_fetch_url($src['url']??'');
  $links=tvs_extract_links($src['url']??'', $home);
  $out=[];
  foreach($links as $l){ $out[]=['title'=>$l['title'],'url'=>$l['url'],'description'=>'','source'=>$src['name']??'', 'city'=>$src['city']??'Região']; }
  // Não cria pauta genérica a partir da home da fonte. Isso evita transformar página institucional em notícia.
  // Se nenhuma chamada jornalística for encontrada, a fonte simplesmente fica sem itens neste ciclo.
  return $out;
}
function tvs_update_breaking($items){
  $file=dirname(__DIR__).'/data/ultimahora.json';
  $current=tvs_read_json_file($file); $seen=[]; $merged=[];
  foreach($items as $it){
    $url=$it['url']??''; if(!$url || isset($seen[$url])) continue; $seen[$url]=1;
    $merged[]=['id'=>md5($url),'title'=>$it['title']??'Atualização regional','url'=>$url,'source'=>$it['source']??'Fonte regional','city'=>$it['city']??'Região','created_at'=>date('c')];
  }
  foreach($current as $it){
    $url=$it['url']??''; if(!$url || isset($seen[$url])) continue; $seen[$url]=1; $merged[]=$it;
    if(count($merged)>=20) break;
  }
  tvs_save_json_file($file,array_slice($merged,0,20));
}
function tvs_slug($s){
  $s=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  $s=strtolower(preg_replace('~[^a-zA-Z0-9]+~','-', $s));
  return trim($s,'-') ?: uniqid('noticia');
}

// Compatibilidade extra para o Radar Regional na HostGator.
// Estas funções evitam erro 500 quando o servidor não encontra imagem da fonte
// ou quando o fallback local precisa montar parágrafos melhores.
if(!function_exists('tvs_category_image_path')){
  function tvs_category_image_path($category){
    $cat = tvs_lower((string)$category);
    $map = [
      'saúde'=>'assets/cat-saude.svg', 'saude'=>'assets/cat-saude.svg',
      'educação'=>'assets/cat-educacao.svg', 'educacao'=>'assets/cat-educacao.svg',
      'esporte'=>'assets/cat-esportes.svg', 'esportes'=>'assets/cat-esportes.svg',
      'segurança'=>'assets/cat-seguranca.svg', 'seguranca'=>'assets/cat-seguranca.svg',
      'política'=>'assets/cat-politica.svg', 'politica'=>'assets/cat-politica.svg',
      'cultura'=>'assets/cat-cultura.svg',
      'emprego'=>'assets/cat-empregos.svg', 'empregos'=>'assets/cat-empregos.svg',
      'economia'=>'assets/cat-economia.svg',
      'cidade'=>'assets/cat-cidade.svg', 'cidades'=>'assets/cat-cidade.svg',
      'trânsito'=>'assets/cat-cidade.svg', 'transito'=>'assets/cat-cidade.svg',
      'turismo'=>'assets/cat-cultura.svg',
      'utilidade'=>'assets/cat-cidade.svg',
    ];
    foreach($map as $key=>$path){ if(strpos($cat,$key)!==false) return $path; }
    return 'assets/logo-tv-sumare.jpeg';
  }
}

if(!function_exists('tvs_is_valid_image_url')){
  function tvs_is_valid_image_url($img){

  $url=trim((string)$img);

  // Google Notícias usa uma miniatura genérica idêntica para diversas pautas.
  // Ela não representa a fotografia da matéria e nunca deve ser aceita.
  if(preg_match(
    '~(?:^|//)(?:lh3\.)?googleusercontent\.com/~i',
    $url
  )){
    return false;
  }

  if(preg_match(
    '~(?:news\.google\.com|google\.com/news)~i',
    $url
  )){
    return false;
  }

    $img = trim((string)$img);
    if($img==='') return false;
    if(preg_match('~^(data:|javascript:)~i',$img)) return false;
    if(preg_match('~(logo|icone|icon|avatar|sprite|placeholder|whatsapp|facebook|instagram|youtube|twitter|linkedin)~i',$img)) return false;
    return true;
  }
}

if(!function_exists('tvs_best_image')){
  function tvs_best_image($primary='', $secondary='', $category='Cidade'){
    $primary = trim((string)$primary);
    $secondary = trim((string)$secondary);
    if(tvs_is_valid_image_url($primary)) return $primary;
    if(tvs_is_valid_image_url($secondary)) return $secondary;
    return tvs_category_image_path($category);
  }
}

if(!function_exists('tvs_quality_paragraphs')){
  function tvs_quality_paragraphs($text, $limit=8){
    $text = tvs_remove_editorial_artifacts((string)$text);
    $text = preg_replace('/\s+/u',' ',trim($text));
    if($text==='') return [];
    $sentences = preg_split('/(?<=[.!?])\s+/u',$text);
    $out=[]; $buf='';
    foreach($sentences as $s){
      $s = trim($s);
      if($s==='' || tvs_is_boilerplate($s)) continue;
      if(tvs_strlen($s)<35) continue;
      $buf = trim($buf.' '.$s);
      if(tvs_strlen($buf)>=180){
        $out[]=$buf; $buf='';
        if(count($out)>=$limit) break;
      }
    }
    if($buf!=='' && count($out)<$limit) $out[]=$buf;
    if(!$out){
      $chunks = preg_split('/\n{2,}/', (string)$text);
      foreach($chunks as $c){
        $c=trim($c);
        if($c!=='' && !tvs_is_boilerplate($c)){ $out[]=$c; if(count($out)>=$limit) break; }
      }
    }
    return array_values(array_filter($out));
  }
}

/* BUILD_1.0.2 - bloqueio definitivo de pautas antigas/duplicadas antes de entrarem na redação */
if(!function_exists('tvs_monitor_age_days')){
function tvs_monitor_age_days($item){
  $raw=$item['published_at']??$item['created_at']??$item['date']??'';
  if(!$raw) return 0;
  $ts=strtotime((string)$raw);
  if(!$ts) return 0;
  return (int)floor((time()-$ts)/86400);
}
function tvs_monitor_is_old($item,$maxDays=14){
  $age=tvs_monitor_age_days($item);
  return $age>$maxDays;
}
function tvs_monitor_title_key($title){
  $t=tvs_clean_text((string)$title);
  $t=preg_replace('/\s*(?:[-–—|•:]\s*)?(G1|Portal ON|sampi\.net\.br|Sampi Campinas|Hora Campinas|Notícia FM|Noticias FM|Hortonews|Google News|Google Notícias|R7|UOL)\s*$/iu','',$t);
  $t=tvs_lower($t);
  $t=preg_replace('/[^a-z0-9áàâãéèêíïóôõöúçñ]+/u',' ', $t);
  $drop=['prefeitura','municipal','de','da','do','das','dos','em','para','com','e','a','o','as','os','um','uma','nesta','neste','veja','como','saiba'];
  $parts=array_values(array_filter(explode(' ',trim($t)),function($w)use($drop){return strlen($w)>2&&!in_array($w,$drop,true);}));
  return implode(' ',array_slice($parts,0,14));
}
function tvs_monitor_discard($item,$reason){
  $file=dirname(__DIR__).'/data/pautas_descartadas.json';
  $arr=tvs_read_json_file($file);
  $arr[]=['data'=>date('d/m H:i'),'status'=>'DESCARTADA','cidade'=>$item['city']??'Região','fonte'=>$item['source']??'Fonte','titulo'=>$item['title']??'','motivo'=>$reason,'url'=>$item['url']??''];
  $arr=array_slice($arr,-300);
  tvs_save_json_file($file,$arr);
}
function tvs_monitor_seen_keys($drafts,$news){
  $seen=[];
  foreach(array_merge((array)$drafts,(array)$news) as $n){
    if(!empty($n['source_url'])) $seen['url:'.$n['source_url']]=1;
    if(!empty($n['url'])) $seen['url:'.$n['url']]=1;
    $tk=tvs_monitor_title_key($n['title']??'');
    $city=tvs_lower($n['city']??'');
    if($tk) $seen['title:'.md5($city.'|'.$tk)]=1;
  }
  return $seen;
}
function tvs_monitor_item_is_duplicate($item,$seen){
  $url=$item['url']??'';
  if($url && isset($seen['url:'.$url])) return true;
  $tk=tvs_monitor_title_key($item['title']??'');
  $city=tvs_lower($item['city']??'');
  return $tk && isset($seen['title:'.md5($city.'|'.$tk)]);
}
}
