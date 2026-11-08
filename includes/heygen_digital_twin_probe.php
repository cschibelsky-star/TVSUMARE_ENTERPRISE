<?php
/**
 * Read-only HeyGen avatar visibility probe. No API key or raw response is returned.
 * The caller supplies an authenticated GET-only adapter.
 */
if (!function_exists('tvs_heygen_digital_twin_probe')) {
  function tvs_heygen_digital_twin_probe($get, $avatarId) {
    $id=trim((string)$avatarId);
    if (!preg_match('/^[a-f0-9]{32}$/i',$id)) return ['ok'=>false,'reason'=>'invalid_avatar_id'];
    if (!is_callable($get)) return ['ok'=>false,'reason'=>'missing_readonly_adapter'];
    $r=call_user_func($get,'/v3/avatars/looks');
    if (!is_array($r) || empty($r['ok'])) return ['ok'=>false,'reason'=>'avatar_catalog_unavailable','http'=>is_array($r)?(int)($r['http']??0):0];
    $data=$r['data']['data']??[];
    $items=[];
    foreach (['looks','avatars','items','results'] as $k) {
      if (isset($data[$k]) && is_array($data[$k])) $items=array_merge($items,$data[$k]);
    }
    if (isset($data[0])) $items=array_merge($items,$data);
    if (isset($r['data']['next_token']) || !empty($r['data']['has_more']) || isset($data['next_token']) || !empty($data['has_more'])) return ['ok'=>false,'reason'=>'catalog_pagination_incomplete'];
    $found=false;
    foreach ($items as $item) {
      if (!is_array($item)) continue;
      if (strcasecmp((string)($item['avatar_id']??$item['id']??''),$id)===0) { $found=true; break; }
    }
    return ['ok'=>true,'avatar_visible'=>$found,'catalog_items_checked'=>count($items),
      'note'=>$found?'visible_in_catalog':'not_listed_access_not_proven'];
  }
}
