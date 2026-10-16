<?php
/**
 * Política editorial de voz da Vitrine IA Pro para TV Sumaré.
 * Espelha o catálogo versionado no PR #117 de cschibelsky-star/vitrine-ai-pro.
 *
 * Perfis de repórter e avatar com Voice ID explícito NÃO são substituídos.
 * Este catálogo limita somente a seleção de NOVAS locuções genéricas.
 * Nenhuma seleção implica geração, agendamento ou publicação automática.
 */
if (!function_exists('tvs_voice_policy_catalog')) {
  function tvs_voice_policy_catalog() {
    return [
      'locale' => 'pt-BR',
      'tts_language' => 'pt',
      'preferred_tts_engine' => 'orca',
      'approved_narrators' => [
        'adriano' => ['id'=>'c8ac31e97555494fb8502599e6bc5461', 'gender'=>'male'],
        'yuri' => ['id'=>'14be84ff0ad848d79bd867a79a870f4a', 'gender'=>'male'],
        'aida' => ['id'=>'2799eccbd72d48e0af3202a147d51146', 'gender'=>'female'],
      ],
      'brand_name_written' => 'Vitrine IA Pro',
      'brand_pronunciation_ptbr' => 'Vitrine iá pró',
      'protect_explicit_presenter_voice' => true,
      'auto_publish' => false,
    ];
  }
}
if (!function_exists('tvs_voice_policy_allows_generic_voice')) {
  function tvs_voice_policy_allows_generic_voice($voiceId) {
    $selected = trim((string)$voiceId);
    if ($selected === '') return false;
    foreach (tvs_voice_policy_catalog()['approved_narrators'] as $voice) {
      if ($voice['id'] === $selected) return true;
    }
    return false;
  }
}
if (!function_exists('tvs_voice_policy_prompt')) {
  function tvs_voice_policy_prompt() {
    return 'Política obrigatória de locução: todo texto falado deve ser em português brasileiro nativo (pt-BR), com sotaque brasileiro natural, dicção clara e consistente entre cenas. Não trocar para espanhol ou português de Portugal, nem alternar idioma, voz ou sotaque entre trechos. Respeitar integralmente a voz e o avatar explicitamente configurados para este repórter/apresentador: nunca substituí-los automaticamente por vozes do catálogo comercial. Para nova locução genérica sem apresentador já configurado, somente usar voz explicitamente selecionada do catálogo aprovado (Adriano, Yuri ou Aida); não inventar narradores nem usar vozes não aprovadas. Se mencionar a marca Vitrine IA Pro, o áudio deve soar "Vitrine iá pró", com Ó aberto em "Pró"; em títulos, logotipo, legendas e textos na tela, escrever "Vitrine IA Pro" sem acento. Não adaptar outras marcas ou a expressão "TV Sumaré" para a pronúncia da Vitrine IA Pro.';
  }
}
