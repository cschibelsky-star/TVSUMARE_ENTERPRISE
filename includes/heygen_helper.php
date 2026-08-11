<?php
// Helper central da HeyGen — TV Sumaré Enterprise 1.0
// Objetivo: impedir erro de "HeyGen sem chave configurada" por divergência entre config.php e JSON.

if (!function_exists('tvs_heygen_value')) {
  function tvs_heygen_value($value) {
    $v = trim((string)$value);
    return $v;
  }
}

if (!function_exists('tvs_heygen_builtin_defaults')) {
  function tvs_heygen_builtin_defaults() {
    return [
      'heygen_api_key' => '',
      'heygen_avatar_id' => 'fb1c964d8284436caab1b63796e7b644',
      'heygen_voice_id' => '21a8abfef8b145da96c701bb4a75670c',
      'heygen_style_id' => '',
      'heygen_brand_kit_id' => '',
      'heygen_orientation' => 'landscape',
      'heygen_incognito_mode' => '0',
      'heygen_reference_file_url' => '',
      'heygen_callback_token' => ''
    ];
  }
}

if (!function_exists('tvs_heygen_root')) {
  function tvs_heygen_root() { return dirname(__DIR__); }
}

if (!function_exists('tvs_heygen_config_paths')) {
  function tvs_heygen_config_paths() {
    $root = tvs_heygen_root();
    return [
      $root.'/data/reporter_ia_config.json',
      $root.'/data/heygen_config.json'
    ];
  }
}

if (!function_exists('tvs_heygen_read_json')) {
  function tvs_heygen_read_json($path) {
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
  }
}

if (!function_exists('tvs_heygen_apply_aliases')) {
  function tvs_heygen_apply_aliases($cfg) {
    if (!is_array($cfg)) $cfg = [];
    $aliases = [
      'heygen_api_key' => ['heygen_key','api_key','key','x_api_key','HEYGEN_API_KEY'],
      'heygen_avatar_id' => ['avatar_id','HEYGEN_AVATAR_ID'],
      'heygen_voice_id' => ['voice_id','HEYGEN_VOICE_ID'],
      'heygen_style_id' => ['style_id','HEYGEN_STYLE_ID'],
      'heygen_brand_kit_id' => ['brand_kit_id','HEYGEN_BRAND_KIT_ID'],
      'heygen_orientation' => ['orientation','HEYGEN_ORIENTATION']
    ];
    foreach ($aliases as $canonical => $names) {
      if (tvs_heygen_value($cfg[$canonical] ?? '') !== '') continue;
      foreach ($names as $name) {
        if (tvs_heygen_value($cfg[$name] ?? '') !== '') {
          $cfg[$canonical] = tvs_heygen_value($cfg[$name]);
          break;
        }
      }
    }
    return $cfg;
  }
}

if (!function_exists('tvs_heygen_env_and_global_defaults')) {
  function tvs_heygen_env_and_global_defaults() {
    $builtin = tvs_heygen_builtin_defaults();
    $out = [];
    foreach ($builtin as $k => $fallback) {
      $envName = strtoupper($k);
      $env = getenv($envName);
      $global = $GLOBALS[$k] ?? '';
      $out[$k] = tvs_heygen_value($env) !== '' ? tvs_heygen_value($env) : (tvs_heygen_value($global) !== '' ? tvs_heygen_value($global) : $fallback);
    }
    return $out;
  }
}

if (!function_exists('tvs_heygen_load_config')) {
  function tvs_heygen_load_config($cfg = []) {
    $cfg = tvs_heygen_apply_aliases(is_array($cfg) ? $cfg : []);
    foreach (tvs_heygen_config_paths() as $path) {
      $disk = tvs_heygen_apply_aliases(tvs_heygen_read_json($path));
      foreach ($disk as $k => $v) {
        if (tvs_heygen_value($cfg[$k] ?? '') === '' && tvs_heygen_value($v) !== '') $cfg[$k] = tvs_heygen_value($v);
      }
    }
    $defaults = tvs_heygen_env_and_global_defaults();
    foreach ($defaults as $k => $v) {
      if (tvs_heygen_value($cfg[$k] ?? '') === '' && tvs_heygen_value($v) !== '') $cfg[$k] = tvs_heygen_value($v);
    }
    if (!in_array(($cfg['heygen_orientation'] ?? 'landscape'), ['landscape','portrait'], true)) $cfg['heygen_orientation'] = 'landscape';
    if (tvs_heygen_value($cfg['heygen_incognito_mode'] ?? '') === '') $cfg['heygen_incognito_mode'] = '0';
    return $cfg;
  }
}

if (!function_exists('tvs_heygen_repair_config')) {
  function tvs_heygen_repair_config($cfg = []) {
    $cfg = tvs_heygen_load_config($cfg);
    $path = tvs_heygen_config_paths()[0];
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($path, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $cfg;
  }
}

if (!function_exists('tvs_heygen_mask')) {
  function tvs_heygen_mask($value) {
    $v = tvs_heygen_value($value);
    if ($v === '') return 'vazio';
    return substr($v, 0, 5).'••••'.substr($v, -4);
  }
}

if (!function_exists('tvs_heygen_diagnostics')) {
  function tvs_heygen_diagnostics($cfg = []) {
    $loaded = tvs_heygen_load_config($cfg);
    $paths = [];
    foreach (tvs_heygen_config_paths() as $p) {
      $paths[] = [
        'path' => $p,
        'exists' => file_exists($p),
        'writable' => file_exists($p) ? is_writable($p) : is_writable(dirname($p)),
        'has_key' => tvs_heygen_value(tvs_heygen_read_json($p)['heygen_api_key'] ?? '') !== ''
      ];
    }
    return [
      'root' => tvs_heygen_root(),
      'key_masked' => tvs_heygen_mask($loaded['heygen_api_key'] ?? ''),
      'avatar_configured' => tvs_heygen_value($loaded['heygen_avatar_id'] ?? '') !== '',
      'voice_configured' => tvs_heygen_value($loaded['heygen_voice_id'] ?? '') !== '',
      'paths' => $paths
    ];
  }
}

// Ponte operacional do TV Play para a API de geração de avatar documentada pela HeyGen.
// Mantida aqui para que video_ai_helper.php não registre o fluxo legado /v3/video-agents.
if (!function_exists('tvp_heygen_config')) {
  function tvp_heygen_config() {
    return tvs_heygen_load_config([]);
  }

  function tvp_heygen_clean_script($text) {
    $text = html_entity_decode(strip_tags((string)$text), ENT_QUOTES|ENT_HTML5, 'UTF-8');
    $text = preg_replace('~https?://\S+|www\.\S+~iu', ' ', $text);
    $text = preg_replace('~\b[\w.-]+\.(?:com\.br|com|br|net|org)(?:/\S*)?~iu', ' ', $text);
    $text = preg_replace('~\bFonte\s*:\s*[^.!?]+[.!?]?~iu', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', trim((string)$text));
    $parts = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
    $seen = [];
    $out = [];
    foreach ($parts as $part) {
      $p = trim($part);
      if ($p === '') continue;
      $key = function_exists('mb_strtolower') ? mb_strtolower($p, 'UTF-8') : strtolower($p);
      $key = preg_replace('/[^\pL\pN]+/u', ' ', $key);
      $key = trim((string)$key);
      if ($key !== '' && isset($seen[$key])) continue;
      if ($key !== '') $seen[$key] = true;
      $out[] = $p;
    }
    return trim(implode(' ', $out));
  }

  function tvp_http($method, $endpoint, $payload = null, $timeout = 45) {
    $cfg = tvp_heygen_config();
    $key = trim((string)($cfg['heygen_api_key'] ?? ''));
    if ($key === '') return ['ok'=>false,'error'=>'HeyGen sem chave configurada.'];
    if (!function_exists('curl_init')) return ['ok'=>false,'error'=>'cURL não está habilitado.'];
    $url = 'https://api.heygen.com'.$endpoint;
    if (!function_exists('tvs_outbound_curl_options')) return ['ok'=>false,'error'=>'Proteção de saída HTTP indisponível.'];
    $outboundOptions = tvs_outbound_curl_options($url, (int)$timeout);
    if ($outboundOptions === null) return ['ok'=>false,'error'=>'URL HeyGen bloqueada pela política de saída.'];
    $headers = ['X-Api-Key: '.$key, 'Accept: application/json'];
    $ch = curl_init($url);
    $opts = $outboundOptions + [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HTTPHEADER=>$headers];
    if ($payload !== null) {
      $headers[] = 'Content-Type: application/json';
      $opts[CURLOPT_HTTPHEADER] = $headers;
      $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $res === '') return ['ok'=>false,'http'=>$http,'error'=>'HeyGen sem resposta. '.$err];
    if (is_string($res) && strlen($res) > 2097152) return ['ok'=>false,'http'=>$http,'error'=>'Resposta HeyGen excedeu o limite seguro.'];
    $json = json_decode((string)$res, true);
    if ($http >= 400) {
      $message = is_array($json) ? ($json['error']['message'] ?? $json['message'] ?? '') : '';
      return ['ok'=>false,'http'=>$http,'error'=>'HeyGen HTTP '.$http.($message !== '' ? ': '.$message : ''),'raw'=>$json ?: null];
    }
    if (!is_array($json)) return ['ok'=>false,'http'=>$http,'error'=>'Resposta HeyGen inválida.'];
    return ['ok'=>true,'http'=>$http,'data'=>$json];
  }

  function tvp_send_heygen($job) {
    $cfg = tvp_heygen_config();
    $avatarId = trim((string)($cfg['heygen_avatar_id'] ?? ''));
    $voiceId = trim((string)($cfg['heygen_voice_id'] ?? ''));
    $script = tvp_heygen_clean_script($job['script'] ?? '');
    if ($avatarId === '') return ['ok'=>false,'error'=>'HeyGen sem Avatar ID configurado.'];
    if ($voiceId === '') return ['ok'=>false,'error'=>'HeyGen sem Voice ID configurado.'];
    if ($script === '') return ['ok'=>false,'error'=>'Roteiro vazio após saneamento.'];
    if (function_exists('mb_substr')) $script = mb_substr($script, 0, 4900, 'UTF-8'); else $script = substr($script, 0, 4900);
    $portrait = (($cfg['heygen_orientation'] ?? 'landscape') === 'portrait');
    $payload = [
      'video_inputs' => [[
        'character' => [
          'type' => 'avatar',
          'avatar_id' => $avatarId,
          'avatar_style' => 'normal'
        ],
        'voice' => [
          'type' => 'text',
          'input_text' => $script,
          'voice_id' => $voiceId,
          'speed' => 1.0
        ]
      ]],
      'dimension' => $portrait ? ['width'=>720,'height'=>1280] : ['width'=>1280,'height'=>720]
    ];
    $r = tvp_http('POST', '/v2/video/generate', $payload, 75);
    if (!$r['ok']) return $r;
    $d = $r['data']['data'] ?? [];
    $videoId = trim((string)($d['video_id'] ?? ''));
    if ($videoId === '') return ['ok'=>false,'http'=>$r['http'] ?? 200,'error'=>'HeyGen aceitou a requisição, mas não retornou video_id.','raw'=>$r['data']];
    return ['ok'=>true,'session_id'=>'','video_id'=>$videoId,'status'=>'gerando','raw'=>$r['data']];
  }

  function tvp_check_heygen($job) {
    $videoId = trim((string)($job['heygen_video_id'] ?? ''));
    if ($videoId === '') return ['ok'=>false,'error'=>'Job sem video_id da HeyGen.'];
    $r = tvp_http('GET', '/v1/video_status.get?video_id='.rawurlencode($videoId), null, 40);
    if (!$r['ok']) return $r;
    $d = $r['data']['data'] ?? [];
    $status = strtolower(trim((string)($d['status'] ?? '')));
    $failure = '';
    if ($status === 'failed') {
      $failure = is_string($d['error'] ?? null) ? $d['error'] : (($d['error']['message'] ?? '') ?: 'HeyGen informou falha na geração.');
    }
    return [
      'ok'=>true,
      'video_id'=>$videoId,
      'video_status'=>$status,
      'progress'=>$d['progress'] ?? null,
      'video_url'=>$status === 'completed' ? ($d['video_url'] ?? '') : '',
      'captioned_video_url'=>$status === 'completed' ? ($d['video_url_caption'] ?? '') : '',
      'thumb'=>$d['thumbnail_url'] ?? '',
      'failure_message'=>$failure
    ];
  }
}
?>