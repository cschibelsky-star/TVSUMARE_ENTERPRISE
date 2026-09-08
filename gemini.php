<?php
require_once (is_file(__DIR__.'/includes/outbound_guard.php') ? __DIR__.'/includes/outbound_guard.php' : dirname(__DIR__).'/includes/outbound_guard.php');
require_once (is_file(__DIR__.'/includes/centro_ia_helper.php') ? __DIR__.'/includes/centro_ia_helper.php' : dirname(__DIR__).'/includes/centro_ia_helper.php');
function tvs_ai_substr($s,$start,$len=null){ if(function_exists('tvs_substr')) return tvs_substr($s,$start,$len); return function_exists('mb_substr') ? mb_substr((string)$s,$start,$len,'UTF-8') : substr((string)$s,$start,$len); }
function tvs_ai_strlen($s){ if(function_exists('tvs_strlen')) return tvs_strlen($s); return function_exists('mb_strlen') ? mb_strlen((string)$s,'UTF-8') : strlen((string)$s); }
function tvs_ai_log($msg){
    $file = dirname(__DIR__).'/data/ia_erros.log';
    @file_put_contents($file, '['.date('c').'] '.$msg."\n", FILE_APPEND);
}

function tvs_gemini_models(){
    $models=[];
    if(isset($GLOBALS['gemini_model']) && trim((string)$GLOBALS['gemini_model'])!=='') $models[]=trim((string)$GLOBALS['gemini_model']);
    if(isset($GLOBALS['gemini_fallback_models']) && is_array($GLOBALS['gemini_fallback_models'])){
        foreach($GLOBALS['gemini_fallback_models'] as $m){ if(trim((string)$m)!=='') $models[]=trim((string)$m); }
    }
    foreach(['gemini-2.5-flash','gemini-2.5-flash-lite'] as $m) $models[]=$m;
    $out=[];
    foreach($models as $m){ if(!in_array($m,$out,true)) $out[]=$m; }
    return $out;
}

function tvs_gemini_extract_json($txt){
    $txt=trim((string)$txt);
    $txt=preg_replace('/^```json\s*/i','',$txt);
    $txt=preg_replace('/^```\s*/','',$txt);
    $txt=preg_replace('/\s*```$/','',$txt);
    $data=json_decode($txt,true);
    if(!is_array($data) && preg_match('/\{.*\}/s',$txt,$m)) $data=json_decode($m[0],true);
    return is_array($data) ? $data : null;
}

function tvs_gemini_generate_text($apiKey,$prompt,$generationConfig=[],$timeout=22){
    $generationConfig=array_merge(['temperature'=>0.25,'maxOutputTokens'=>1800], is_array($generationConfig)?$generationConfig:[]);

    // Preferred path for TV Digital Enterprise: shared Core / Centro de IA.
    if(function_exists('tvd_centro_ia_generate_text')){
        $hub=tvd_centro_ia_generate_text((string)$prompt,[
            'temperature'=>(float)($generationConfig['temperature']??0.25),
        ]);
        if(!empty($hub['ok'])) return $hub;
        tvs_ai_log('Centro de IA indisponível; usando fallback direto temporário: '.($hub['error']??'sem detalhe'));
    }

    // Temporary compatibility fallback while the Enterprise baseline is decoupled.
    $apiKey=trim((string)$apiKey);
    if($apiKey==='') return ['ok'=>false,'error'=>'Centro de IA indisponível e chave Gemini de fallback ausente.'];
    if(!function_exists('curl_init')) return ['ok'=>false,'error'=>'cURL não está habilitado no servidor.'];
    $payload=json_encode([
        'contents'=>[['role'=>'user','parts'=>[['text'=>(string)$prompt]]]],
        'generationConfig'=>$generationConfig
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $lastError='';
    foreach(tvs_gemini_models() as $model){
        $url='https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';
        $outboundOptions=tvs_outbound_curl_options($url,(int)$timeout);
        if($outboundOptions===null){
            $lastError='URL Gemini bloqueada pela politica de saida.';
            tvs_ai_log($lastError);
            continue;
        }
        $ch=curl_init($url);
        curl_setopt_array($ch,$outboundOptions+[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-goog-api-key: '.$apiKey],
            CURLOPT_POSTFIELDS=>$payload
        ]);
        $res=curl_exec($ch);
        $curlErr=curl_error($ch);
        $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        if(is_string($res) && strlen($res)>2097152){
            $lastError='Resposta Gemini excedeu o limite seguro.';
            tvs_ai_log($lastError);
            continue;
        }
        if($res===false || $res===''){
            $lastError='Sem resposta do Gemini no modelo '.$model.($curlErr?': '.$curlErr:'');
            tvs_ai_log($lastError);
            continue;
        }
        $j=json_decode((string)$res,true);
        if($http>=400){
            $msg='HTTP Gemini '.$http.' no modelo '.$model.': '.substr((string)$res,0,1200);
            $lastError=$msg;
            tvs_ai_log($msg);
            continue;
        }
        if(!is_array($j)){
            $lastError='Resposta Gemini não é JSON no modelo '.$model.': '.substr((string)$res,0,1000);
            tvs_ai_log($lastError);
            continue;
        }
        if(isset($j['error'])){
            $lastError='Erro Gemini no modelo '.$model.': '.json_encode($j['error'],JSON_UNESCAPED_UNICODE);
            tvs_ai_log($lastError);
            continue;
        }
        $txt=$j['candidates'][0]['content']['parts'][0]['text']??'';
        $txt=trim((string)$txt);
        if($txt===''){
            $lastError='Gemini retornou texto vazio no modelo '.$model.'.';
            tvs_ai_log($lastError);
            continue;
        }
        return ['ok'=>true,'text'=>$txt,'model'=>$model,'provider'=>'gemini-direct-fallback','raw'=>$j];
    }
    return ['ok'=>false,'error'=>$lastError ?: 'Falha desconhecida ao chamar Gemini.'];
}

function tvs_ai_style_rules($style, $approach='Informativa', $size='Média'){
    $style = trim((string)$style) ?: 'Jornalístico profissional';
    $approach = trim((string)$approach) ?: 'Informativa';
    $size = trim((string)$size) ?: 'Média';
    $map = [
        'Jornalístico profissional' => 'Matéria de portal regional profissional: lead forte, linguagem clara, contextualização local, serviço ao leitor e fechamento objetivo. Sem propaganda, sem opinião e sem frases genéricas.',
        'Notícia padrão' => 'Matéria de portal regional profissional: lead claro, linguagem objetiva, contexto local e serviço ao leitor. Sem propaganda, sem opinião e sem frases genéricas.',
        'Última hora' => 'Texto urgente e enxuto. Priorize fato principal, cidade, horário/data, impacto imediato e orientação. Use 3 a 5 parágrafos curtos. Não use alarmismo.',
        'Política' => 'Matéria política institucional equilibrada. Explique decisão, órgão envolvido, impacto para a população, próximos passos e contexto. Não tome lado.',
        'Esporte' => 'Matéria esportiva regional. Destaque competição, categoria, inscrições, datas, equipes, atletas, resultados ou calendário. Linguagem dinâmica, mas precisa.',
        'Segurança' => 'Matéria de segurança pública com cautela jurídica. Evite exposição indevida, termos acusatórios e conclusões não confirmadas. Use linguagem factual.',
        'Saúde' => 'Matéria de saúde pública com foco em serviço: público atendido, local, datas, horários, documentos, orientação e canais oficiais.',
        'Educação' => 'Matéria educacional para famílias e comunidade: escolas, alunos, prazos, matrículas, vagas, calendário e impacto local.',
        'Cultura' => 'Matéria cultural com programação, artistas, local, data, público, inscrições e relevância para a cidade.',
        'Empregos' => 'Matéria de serviço com vagas, requisitos, inscrição, prazos, local e orientação prática. Seja direto e útil.',
        'Utilidade pública' => 'Matéria de serviço ao cidadão. Explique o que muda, quem é afetado, como acessar, prazos, documentos e contatos oficiais.',
        'Defesa Civil / Alerta' => 'Alerta público objetivo. Informe risco, cidades afetadas, período, cuidados recomendados e canais de emergência. Tom sério, sem pânico.',
        'Release institucional' => 'Transforme release em notícia jornalística. Remova excesso promocional, organize fatos e mantenha neutralidade.',
        'Publieditorial' => 'Conteúdo comercial identificado, informativo e persuasivo. Destaque serviço, diferenciais, cidade, contato e chamada para ação, sem promessas exageradas.',
        'Guia comercial / publieditorial' => 'Conteúdo comercial identificado, informativo e persuasivo. Destaque serviço, diferenciais, cidade, contato e chamada para ação, sem promessas exageradas.'
    ];
    $len = [
        'Curta' => '3 a 5 parágrafos curtos, sem enrolação.',
        'Média' => '5 a 8 parágrafos bem estruturados.',
        'Completa' => '8 a 12 parágrafos, com mais contexto, serviço ao leitor e SEO quando o material permitir.'
    ];
    return "PERFIL EDITORIAL: {$style}. ".($map[$style] ?? $map['Jornalístico profissional'])."\nABORDAGEM: {$approach}. Ajuste o tom conforme essa abordagem, sem opinião e sem inventar fatos.\nTAMANHO: ".($len[$size] ?? $len['Média']);
}

function tvs_professional_fallback_article($title,$sourceText,$city,$source,$style='Jornalístico profissional'){
    $clean = strip_tags((string)$sourceText);
    $clean = preg_replace('/\s+/', ' ', trim($clean));
    $clean = preg_replace('/\b(Menu|Facebook|Instagram|Youtube|YouTube|Whatsapp|WhatsApp|X-twitter|Linkedin|fechar|Download)\b/i', '', $clean);
    $sent = preg_split('/(?<=[.!?])\s+/', $clean);
    $sent = array_values(array_filter(array_map('trim', $sent)));
    $main = $sent[0] ?? 'Uma nova informação de interesse público foi divulgada para moradores da região.';
    $subtitle = $sent[1] ?? 'Informações foram divulgadas pela fonte oficial consultada e devem ser acompanhadas pela população.';
    $title = trim($title) ?: preg_replace('/\.$/', '', tvs_ai_substr($main,0,95));
    if($title==='') $title='Informação regional é divulgada para moradores de '.$city;
    $paras=[];
    $paras[]=$main;
    $middle = array_slice($sent,1,5);
    foreach($middle as $m){ if(tvs_ai_strlen($m)>45) $paras[]=$m; }
    if(count($paras)<3){
        $paras[]='O assunto pode ter impacto para moradores, famílias, empresas ou serviços públicos da região.';
        $paras[]='Novas informações oficiais poderão detalhar prazos, locais, atendimento ao público ou eventuais desdobramentos.';
    }
    $paras[]='A publicação poderá ser atualizada caso novas informações oficiais sejam divulgadas.';
    $body = implode("\n\n", array_slice($paras,0,8));
    $cat = 'Cidades';
    if(stripos($style,'Esporte')!==false) $cat='Esportes';
    if(stripos($style,'Saúde')!==false) $cat='Saúde';
    if(stripos($style,'Educação')!==false) $cat='Educação';
    if(stripos($style,'Alerta')!==false || stripos($style,'Defesa')!==false) $cat='Utilidade Pública';
    $brand = trim((string)($GLOBALS['site_nome'] ?? 'TV Digital')) ?: 'TV Digital';
    $brandTag = trim((string)($GLOBALS['site_brand_hashtag'] ?? '#TVDigital')) ?: '#TVDigital';
    return [
        'title'=>$title,
        'subtitle'=>$subtitle,
        'summary'=>tvs_ai_substr($main,0,180),
        'body'=>$body,
        'category'=>$cat,
        'tags'=>array_values(array_filter([$city,$source,$cat,$brand])),
        'seo_title'=>$title,
        'meta_description'=>tvs_ai_substr($main,0,155),
        'slug'=>function_exists('tvs_slug')?tvs_slug($title):strtolower(preg_replace('/[^a-z0-9]+/i','-',$title)),
        'instagram_caption'=>$title."\n\nConfira a matéria completa no portal.\n\n".$brandTag,
        'whatsapp_text'=>$title."\n\nLeia a matéria completa no portal."
    ];
}

function gemini_rewrite($apiKey, $input, $options=[]){
    $style = $options['style'] ?? 'Notícia padrão';
    $approach = $options['approach'] ?? 'Informativa';
    $size = $options['size'] ?? 'Média';
    $city = $options['city'] ?? ($GLOBALS['site_city'] ?? 'Região');
    $source = $options['source'] ?? 'Fonte consultada';
    $sourceUrl = $options['source_url'] ?? '';
    $mode = $options['mode'] ?? 'article';
    $brand = trim((string)($GLOBALS['site_nome'] ?? 'TV Digital')) ?: 'TV Digital';

    if($mode === 'social'){
      $prompt="Você é social media de um portal regional de notícias. Crie conteúdo para divulgação de uma matéria do portal {$brand}.\nRetorne SOMENTE JSON válido, sem markdown. Campos: instagram_caption, whatsapp_text, hashtags.\nRegras: legenda curta, jornalística, sem sensacionalismo; inclua chamada para ler a matéria; hashtags em array; não invente informações.\n\nMatéria:\n".$input;
      $gen=['temperature'=>0.35,'maxOutputTokens'=>900];
    } else {
      $prompt="Você é EDITOR-CHEFE de um portal regional profissional chamado {$brand}. Sua tarefa é transformar o material abaixo em uma matéria jornalística completa, pronta para o editor humano apenas revisar e aprovar.\n\n".
      tvs_ai_style_rules($style,$approach,$size)."\nCIDADE/REGIÃO PRIORITÁRIA: {$city}\nFONTE CONSULTADA: {$source}\nLINK DA FONTE: {$sourceUrl}\n\nFORMATO DE SAÍDA OBRIGATÓRIO:\nRetorne SOMENTE JSON válido, sem markdown, sem comentários e sem texto fora do JSON.\nCampos obrigatórios: title, subtitle, summary, body, category, tags, seo_title, meta_description, slug, instagram_caption, whatsapp_text.\n\nPADRÃO JORNALÍSTICO OBRIGATÓRIO:\n- A matéria deve parecer escrita por uma redação profissional de portal regional.\n- Comece direto pelo fato. NÃO use introduções artificiais.\n- O primeiro parágrafo deve responder claramente: quem, o quê, quando, onde e impacto/serviço quando disponível.\n- Use parágrafos curtos, objetivos e bem organizados.\n- Inclua contexto local e utilidade ao leitor quando o material permitir.\n- Quando houver inscrições, evento, serviço, atendimento, prazo ou mudança pública, inclua um parágrafo específico com orientação prática.\n- Título específico, informativo e sem clickbait.\n- Subtítulo complementar, sem repetir o título.\n- Summary com até 180 caracteres.\n- Meta description com até 155 caracteres.\n- Slug minúsculo, sem acento, com hífens.\n\nPROIBIÇÕES ABSOLUTAS:\n- Não use: 'conteúdo gerado automaticamente', 'rascunho', 'monitor regional' ou 'pauta encontrada'.\n- Não mencione IA, robô, automação, revisão editorial ou que o texto será revisado.\n- Não copie menus, rodapés, cabeçalhos, botões, links de redes sociais ou navegação.\n- Não invente fatos, números, datas, nomes, cargos, declarações ou locais.\n- Não use opinião, adjetivos exagerados ou propaganda, exceto quando o estilo for Publieditorial.\n- Não coloque crédito da fonte dentro do corpo da matéria; o sistema exibirá a fonte separadamente.\n\nCATEGORIAS PERMITIDAS:\nCidades, Política, Segurança, Saúde, Educação, Esportes, Cultura, Empregos, Trânsito, Economia, Turismo, Utilidade Pública, Publicidade.\n\nTAGS:\nRetorne tags como array com 3 a 7 termos úteis, incluindo cidade quando fizer sentido.\n\nMATERIAL BASE:\n".$input;
      $gen=['temperature'=>0.28,'maxOutputTokens'=>2200];
    }

    $r=tvs_gemini_generate_text($apiKey,$prompt,$gen,24);
    if(empty($r['ok'])){ tvs_ai_log('Falha de geração editorial: '.($r['error']??'sem detalhe')); return null; }
    $data=tvs_gemini_extract_json($r['text']??'');
    if(!is_array($data)) { tvs_ai_log('Texto de IA sem JSON válido: '.substr((string)($r['text']??''),0,1000)); return null; }

    if($mode !== 'social'){
      if(!empty($data['discard'])){ tvs_ai_log('IA descartou pauta: '.($data['reason']??'sem motivo')); return null; }
      foreach(['title','subtitle','body'] as $field){ if(empty($data[$field])) { tvs_ai_log('Campo ausente no JSON editorial: '.$field); return null; } }
      if(empty($data['category'])) $data['category']='Cidades';
      if(empty($data['tags']) || !is_array($data['tags'])) $data['tags']=[];
    }
    return $data;
}

function gemini_reporter_article($apiKey, $material, $options=[]){
    $city = $options['city'] ?? ($GLOBALS['site_city'] ?? 'Região');
    $theme = $options['theme'] ?? 'Tema regional';
    $category = $options['category'] ?? 'Cidade';
    $brand = trim((string)($GLOBALS['site_nome'] ?? 'TV Digital')) ?: 'TV Digital';
    $prompt="Você é o REPÓRTER-CHEFE de {$brand}, um portal regional de notícias. Você recebe material coletado de fontes públicas e deve produzir UMA REPORTAGEM ORIGINAL, completa e publicável para aprovação humana.\n\n".
    "CIDADE PRIORITÁRIA: {$city}\nCATEGORIA EDITORIAL: {$category}\nASSUNTO APURADO: {$theme}\nPADRÃO: jornalístico profissional regional.\n\n".
    "RETORNE SOMENTE JSON VÁLIDO, sem markdown e sem texto fora do JSON.\n".
    "Campos obrigatórios: title, subtitle, summary, body, category, tags, seo_title, meta_description, slug, instagram_caption, whatsapp_text.\n\n".
    "PROCESSO EDITORIAL OBRIGATÓRIO ANTES DE ESCREVER:\n".
    "1. Identifique o fato principal: o que aconteceu, quem está envolvido, onde, quando e por que importa.\n".
    "2. Ignore páginas institucionais, páginas de secretaria, contato, estrutura administrativa e textos permanentes sem fato novo.\n".
    "3. Se o material não trouxer fato jornalístico suficiente, retorne JSON com {\\\"discard\\\":true,\\\"reason\\\":\\\"sem fato jornalístico suficiente\\\"}.\n".
    "4. Use apenas fatos presentes no material. Não invente números, datas, locais, nomes ou declarações.\n\n".
    "PADRÃO DA REPORTAGEM:\n".
    "- Título específico, factual e profissional.\n".
    "- Subtítulo complementar, com impacto ou serviço ao leitor.\n".
    "- Body em TEXTO PURO, sem tags HTML, com parágrafos separados por linha em branco.\n".
    "- Primeiro parágrafo com lead: quem, o quê, quando, onde e impacto.\n".
    "- Desenvolvimento com contexto regional e relevância para moradores.\n".
    "- Quando houver serviço público, inclua informações úteis: prazos, locais, inscrições, público-alvo ou canais oficiais, somente se constarem no material.\n".
    "- Encerramento objetivo.\n".
    "- Meta description com até 155 caracteres. Summary com até 180 caracteres. Slug sem acento, minúsculo e com hífens.\n".
    "- Tags como array com 3 a 7 termos úteis, incluindo cidade e categoria quando fizer sentido.\n\n".
    "PROIBIÇÕES ABSOLUTAS:\n".
    "- Não use: rascunho, monitor regional, pauta encontrada, conteúdo gerado automaticamente, antes da publicação final, moradores interessados devem acompanhar.\n".
    "- Não mencione IA, robô, automação ou revisão editorial.\n".
    "- Não descreva órgão público como se fosse notícia. O fato é a ação, evento, programa, obra, serviço, operação ou decisão.\n".
    "- Não copie trechos longos da fonte. Reescreva com estrutura própria.\n".
    "- Não coloque crédito da fonte no corpo; o sistema exibirá a fonte discretamente no final.\n\n".
    "MATERIAL COLETADO E APURADO:\n{$material}";
    $r=tvs_gemini_generate_text($apiKey,$prompt,['temperature'=>0.22,'maxOutputTokens'=>2600],28);
    if(empty($r['ok'])){ tvs_ai_log('Falha reporter editorial: '.($r['error']??'sem detalhe')); return null; }
    $data=tvs_gemini_extract_json($r['text']??'');
    if(!is_array($data)){ tvs_ai_log('Reporter sem JSON válido: '.substr((string)($r['text']??''),0,1200)); return null; }
    if(!empty($data['discard'])){ tvs_ai_log('Reporter descartou pauta: '.($data['reason']??'sem motivo')); return null; }
    foreach(['title','subtitle','body'] as $field){ if(empty($data[$field])){ tvs_ai_log('Reporter sem campo: '.$field); return null; } }
    if(empty($data['category'])) $data['category']=$category ?: 'Cidade';
    if(empty($data['tags']) || !is_array($data['tags'])) $data['tags']=[$city,$category,$brand];
    return $data;
}
?>
