<?php
function tvs_data_file($name){ return __DIR__.'/data/'.$name; }
function tvs_read_admin_json($name){ $p=tvs_data_file($name); if(!file_exists($p)) return []; $d=json_decode(file_get_contents($p),true); return is_array($d)?$d:[]; }
function tvs_save_admin_json($name,$data){ $p=tvs_data_file($name); if(!is_dir(dirname($p))) mkdir(dirname($p),0755,true); file_put_contents($p,json_encode(array_values($data),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
function tvs_safe_text($v){ return trim(strip_tags((string)$v)); }
function tvs_upload_public_file($field,$sub='anuncios'){
  if(empty($_FILES[$field]) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return '';
  $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
  $mime=mime_content_type($_FILES[$field]['tmp_name']);
  if(!isset($allowed[$mime])) return '';
  $dir=__DIR__.'/uploads/'.$sub;
  if(!is_dir($dir)) mkdir($dir,0755,true);
  $name=date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
  if(move_uploaded_file($_FILES[$field]['tmp_name'],$dir.'/'.$name)) return 'uploads/'.$sub.'/'.$name;
  return '';
}
?>
