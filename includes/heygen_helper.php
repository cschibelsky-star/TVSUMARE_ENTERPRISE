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
?>
