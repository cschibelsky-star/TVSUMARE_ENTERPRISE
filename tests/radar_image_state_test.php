<?php
// Exercise the production metadata block without running cron, AI or persistence.
$source=file_get_contents(dirname(__DIR__).'/admin/radar-regional.php');
$start=strpos($source,'  // Imagem é um atributo paralelo.');
$end=strpos($source,"  \$result['created_at']=",$start===false?0:$start);
if($start===false || $end===false) throw new RuntimeException('Bloco de metadados não encontrado');
$metadata=substr($source,$start,$end-$start);
$photo='https://bucket.portaldacidade.com/sumare.portaldacidade.com/img/news/2026-09/foto.jpg';
foreach([
    // Cards sem foto vistos em Aprovações, Americana e Campinas, em 05/10.
    ['',false,0,true,'missing',0],
    ['',false,0,false,'missing',0],
    [$photo,true,0,true,'verified',1],
    [$photo,true,1,true,'missing',0],
    ['assets/cat-cidade.svg',false,0,true,'missing',0],
] as [$image,$hasVerifiedSourceImage,$review,$editorProcessed,$status,$home]) {
    $result=['image'=>$image,'image_review_required'=>$review];
    eval($metadata);
    if($result['image_status']!==$status || $result['home_eligible']!==$home) {
        throw new RuntimeException('Imagem ausente ou não revisada foi liberada: '.json_encode($result));
    }
    if($result['publication_eligible']!==($editorProcessed?1:0)) {
        throw new RuntimeException('Imagem alterou elegibilidade editorial');
    }
    if($result['image']!==$image || $result['image_review_required']!==$review) {
        throw new RuntimeException('Foto ou requisito de revisão alterado');
    }
}
echo "RADAR_IMAGE_STATE_TEST=PASS\n";
