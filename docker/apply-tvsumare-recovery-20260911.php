<?php
/**
 * Recuperação TV Sumaré 2026-09-11.
 * Aplica correções idempotentes para regressões confirmadas antes do smoke de homologação.
 */
function recovery_patch_once(string $path,string $old,string $new,string $label): void {
    $code=file_get_contents($path);
    if($code===false){fwrite(STDERR,"{$label}: arquivo não encontrado\n");exit(1);}
    if(strpos($code,$new)!==false){echo "{$label}: já aplicado.\n";return;}
    $count=substr_count($code,$old);
    if($count!==1){fwrite(STDERR,"{$label}: âncora count={$count}; abortando\n");exit(2);}
    $code=str_replace($old,$new,$code);
    if(file_put_contents($path,$code,LOCK_EX)===false){fwrite(STDERR,"{$label}: falha ao gravar\n");exit(3);}
    echo "{$label}: aplicado.\n";
}

$drafts='/var/www/html/admin/drafts.php';
recovery_patch_once(
    $drafts,
    <<<'OLD'
    $previousImage=trim((string)($drafts[$idx]['image']??''));
    $drafts[$idx]['image']=trim((string)($_POST['image']??$previousImage));
    $imageChanged=$drafts[$idx]['image']!==$previousImage;
OLD,
    <<<'NEW'
    $previousImage=trim((string)($drafts[$idx]['image']??''));
    $drafts[$idx]['image']=trim((string)($_POST['image']??$previousImage));
    if($drafts[$idx]['image']===''){
      $drafts[$idx]['image']='assets/tvsumare-noticia-padrao.svg';
      $drafts[$idx]['image_source_type']='default';
      $drafts[$idx]['image_credit']='Imagem ilustrativa: TV Sumaré';
      $drafts[$idx]['image_review_required']=1;
      $drafts[$idx]['image_review_reason']='Imagem ausente; aplicada imagem padrão para revisão obrigatória.';
      unset($drafts[$idx]['image_reviewed_at']);
    }
    $imageChanged=$drafts[$idx]['image']!==$previousImage;
NEW,
    'Drafts imagem ausente'
);

recovery_patch_once(
    $drafts,
    <<<'OLD'
      $drafts[$idx]['image_review_required']=0;
      $drafts[$idx]['image_reviewed_at']=date('c');
OLD,
    <<<'NEW'
      $drafts[$idx]['image_review_required']=0;
      unset($drafts[$idx]['image_review_reason']);
      $drafts[$idx]['image_reviewed_at']=date('c');
NEW,
    'Drafts revisão manual de imagem'
);

recovery_patch_once(
    $drafts,
    <<<'OLD'
    if(tvs_admin_draft_already_published($news,$id)){
      array_splice($drafts,$idx,1);
      tvs_save_json_file($df,$drafts);
      header('Location: noticias.php?published=existing'); exit;
    }
OLD,
    <<<'NEW'
    if(tvs_admin_draft_already_published($news,$id)){
      array_splice($drafts,$idx,1);
      if(!tvs_save_json_file($df,$drafts)){
        header('Location: drafts.php?edit='.urlencode($id).'&erro=publicada-mas-rascunho-nao-removido'); exit;
      }
      header('Location: noticias.php?published=existing'); exit;
    }
NEW,
    'Drafts idempotência publicada'
);

recovery_patch_once(
    $drafts,
    <<<'OLD'
    $news[]=$published;
    if(!tvs_save_json_file($nf,$news)){
      header('Location: drafts.php?edit='.urlencode($id).'&erro=falha-ao-publicar'); exit;
    }
    array_splice($drafts,$idx,1);
    if(!tvs_save_json_file($df,$drafts)){
      header('Location: noticias.php?published=1&warning=rascunho-nao-removido'); exit;
    }
    header('Location: noticias.php?published=1'); exit;
OLD,
    <<<'NEW'
    $newsBefore=$news;
    $news[]=$published;
    if(!tvs_save_json_file($nf,$news)){
      header('Location: drafts.php?edit='.urlencode($id).'&erro=falha-ao-publicar'); exit;
    }
    array_splice($drafts,$idx,1);
    if(!tvs_save_json_file($df,$drafts)){
      if(tvs_save_json_file($nf,$newsBefore)){
        header('Location: drafts.php?edit='.urlencode($id).'&erro=publicacao-revertida-falha-ao-remover-rascunho'); exit;
      }
      header('Location: noticias.php?published=1&warning=falha-critica-rascunho-nao-removido'); exit;
    }
    header('Location: noticias.php?published=1'); exit;
NEW,
    'Drafts publicação compensatória'
);

$helpers='/var/www/html/includes/tvs_public_helpers.php';
recovery_patch_once(
    $helpers,
    <<<'OLD'
function tvs_region_city_detect($n){
  $txt=tvs_lc(($n['city']??'').' '.($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??''));
  $map=['sumaré'=>'Sumaré','sumare'=>'Sumaré','hortolândia'=>'Hortolândia','hortolandia'=>'Hortolândia','paulínia'=>'Paulínia','paulinia'=>'Paulínia','nova odessa'=>'Nova Odessa','americana'=>'Americana','campinas'=>'Campinas'];
  foreach($map as $k=>$v){ if(strpos($txt,$k)!==false) return $v; }
  return '';
}
OLD,
    <<<'NEW'
function tvs_region_city_detect($n){
  // Evidência regional deve existir no conteúdo editorial; o campo city isolado
  // pode ter vindo do loop de coleta e não é suficiente para liberar a Home.
  $txt=tvs_lc(($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??'').' '.($n['body']??'').' '.($n['source']??'').' '.($n['source_url']??''));
  $map=['sumaré'=>'Sumaré','sumare'=>'Sumaré','hortolândia'=>'Hortolândia','hortolandia'=>'Hortolândia','paulínia'=>'Paulínia','paulinia'=>'Paulínia','nova odessa'=>'Nova Odessa','americana'=>'Americana','campinas'=>'Campinas'];
  foreach($map as $k=>$v){ if(strpos($txt,$k)!==false) return $v; }
  return '';
}
NEW,
    'Home evidência regional estrita'
);

foreach([$drafts,$helpers] as $file){
    $out=[];$rc=0;
    exec('php -l '.escapeshellarg($file).' 2>&1',$out,$rc);
    if($rc!==0){fwrite(STDERR,implode("\n",$out)."\n");exit(4);}
}

echo "TVSUMARE_RECOVERY_20260911=APPLIED\n";
