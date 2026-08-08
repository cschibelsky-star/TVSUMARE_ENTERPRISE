<?php


if (!function_exists('tvs_radar_editorial_category')) {
    function tvs_radar_editorial_category(array $item): string
    {
        $current = trim((string)($item['category'] ?? 'Cidade'));

        $text = mb_strtolower(
            trim(
                (string)($item['title'] ?? '')
                .' '
                .(string)($item['description'] ?? '')
                .' '
                .mb_substr((string)($item['body'] ?? ''), 0, 700, 'UTF-8')
            ),
            'UTF-8'
        );

        /*
         * A ordem é importante:
         * cursos e matrículas precisam ser classificados antes de "vagas".
         */
        if (preg_match(
            '~campeonato|futebol|v[oô]lei|basquete|corrida|'
            .'torneio|jogos regionais|atleta|minicampo|competi[cç][aã]o~iu',
            $text
        )) {
            return 'Esportes';
        }

        if (preg_match(
            '~festival|show|m[uú]sica|teatro|circo|cultural|'
            .'exposi[cç][aã]o|agenda cultural|gastron[oô]mico|'
            .'apresenta[cç][aã]o musical~iu',
            $text
        )) {
            return 'Cultura';
        }

        if (preg_match(
            '~curso|cursos|capacita[cç][aã]o|qualifica[cç][aã]o|'
            .'escola|creche|aluno|alunos|matr[ií]cula|educa[cç][aã]o|'
            .'ensino|oficina pedag[oó]gica|profissionalizante~iu',
            $text
        )) {
            return 'Educação';
        }

        if (preg_match(
            '~vaga de emprego|vagas de emprego|feir[aã]o de emprego|'
            .'mercado de trabalho|contrata[cç][aã]o|empregador|'
            .'posto de atendimento ao trabalhador|\bpat\b|'
            .'auxiliar de log[ií]stica|processo seletivo para contratar~iu',
            $text
        )) {
            return 'Empregos';
        }

        if (preg_match(
            '~tapa[- ]buraco|pavimenta[cç][aã]o|recape|asfalto|'
            .'obra vi[aá]ria|sinaliza[cç][aã]o|revitaliza[cç][aã]o|'
            .'pra[cç]a|transporte p[uú]blico|cad[uú]nico~iu',
            $text
        )) {
            return 'Cidade';
        }

        return $current !== '' ? $current : 'Cidade';
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
            $scope = trim((string)(
                $item['global_scope']
                ?? $item['city']
                ?? 'Brasil'
            ));

            if (!isset($globalCaps[$scope])) {
                $scope = 'Brasil';
            }

            $key = 'GLOBAL|' . $scope;
            $current = $counts[$key] ?? 0;

            if ($current >= $globalCaps[$scope]) {
                continue;
            }

            $counts[$key] = $current + 1;
            $item['global_scope'] = $scope;
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
            $path = trim(
                (string)(parse_url($url, PHP_URL_PATH) ?? ''),
                '/'
            );

            $segments = array_values(
                array_filter(explode('/', $path))
            );

            if (
                $path === ''
                || count($segments) < 2
                || preg_match(
                    '~(?:^|/)(category|categoria|tag|tags|author|autor|'
                    .'search|busca|page|pagina|arquivo|archive|editoria|'
                    .'secao|seção)(?:/|$)~iu',
                    $path
                )
            ) {
                $reasons[] = 'URL corresponde a página de listagem';
            }
        }

        if (
            $image === ''
            || preg_match(
                '~(^|/)assets/cat-|placeholder|logo-tv-sumare|'
                .'googleusercontent\.com|gstatic\.com~i',
                $image
            )
        ) {
            $reasons[] = 'Imagem jornalística não resolvida';
        }

        if (!empty($item['image_review_required'])) {
            $reasons[] = 'Imagem exige revisão';
        }

        if (!empty($item['url_resolution_required'])) {
            $reasons[] = 'URL exige resolução';
        }

        /*
         * Corpo é obrigatório apenas quando o item já deveria estar pronto
         * para edição/publicação. Evita matéria vazia na fila editorial.
         */
        if ($body === '' || mb_strlen($body, 'UTF-8') < 180) {
            $reasons[] = 'Texto jornalístico insuficiente';
        }

        if ($date === '' || strtotime($date) === false) {
            $reasons[] = 'Data não confirmada';
        }

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
