<?php
$path='/var/www/html/admin/distribuicao-social.php';
$code=file_get_contents($path);
if($code===false){
    fwrite(STDERR,"Distribuição Social não encontrada\n");
    exit(1);
}

$requiredMarkers=[
    'Social helpers'=>'function ds_queue_active($q)',
    'Social reviewed copy'=>"'revisado':'automatico'",
    'Social idempotency'=>'ds_queue_active($q)',
    'Social ready filtering'=>'Vídeos já colocados na fila não aparecem novamente aqui.',
    'Social heading'=>'<h2>Preparar publicação</h2>',
];

foreach($requiredMarkers as $label=>$marker){
    if(strpos($code,$marker)===false){
        fwrite(STDERR,"{$label}: marcador moderno ausente; abortando\n");
        exit(2);
    }
    echo "{$label}: fluxo moderno já endurecido.\n";
}

if(strpos($code,'network_status')!==false && strpos($code,'foreach($q[\'network_status\'] as $net=>$st)')!==false){
    echo "Social status UI: status por rede já aplicado.\n";
    echo "SOCIAL_DISTRIBUTION_HARDENING_APPLIED=SIM\n";
    exit(0);
}

$old=<<<'PHP'
<strong><?=ds_h(ds_status_label($q['status']??''))?></strong>
PHP;

$new=<<<'PHP'
<strong><?=ds_h(ds_status_label($q['status']??''))?></strong><?php if(!empty($q['network_status'])): ?><br><small><?php foreach($q['network_status'] as $net=>$st): ?><?=ds_h(ucfirst($net))?>: <?=ds_h(ds_status_label($st))?> <?php endforeach; ?></small><?php endif; ?>
PHP;

$count=substr_count($code,$old);
if($count!==1){
    fwrite(STDERR,"Social status UI: trecho esperado count={$count}; abortando\n");
    exit(2);
}

$code=str_replace($old,$new,$code);
if(file_put_contents($path,$code,LOCK_EX)===false){
    fwrite(STDERR,"Falha ao gravar Distribuição Social\n");
    exit(3);
}

echo "Social status UI: status por rede aplicado.\n";
echo "SOCIAL_DISTRIBUTION_HARDENING_APPLIED=SIM\n";
