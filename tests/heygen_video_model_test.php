<?php
require_once dirname(__DIR__).'/includes/heygen_video_model.php';
function hv_assert($condition,$message){ if(!$condition) throw new RuntimeException($message); }
$valid=tvs_hvm_plan(['mode'=>'text_to_video','prompt'=>'Vinheta abstrata da TV Sumaré','duration'=>5,'resolution'=>'768p','aspect_ratio'=>'16:9']);
hv_assert($valid['ok'] && $valid['billable'] && $valid['requires_explicit_approval'],'Plano válido');
hv_assert($valid['enabled']===false,'Geração paga deve permanecer desativada');
hv_assert(!tvs_hvm_plan(['prompt'=>'Teste','duration'=>16])['ok'],'Duração inválida bloqueada');
hv_assert(!tvs_hvm_plan(['prompt'=>'Teste','resolution'=>'1080p','aspect_ratio'=>'1:1'])['ok'],'Proporção incompatível bloqueada');
hv_assert(!tvs_hvm_plan(['prompt'=>'Teste','mode'=>'image_to_video'])['ok'],'Imagem obrigatória');
hv_assert(!tvs_hvm_plan(['prompt'=>'Teste','mode'=>'reference_to_video'])['ok'],'Referências obrigatórias');
hv_assert(!tvs_hvm_submit(['prompt'=>'Teste'])['ok'],'Geração paga bloqueada');
$catalogue=tvs_hvm_catalogue();
hv_assert($catalogue['available_for_generation']===false && $catalogue['editorial_review_required']===true,'Catálogo mantém revisão obrigatória e geração bloqueada');
hv_assert($catalogue['presenter']===false && $catalogue['role']==='synthetic_support_scenes','Motor não substitui apresentador');
$estimate=tvs_hvm_estimate(['prompt'=>'Vinheta abstrata','duration'=>5]);
hv_assert($estimate['ok'] && $estimate['cost_estimate']===null && $estimate['can_generate']===false,'Preço desconhecido impede geração');
echo "HEYGEN_VIDEO_MODEL_TEST=PASS\n";
