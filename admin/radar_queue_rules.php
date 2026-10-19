<?php
require_once dirname(__DIR__).'/includes/radar_source_rules.php';


if (!function_exists('tvs_radar_editorial_category')) {
    /**
     * Classificação conservadora pela manchete, não por palavras ocasionais
     * no corpo (ex.: acidente citado numa reportagem esportiva).
     * Sem evidência inequívoca, preserva a editoria atual.
     */
    function tvs_radar_editorial_category(array $item): string
    {
        $current = trim((string)($item['category'] ?? 'Cidade')) ?: 'Cidade';
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') return $current;

        if (preg_match('~acidente|colis[aã]o|atropelamento|capotamento|roubo|assalto|pris[aã]o|apreens[aã]o|persegui[cç][aã]o policial~iu', $title)) return 'Segurança';
        if (preg_match('~\\b(?:vagas?|empregos?)\\b|\\bPAT\\b|contrata[cç][aã]o|processo seletivo|oportunidades de trabalho~iu', $title)) return 'Empregos';
        if (preg_match('~\\b(?:vereador(?:a|es)?|c[aâ]mara municipal|mo[cç][aã]o|requerimento parlamentar)\\b~iu', $title)) return 'Política';
        if (preg_match('~investimentos?|networking|empreendedores?|neg[oó]cios|coopera[cç][aã]o empresarial|economia|ind[uú]stria|empretec~iu', $title)) return 'Economia';
        if (preg_match('~ponto facultativo|decreto municipal|varri[cç][aã]o|transporte p[uú]blico|tr[aâ]nsito|interdi[cç][aã]o|pavimenta[cç][aã]o|avenida|chuvas?|temporal|alagamento~iu', $title)) return 'Cidade';
        if (preg_match('~sa[uú]de|vacina[cç][aã]o|hospital|odontol[oó]gic|sa[uú]de bucal|c[aâ]ncer|dengue~iu', $title)) return 'Saúde';
        if (preg_match('~cursos?|minicursos?|escola|alunos?|matr[ií]cula|educa[cç][aã]o|ensino|capacita[cç][aã]o|palestra educativa~iu', $title)) return 'Educação';
        if (preg_match('~festival|show|m[uú]sica|teatro|cultura(?:l|is)?|artesanato|festa das na[cç][oõ]es|oficina de roteiro~iu', $title)) return 'Cultura';
        if (preg_match('~campeonato|futebol|v[oô]lei|basquete|corrida|torneio|atletas?|competi[cç][aã]o|jogos regionais~iu', $title)) return 'Esportes';
        return $current;
    }
}

if (!function_exists('tvs_radar_temporal_review_reasons')) {
    /**
     * Sinaliza anúncios de eventos/serviços com data explicitamente vencida.
     * Não transforma notícias retrospectivas em eventos expirados.
     * $now é injetável para testes determinísticos.
     */
    function tvs_radar_temporal_review_reasons(array $item, ?int $now = null): array
    {
        $now = $now ?? time();
        $headline = trim((string)($item['title'] ?? '').' '.(string)($item['subtitle'] ?? '').' '.(string)($item['summary'] ?? ''));
        if ($headline === '') return [];
        if (!preg_match('~\\b(?:acontece|acontecer[aá]|ser[aá]\\s+(?:realizad[ao]|promovid[ao])|neste|pr[oó]xim[ao]|inscri[cç][oõ]es|programa[cç][aã]o|rodada decisiva|vai acontecer|vai realizar|oferecer[aá])\\b~iu', $headline)) return [];

        $months = [
            'janeiro'=>1,'fevereiro'=>2,'março'=>3,'marco'=>3,'abril'=>4,
            'maio'=>5,'junho'=>6,'julho'=>7,'agosto'=>8,'setembro'=>9,
            'outubro'=>10,'novembro'=>11,'dezembro'=>12
        ];
        $source = strtotime((string)($item['source_published_at'] ?? $item['published_at'] ?? $item['created_at'] ?? ''));
        $base = $source ?: $now;
        $baseYear = (int)date('Y', $base);
        $baseMonth = (int)date('n', $base);
        $dates = [];

        if (preg_match_all('~\\b([0-3]?\\d)\\s+de\\s+(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(?:\\s+de\\s+(20\\d{2}))?\\b~iu', $headline, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $month = $months[mb_strtolower($m[2], 'UTF-8')] ?? 0;
                $year = !empty($m[3]) ? (int)$m[3] : $baseYear;
                if (empty($m[3]) && $baseMonth >= 11 && $month <= 2) $year++;
                if (checkdate($month, (int)$m[1], $year)) $dates[] = sprintf('%04d-%02d-%02d', $year, $month, (int)$m[1]);
            }
        }
        if (preg_match_all('~\\b([0-3]?\\d)[/\\-]([01]?\\d)(?:[/\\-](20\\d{2}))?\\b~u', $headline, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $month = (int)$m[2];
                $year = !empty($m[3]) ? (int)$m[3] : $baseYear;
                if (empty($m[3]) && $baseMonth >= 11 && $month <= 2) $year++;
                if (checkdate($month, (int)$m[1], $year)) $dates[] = sprintf('%04d-%02d-%02d', $year, $month, (int)$m[1]);
            }
        }
        // Ex.: "neste domingo (18)" — o mês vem da data de origem.
        if (preg_match_all('~\\b(?:segunda|ter[cç]a|quarta|quinta|sexta|s[aá]bado|domingo)(?:-feira)?\\s*\\(([0-3]?\\d)\\)~iu', $headline, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if (checkdate($baseMonth, (int)$m[1], $baseYear)) $dates[] = sprintf('%04d-%02d-%02d', $baseYear, $baseMonth, (int)$m[1]);
            }
        }
        if (!$dates) return [];
        $today = date('Y-m-d', $now);
        foreach ($dates as $eventDate) {
            if ($eventDate < $today) return ['Data de evento/serviço vencida ('.$eventDate.'); atualizar a abordagem antes de publicar'];
        }
        return [];
    }
}

function tvs_radar_normalize_queue_by_rules(array $items): array
{
    $cities = [
        'Sumaré',
        'Hortolândia',
        'Paulínia',
        'Nova Odessa',
        'Americana',
        'Campinas',
    ];

    $caps = [
        'Cidade'    => 5,
        'Cultura'   => 2,
        'Esportes'  => 2,
        'Empregos'  => 2,
        'Educação'  => 2,
        'Saúde'     => 2,
        'Segurança' => 2,
        'Economia'  => 2,
        'Política'  => 1,
    ];

    $globalCaps = [
        'Brasil'    => 3,
        'São Paulo' => 3,
        'RMC'       => 3,
    ];

    usort($items, static function ($a, $b) {
        $scoreA = (int)($a['editorial_score'] ?? 0);
        $scoreB = (int)($b['editorial_score'] ?? 0);

        if ($scoreA !== $scoreB) {
            return $scoreB <=> $scoreA;
        }

        $dateA = strtotime(
            $a['published_at']
            ?? $a['created_at']
            ?? '1970-01-01'
        ) ?: 0;

        $dateB = strtotime(
            $b['published_at']
            ?? $b['created_at']
            ?? '1970-01-01'
        ) ?: 0;

        return $dateB <=> $dateA;
    });

    $selected = [];
    $counts = [];
    $seenTopics = [];

    foreach ($items as $item) {
        $city = trim((string)($item['city'] ?? ''));
        $category = trim((string)(
            $item['category']
            ?? $item['radar_pre_category']
            ?? 'Cidade'
        ));

        if ($category === '' || !isset($caps[$category])) {
            $category = 'Cidade';
        }

        $item['category'] = $category;

        $category = tvs_radar_editorial_category($item);

        if (!isset($caps[$category])) {
            $category = 'Cidade';
        }

        $item['category'] = $category;

        $category = tvs_radar_editorial_category($item);

        if (!isset($caps[$category])) {
            $category = 'Cidade';
        }

        $title = mb_strtolower(
            trim((string)($item['title'] ?? '')),
            'UTF-8'
        );

        $topicKey = preg_replace(
            '~[^\p{L}\p{N}]+~u',
            ' ',
            $title
        );

        $topicKey = trim(preg_replace('~\s+~u', ' ', $topicKey));

        if ($topicKey !== '' && isset($seenTopics[$topicKey])) {
            continue;
        }

        if (in_array($city, $cities, true)) {
            $key = $city . '|' . $category;
            $current = $counts[$key] ?? 0;

            if ($current >= $caps[$category]) {
                continue;
            }

            $counts[$key] = $current + 1;
        } else {
            // TV Sumaré opera com recorte regional explícito. Conteúdo nacional,
            // estadual ou genérico não entra automaticamente na fila do Radar.
            continue;
        }

        $item['category'] = $category;

        if ($topicKey !== '') {
            $seenTopics[$topicKey] = true;
        }

        $selected[] = $item;
    }

    return array_values($selected);
}

if (!function_exists('tvs_radar_queue_item_readiness')) {
    function tvs_radar_queue_item_readiness(array $item): array
    {
        $reasons = [];

        $title = trim((string)($item['title'] ?? ''));
        $body = trim((string)($item['body'] ?? ''));
        $image = trim((string)($item['image'] ?? ''));
        $url = trim((string)(
            $item['source_url']
            ?? $item['url']
            ?? ''
        ));

        $date = trim((string)(
            $item['published_at']
            ?? $item['created_at']
            ?? ''
        ));

        if ($title === '' || mb_strlen($title, 'UTF-8') < 25) {
            $reasons[] = 'Título ausente ou curto';
        }

        if (preg_match('~\.{3}$~u', $title)) {
            $reasons[] = 'Título truncado';
        }

        if ($url === '') {
            $reasons[] = 'URL ausente';
        } elseif (preg_match('~news\.google\.com~i', $url)) {
            $reasons[] = 'URL original não resolvida';
        } else {
            $articleLike = null;
            if (function_exists('tvs_radar_is_article_path')) {
                $articleLike = tvs_radar_is_article_path(
                    $url,
                    (string)($item['source_original_title'] ?? $title),
                    (string)($item['city'] ?? '')
                );
            }

            if ($articleLike === null) {
                $path = trim(
                    (string)(parse_url($url, PHP_URL_PATH) ?? ''),
                    '/'
                );
                $segments = array_values(array_filter(explode('/', $path)));
                $articleLike = !(
                    $path === ''
                    || count($segments) < 2
                    || preg_match(
                        '~(?:^|/)(category|categoria|tag|tags|author|autor|'
                        .'search|busca|page|pagina|arquivo|archive|editoria|'
                        .'secao|seção)(?:/|$)~iu',
                        $path
                    )
                );
            }

            if (!$articleLike) {
                $reasons[] = 'URL corresponde a página de listagem';
                $sourceCheckReason = (string)($item['source_title_check_reason'] ?? '');
                if ($sourceCheckReason === 'source_date_unconfirmed') {
                    $reasons[] = 'Fonte pendente: data de origem ausente ou divergente';
                } elseif ($sourceCheckReason === 'source_body_mismatch') {
                    $reasons[] = 'Fonte pendente: conteúdo de origem não confirmado';
                } elseif ($sourceCheckReason === 'source_body_insufficient') {
                    $reasons[] = 'Fonte pendente: conteúdo de origem insuficiente';
                }
            }
        }

        // Imagem não define prontidão editorial. Ela é tratada separadamente
        // por image_status/home_eligible para Hero, Home e redes sociais.

        if (!empty($item['url_resolution_required'])) {
            $reasons[] = 'URL exige resolução';
        }

        /*
         * Corpo precisa conter conteúdo jornalístico real, e não apenas a
         * manchete repetida, o nome do veículo ou um resumo de RSS curto.
         */
        if (function_exists('tvs_editorial_body_is_thin')) {
            if (tvs_editorial_body_is_thin($title,$body,$item['source']??'')) {
                $reasons[] = 'Texto jornalístico insuficiente ou duplicado';
            }
        } elseif ($body === '' || mb_strlen($body, 'UTF-8') < 300) {
            $reasons[] = 'Texto jornalístico insuficiente';
        }

        if ($date === '' || strtotime($date) === false) {
            $reasons[] = 'Data não confirmada';
        }

        $reasons = array_merge($reasons, tvs_radar_temporal_review_reasons($item));

        return [
            'ready' => count($reasons) === 0,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}

if (!function_exists('tvs_radar_split_queue_by_readiness')) {
    function tvs_radar_split_queue_by_readiness(array $items): array
    {
        $ready = [];
        $processing = [];

        foreach ($items as $item) {
            $validation = tvs_radar_queue_item_readiness($item);

            if ($validation['ready']) {
                $item['queue_status'] = 'ready';
                $item['queue_validated_at'] = date(DATE_ATOM);
                $item['queue_pending_reasons'] = [];
                $ready[] = $item;
                continue;
            }

            $item['queue_status'] = 'processing';
            $item['queue_pending_reasons'] = $validation['reasons'];
            $item['queue_last_checked_at'] = date(DATE_ATOM);
            $processing[] = $item;
        }

        return [
            'ready' => array_values($ready),
            'processing' => array_values($processing),
        ];
    }
}
