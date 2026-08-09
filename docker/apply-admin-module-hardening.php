<?php
function patch_once($path,$old,$new,$label){
  $code=file_get_contents($path);
  if($code===false){fwrite(STDERR,"{$label}: arquivo não encontrado\n");exit(1);}
  $count=substr_count($code,$old);
  if($count!==1){fwrite(STDERR,"{$label}: trecho esperado count={$count}; abortando\n");exit(2);}
  $code=str_replace($old,$new,$code);
  if(file_put_contents($path,$code)===false){fwrite(STDERR,"{$label}: falha ao gravar\n");exit(3);}
}
$patches=[
['/var/www/html/admin/editor-ia.php',"if((\$_SERVER['REQUEST_METHOD']??'GET')==='POST'){\n  \$action=\$_POST['action']??'search_generate';","if((\$_SERVER['REQUEST_METHOD']??'GET')==='POST'){\n  tvs_verify_csrf();\n  \$action=\$_POST['action']??'search_generate';",'Editor IA CSRF'],
['/var/www/html/admin/tvplay.php',"if(\$_SERVER['REQUEST_METHOD']==='POST'){\n  \$action=\$_POST['action']??'';","if(\$_SERVER['REQUEST_METHOD']==='POST'){\n  tvs_verify_csrf();\n  \$action=\$_POST['action']??'';",'TV Play IA CSRF'],
['/var/www/html/admin/fontes.php',"if(\$_SERVER['REQUEST_METHOD']==='POST'){ \$action=\$_POST['action']??'';","if(\$_SERVER['REQUEST_METHOD']==='POST'){ tvs_verify_csrf(); \$action=\$_POST['action']??'';",'Fontes CSRF'],
['/var/www/html/admin/aovivo.php',"if((\$_SERVER['REQUEST_METHOD']??'GET')==='POST'){\n  \$settings['live_title']","if((\$_SERVER['REQUEST_METHOD']??'GET')==='POST'){\n  tvs_verify_csrf();\n  \$settings['live_title']",'Ao Vivo CSRF'],
['/var/www/html/admin/guia-comercial.php',"if(\$_SERVER['REQUEST_METHOD']==='POST'){ \$action=\$_POST['action']??'';","if(\$_SERVER['REQUEST_METHOD']==='POST'){ tvs_verify_csrf(); \$action=\$_POST['action']??'';",'Guia Comercial CSRF'],
['/var/www/html/admin/area-comercial.php',"if(\$_SERVER['REQUEST_METHOD']==='POST'){ \$id=\$_POST['id']??'';","if(\$_SERVER['REQUEST_METHOD']==='POST'){ tvs_verify_csrf(); \$id=\$_POST['id']??'';",'Area Comercial CSRF'],
['/var/www/html/admin/monetizacao.php',"if(\$_SERVER['REQUEST_METHOD']==='POST'){\n  foreach(\$defaults","if(\$_SERVER['REQUEST_METHOD']==='POST'){\n  tvs_verify_csrf();\n  foreach(\$defaults",'Monetizacao CSRF'],
['/var/www/html/admin/status.php',"\$geminiTest=null;\n\$heygenTest=null;\nif((\$_SERVER['REQUEST_METHOD']??'GET')==='POST' && (\$_POST['action']??'')==='test_gemini'){","\$geminiTest=null;\n\$heygenTest=null;\nif((\$_SERVER['REQUEST_METHOD']??'GET')==='POST') tvs_verify_csrf();\nif((\$_SERVER['REQUEST_METHOD']??'GET')==='POST' && (\$_POST['action']??'')==='test_gemini'){",'Status CSRF'],
['/var/www/html/admin/rss-central.php',"try{\n  if(\$_SERVER['REQUEST_METHOD']==='POST'){\n    \$action = \$_POST['action'] ?? '';","try{\n  if(\$_SERVER['REQUEST_METHOD']==='POST'){\n    tvs_verify_csrf();\n    \$action = \$_POST['action'] ?? '';",'Central RSS CSRF'],
['/var/www/html/monitor_lib.php',<<<'OLD'
  // Fallback: primeira imagem relevante dentro do HTML.
  if(preg_match_all('~<img\b[^>]*>~is',$html,$imgs)){
    foreach($imgs[0] as $tag){
      if(preg_match('~(?:src|data-src|data-original|data-lazy-src)=["\']([^"\']+)["\']~i',$tag,$m)){
        $img=tvs_absolute_url($base, html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));
        if(tvs_is_valid_image_url($img)) return $img;
      }
    }
  }
OLD,<<<'NEW'
  // Sem OG/Twitter confiável, não associa imagem aleatória do HTML à pauta.
NEW,'Monitor image policy'],
['/var/www/html/monitor_lib.php',<<<'OLD'
  $arr[]=['data'=>date('d/m H:i'),'status'=>'DESCARTADA','cidade'=>$item['city']??'Região','fonte'=>$item['source']??'Fonte','titulo'=>$item['title']??'','motivo'=>$reason,'url'=>$item['url']??''];
OLD,<<<'NEW'
  $arr[]=['data'=>date('d/m H:i'),'created_at'=>date('c'),'status'=>'DESCARTADA','city'=>$item['city']??'Região','cidade'=>$item['city']??'Região','source'=>$item['source']??'Fonte','fonte'=>$item['source']??'Fonte','title'=>$item['title']??'','titulo'=>$item['title']??'','reason'=>$reason,'motivo'=>$reason,'source_url'=>$item['url']??'','url'=>$item['url']??'','description'=>$item['description']??'','summary'=>$item['description']??'','body'=>$item['body']??($item['text']??''),'text'=>$item['text']??($item['body']??($item['description']??'')),'image'=>$item['image']??'','image_source_type'=>$item['image_source_type']??'','image_review_required'=>$item['image_review_required']??0,'published_at'=>$item['published_at']??''];
NEW,'Monitor discard traceability']
];
foreach($patches as $p) patch_once($p[0],$p[1],$p[2],$p[3]);
echo "ADMIN_MODULE_HARDENING_APPLIED=SIM\n";
