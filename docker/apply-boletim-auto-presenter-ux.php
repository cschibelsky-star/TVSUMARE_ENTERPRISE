<?php
function bux_patch(&$code,$old,$new,$label){
  $n=substr_count($code,$old);
  if($n!==1){fwrite(STDERR,"{$label}: trecho esperado count={$n}; abortando\n");exit(2);}
  $code=str_replace($old,$new,$code);
}

$reporter='/var/www/html/admin/reporter-ia.php';
$r=file_get_contents($reporter);
if($r===false){fwrite(STDERR,"Reporter IA não encontrado\n");exit(1);}
bux_patch($r,
  '<a class="btn orange" href="boletim-ia.php">Configurar apresentador dos boletins</a>',
  '<a class="btn orange" href="boletim-ia.php">Criar boletim com seleção automática pela IA</a>',
  'Reporter CTA boletim'
);
if(file_put_contents($reporter,$r)===false){fwrite(STDERR,"Falha ao gravar Reporter IA\n");exit(3);}

$boletim='/var/www/html/admin/boletim-ia.php';
$b=file_get_contents($boletim);
if($b===false){fwrite(STDERR,"Boletim IA não encontrado\n");exit(1);}

$old= <<<'PHP'
$cfg=bia_config_read(); $cfg['presenters']=$cfg['presenters']??[];
PHP;
$new= <<<'PHP'
$cfg=bia_config_read(); $cfg['presenters']=$cfg['presenters']??[]; $reporterCfg=bia_read('reporter_ia_config.json'); $defaultVoice=trim((string)($reporterCfg['heygen_voice_id']??''));
PHP;
bux_patch($b,$old,$new,'Boletim voice fallback load');

$old= <<<'PHP'
$cfg['presenters']['giro']=array_merge(['name'=>'TV Sumaré Giro','tone'=>'dinâmico, humano, energético, natural e conversacional','avatar_id'=>'ecc586fbd3e94cd4a07347c098364fc8','voice_id'=>'','style_id'=>''],$cfg['presenters']['giro']??[]);
PHP;
$new= <<<'PHP'
$cfg['presenters']['giro']=array_merge(['name'=>'TV Sumaré Giro','tone'=>'dinâmico, humano, energético, natural e conversacional','avatar_id'=>'ecc586fbd3e94cd4a07347c098364fc8','voice_id'=>'','style_id'=>''],$cfg['presenters']['giro']??[]); foreach(['noticias','cidade','giro'] as $pk){ if(trim((string)($cfg['presenters'][$pk]['voice_id']??''))==='' && $defaultVoice!=='') $cfg['presenters'][$pk]['voice_id']=$defaultVoice; }
PHP;
bux_patch($b,$old,$new,'Boletim voice fallback apply');

$oldStart='<div class="box" style="margin-top:14px"><h2>Apresentadores disponíveis para a OpenAI</h2><form method="post" class="form"><?=tvs_csrf_field()?>';
$start=strpos($b,$oldStart);
if($start===false){fwrite(STDERR,"Boletim config UI início não encontrado\n");exit(4);}
$oldEnd="</form></div>\n<div class=\"box\" style=\"margin-top:14px\"><h2>Novo boletim vertical</h2>";
$end=strpos($b,$oldEnd,$start);
if($end===false){fwrite(STDERR,"Boletim config UI fim não encontrado\n");exit(5);}
$advanced=<<<'HTML'
<div class="box" style="margin-top:14px"><h2>Seleção automática do apresentador</h2><p><strong>A IA escolhe automaticamente</strong> o perfil mais adequado para cada pauta. O operador não precisa selecionar apresentador durante a produção normal.</p><div class="grid2"><div><strong>Notícias & Serviço</strong><br><small>Utilidade pública, saúde, segurança, emprego, prefeitura e educação.</small></div><div><strong>Cidade, Cultura & Agenda</strong><br><small>Eventos, cultura, turismo, gastronomia, lazer e histórias locais.</small></div><div><strong>Giro Rápido & Destaques</strong><br><small>Economia, destaques gerais, curiosidades e pautas de maior apelo.</small></div></div><details style="margin-top:14px"><summary><strong>Configurações avançadas dos apresentadores</strong></summary><p class="muted">Área técnica para Avatar ID, Voice ID e Style ID. Use apenas quando houver mudança de avatar/voz na HeyGen.</p><form method="post" class="form" style="margin-top:12px"><?=tvs_csrf_field()?><?php foreach(bia_presenter_labels() as $key=>$label): $p=$cfg['presenters'][$key]; ?><h3><?=bia_h($label)?></h3><div class="grid2"><div><label>Nome/persona</label><input name="<?=$key?>_name" value="<?=bia_h($p['name'])?>"></div><div><label>Tom</label><input name="<?=$key?>_tone" value="<?=bia_h($p['tone'])?>"></div><div><label>HeyGen Avatar ID</label><input name="<?=$key?>_avatar_id" value="<?=bia_h($p['avatar_id'])?>"></div><div><label>HeyGen Voice ID</label><input name="<?=$key?>_voice_id" value="<?=bia_h($p['voice_id'])?>" placeholder="usa a voz principal quando vazio"></div><div><label>HeyGen Style ID</label><input name="<?=$key?>_style_id" value="<?=bia_h($p['style_id'])?>"></div></div><?php endforeach; ?><button class="btn secondary" name="save_presenters" value="1">Salvar configurações avançadas</button></form></details></div>
<div class="box" style="margin-top:14px"><h2>Novo boletim vertical</h2>
HTML;
$b=substr($b,0,$start).$advanced.substr($b,$end+strlen($oldEnd));

bux_patch($b,
  '<button class="btn orange" name="create_boletim" value="1">OpenAI: criar roteiro e escolher apresentador</button>',
  '<button class="btn orange" name="create_boletim" value="1">Criar boletim — IA escolhe roteiro e apresentador</button>',
  'Boletim create CTA'
);

if(file_put_contents($boletim,$b)===false){fwrite(STDERR,"Falha ao gravar Boletim IA\n");exit(6);}
echo "BOLETIM_AUTO_PRESENTER_UX_APPLIED=SIM\n";
