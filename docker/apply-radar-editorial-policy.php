<?php
$path='/var/www/html/admin/radar-regional.php';
$code=file_get_contents($path);
if($code===false){fwrite(STDERR,"Radar não encontrado\n");exit(1);}

$replacements=[];
$replacements[]=[
<<<'OLD'
function tvs_radar_status_from_score($score,$sensitive=false){
  if($sensitive) return ['review_level'=>'revisao_obrigatoria','editorial_status'=>'Revisão obrigatória'];
  if($score>=85) return ['review_level'=>'normal','editorial_status'=>'Prioridade máxima'];
  if($score>=70) return ['review_level'=>'normal','editorial_status'=>'Destaque'];
  if($score>=50) return ['review_level'=>'normal','editorial_status'=>'Publicável'];
  if($score>=30) return ['review_level'=>'precisa_revisao','editorial_status'=>'Revisão'];
  return ['review_level'=>'descartar','editorial_status'=>'Descartar'];
}
OLD,
<<<'NEW'
function tvs_radar_status_from_score($score,$sensitive=false){
  if($sensitive) return ['review_level'=>'revisao_obrigatoria','editorial_status'=>'Revisão obrigatória'];
  if($score>=85) return ['review_level'=>'normal','editorial_status'=>'Prioridade máxima'];
  if($score>=70) return ['review_level'=>'normal','editorial_status'=>'Destaque'];
  if($score>=50) return ['review_level'=>'normal','editorial_status'=>'Publicável'];
  if($score>=30) return ['review_level'=>'precisa_revisao','editorial_status'=>'Revisão'];
  return ['review_level'=>'precisa_revisao','editorial_status'=>'Enriquecer com IA'];
}
NEW
];

$replacements[]=[
<<<'OLD'
  $wc=tvs_radar_word_count($body);
  $minWords=tvs_radar_is_volume_mode()?14:20;
  if($wc<$minWords){ $reason='Texto muito curto para revisão'; return false; }
  if($wc<80){ $article['review_level']='precisa_revisao'; $article['editorial_status']='Nota curta'; }
  elseif($wc<120){ $article['review_level']='precisa_revisao'; $article['editorial_status']='Revisão'; }
  else { $article['review_level']=$article['review_level']??'normal'; $article['editorial_status']=$article['editorial_status']??'Publicável'; }
OLD,
<<<'NEW'
  $wc=tvs_radar_word_count($body);
  if($wc<8){ $reason='Conteúdo sem fato suficiente para revisão'; return false; }
  if($wc<40){ $article['review_level']='precisa_revisao'; $article['editorial_status']='Notícia rápida'; }
  elseif($wc<80){ $article['review_level']='precisa_revisao'; $article['editorial_status']='Enriquecer com IA'; }
  elseif($wc<120){ $article['review_level']='precisa_revisao'; $article['editorial_status']='Revisão'; }
  else { $article['review_level']=$article['review_level']??'normal'; $article['editorial_status']=$article['editorial_status']??'Publicável'; }
NEW
];

$replacements[]=[
<<<'OLD'
  } else {
    if(tvs_strlen($text)<35 && $score<2){ $reason='Sem fato regional identificável'; return false; }
    if($score<1){ $reason='Sem relação regional clara'; return false; }
  }
OLD,
<<<'NEW'
  } else {
    if(tvs_strlen($text)<15 && $score<1){ $reason='Sem fato regional identificável'; return false; }
    if($score<0){ $reason='Sem relação regional clara'; return false; }
  }
NEW
];

foreach($replacements as [$old,$new]){
  $count=substr_count($code,$old);
  if($count!==1){
    fwrite(STDERR,"Trecho esperado não encontrado de forma única (count={$count}). Patch abortado.\n");
    exit(2);
  }
  $code=str_replace($old,$new,$code);
}

if(file_put_contents($path,$code)===false){
  fwrite(STDERR,"Falha ao gravar Radar\n");
  exit(3);
}

echo "RADAR_EDITORIAL_POLICY_APPLIED=SIM\n";
