<?php
$target=dirname(__DIR__).'/admin/radar-regional.php';
if(!is_file($target)){
    fwrite(STDERR,"radar-regional.php not found\n");
    exit(1);
}

$source=file_get_contents($target);
if($source===false){
    fwrite(STDERR,"could not read radar-regional.php\n");
    exit(1);
}

$marker="'image_source_type'=>\$imageSourceType,";
if(strpos($source,$marker)!==false){
    echo "Radar image audit hardening already applied.\n";
    exit(0);
}

$start=strpos($source,'function tvs_publish_from_queue($id,$post){');
$end=strpos($source,"\nfunction tvs_selected_ids_from_post(){",$start===false?0:$start);
if($start===false || $end===false || $end<=$start){
    fwrite(STDERR,"publish function boundaries not found\n");
    exit(1);
}

$replacement=<<<'PHP'
function tvs_publish_from_queue($id,$post){
  global $newsFile;

  $queue=tvs_queue_read();
  $found=null;
  $newq=[];

  foreach($queue as $item){
    if(($item['id']??'')===$id) $found=$item;
    else $newq[]=$item;
  }

  if(!$found) return false;

  $title=trim((string)($post['title']??$found['title']??''));
  $body=trim((string)($post['body']??$found['body']??''));
  if($title==='' || $body==='') return false;

  $category=trim((string)($post['category']??$found['category']??'Cidade')) ?: 'Cidade';
  $source=trim((string)($post['source']??$found['source']??'Fonte consultada'));

  $previousImage=trim((string)($found['image']??''));
  $postedImage=array_key_exists('image',$post)
    ? trim((string)$post['image'])
    : $previousImage;

  $imageChanged=(
    array_key_exists('image',$post)
    && $postedImage!==''
    && $postedImage!==$previousImage
  );

  $finalImage=tvs_best_image('', $postedImage, $category);
  $imageSourceType=(string)($found['image_source_type']??'');
  $imageCredit=(string)($found['image_credit']??'');
  $imageReviewRequired=!empty($found['image_review_required']) ? 1 : 0;
  $imageReviewedAt=(string)($found['image_reviewed_at']??'');

  if($imageChanged){
    $imageSourceType='manual_review';
    $imageCredit=tvs_image_credit_from_source($source,$finalImage);
    $imageReviewRequired=0;
    $imageReviewedAt=date('c');
  }

  if($imageReviewRequired) return false;

  if($imageSourceType===''){
    $imageSourceType=preg_match('~^https?://~i',$finalImage)
      ? 'source'
      : 'default_or_legacy';
  }

  if($imageCredit===''){
    $imageCredit=tvs_image_credit_from_source($source,$finalImage);
  }

  $news=tvs_read_json_file($newsFile);
  if(!is_array($news)) $news=[];
  $now=date('c');

  $news[]=[
    'id'=>uniqid('news_'),
    'title'=>$title,
    'subtitle'=>trim((string)($post['subtitle']??$found['subtitle']??'')),
    'summary'=>trim((string)($post['summary']??$found['summary']??'')),
    'body'=>$body,
    'category'=>$category,
    'city'=>trim((string)($post['city']??$found['city']??'Região')),
    'source'=>$source,
    'source_url'=>trim((string)($post['source_url']??$found['source_url']??'')),
    'image'=>$finalImage,
    'image_credit'=>$imageCredit,
    'image_source_type'=>$imageSourceType,
    'image_review_required'=>0,
    'image_reviewed_at'=>$imageReviewedAt,
    'image_review_reason'=>$found['image_review_reason']??'',
    'tags'=>is_array($found['tags']??null)
      ? $found['tags']
      : array_filter(array_map('trim',explode(',',(string)($post['tags']??'')))),
    'seo_title'=>trim((string)($post['seo_title']??$found['seo_title']??$title)),
    'meta_description'=>trim((string)($post['meta_description']??$found['meta_description']??'')),
    'slug'=>trim((string)($post['slug']??$found['slug']??tvs_slug($title))),
    'instagram_caption'=>trim((string)($post['instagram_caption']??$found['instagram_caption']??'')),
    'whatsapp_text'=>trim((string)($post['whatsapp_text']??$found['whatsapp_text']??'')),
    'editorial_score'=>$found['editorial_score']??null,
    'editorial_status'=>$found['editorial_status']??'Publicado',
    'review_level'=>$found['review_level']??'normal',
    'views'=>0,
    'shares'=>0,
    'published_at'=>$now,
    'created_at'=>$now
  ];

  if(!tvs_save_json_file($newsFile,$news)) return false;
  tvs_queue_save($newq);
  return true;
}
PHP;

$newSource=substr($source,0,$start).$replacement.substr($source,$end);
if(file_put_contents($target,$newSource,LOCK_EX)===false){
    fwrite(STDERR,"could not write radar-regional.php\n");
    exit(1);
}

echo "Radar image audit hardening applied.\n";
