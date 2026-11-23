<?php
declare(strict_types=1);

function tvs_radar_category_from_text(string $text): string
{
    $text = trim($text);
    if (preg_match('~\b(vereador(?:es)?|prefeito|prefeita|c[aâ]mara\s+municipal|federa[cç][aã]o\s+partid[aá]ria|partido(?:s)?\s+pol[ií]tico(?:s)?|elei[cç][aã]o|pol[ií]tica|r[eé]u|den[uú]ncia|minist[eé]rio\s+p[uú]blico|justi[cç]a|processo\s+judicial|projeto\s+de\s+lei|sess[aã]o\s+da\s+c[aâ]mara|secret[aá]rio\s+municipal)\b~iu', $text)) {
        return 'Política';
    }
    if (preg_match('~\b(sa[uú]de|hospital|ubs|upa|policl[ií]nica|vacina[cç][aã]o|dengue|consulta|exame|exames|m[eé]dico|paciente|implanon|novembro\s+azul|outubro\s+rosa)\b~iu', $text)) {
        return 'Saúde';
    }
    if (preg_match('~\b(educa[cç][aã]o|escola|escolas|creche|aluno(?:s)?|matr[ií]cula|curso|professor(?:es)?|ensino|universidade|vestibular|faculdade|educa[cç][aã]o\s+ambiental)\b~iu', $text)) {
        return 'Educação';
    }
    if (preg_match('~\b(pol[ií]cia|policial|militares?|civis|guarda\s+municipal|gcm|pris[aã]o|preso|presos|roubo|furto|assalto|homic[ií]dio|assassinato|tr[aá]fico|drogas?|opera[cç][aã]o\s+policial|investiga[cç][aã]o|acidente|colis[aã]o|capotamento|morte|morre|morreu|v[ií]tima|temporal|alagamento|alagamentos|enchente|chuva|defesa\s+civil|desabamento)\b~iu', $text)) {
        return 'Segurança';
    }
    if (preg_match('~\b(empreendedorismo|emprego|empregos|vagas?|pat|mercado de trabalho|gera[cç][aã]o de empregos?|oportunidades? de trabalho)\b~iu', $text)) {
        return 'Empregos';
    }
    if (preg_match('~\b(economia|econ[oô]mico|com[eé]rcio|ind[uú]stria|empresas?|investimento|desenvolvimento econ[oô]mico|crescimento econ[oô]mico)\b~iu', $text)) {
        return 'Economia';
    }
    if (preg_match('~\b(esporte|esportes|campeonato|campeonatos|amador|oitavas|quartas|semifinal|final|jogo|jogos|partida|torneio|atleta|corrida|futebol|v[oô]lei|basquete)\b~iu', $text)) {
        return 'Esportes';
    }
    if (preg_match('~\b(evento|eventos|agenda|festival|show|shows|feira|teatro|cinema|exposi[cç][aã]o|m[uú]sica|turismo|lazer|programa[cç][aã]o|rota cervejeira|desfile)\b~iu', $text)) {
        return 'Cultura';
    }
    if (preg_match('~\b(pra[cç]a|revitaliza[cç][aã]o|obra|obras|pavimenta[cç][aã]o|recape|ilumina[cç][aã]o|servi[cç]os? p[uú]blicos?|cad[uú]nico|atendimento itinerante)\b~iu', $text)) {
        return 'Cidade';
    }
    return 'Cidade';
}
