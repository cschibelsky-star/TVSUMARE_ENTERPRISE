<?php
$activeAdmin=$activeAdmin??'';
if(!function_exists('tvs_admin_active')) {
  function tvs_admin_active($key,$active){return $key===$active?'active':'';}
}
if(!function_exists('tvs_admin_link')) {
  function tvs_admin_link($key,$href,$label,$active){
    return '<a class="'.tvs_admin_active($key,$active).'" href="'.$href.'"'.($key===$active?' aria-current="page"':'').'>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</a>';
  }
}
$adminGroups=[
  ['title'=>'Operação','description'=>'Visão geral, fontes e métricas','links'=>[['dashboard','index.php','Dashboard' ],['status','status.php','Status do Sistema' ],['fontes_status','fontes-status.php','Saúde das Fontes' ],['metricas','metricas.php','Métricas de Acesso' ] ]],
  ['title'=>'Redação','description'=>'Aprovações e matérias','links'=>[['radar','radar-regional.php','Aprovações' ],['nova_noticia','nova-noticia.php','Notícia Rápida' ],['drafts','drafts.php','Revisões Pendentes' ],['validade','content-validity.php','Validade Editorial' ],['noticias','noticias.php','Publicadas' ] ]],
  ['title'=>'Central Editorial IA','description'=>'Editor, estúdios e distribuição','links'=>[['editor_ia','editor-ia.php','Editor IA · Notícias' ],['boletim_ia','boletim-ia.php','Estúdio IA · Boletins' ],['reporter_ia','reporter-ia.php','Estúdio IA · Reportagens' ],['revisao_videos','revisao-videos.php','Estúdio IA · Conferência' ],['distribuicao_social','distribuicao-social.php','Distribuição IA · Redes' ],['tvplay','tvplay.php','Distribuição IA · TV Play' ],['apresentadores_ia','apresentadores-ia.php','Apresentadores IA' ] ]],
  ['title'=>'Conteúdo','description'=>'Fontes, RSS e transmissão','links'=>[['rss','rss-central.php','Central RSS' ],['fontes','fontes.php','Fontes' ],['aovivo','aovivo.php','Ao Vivo' ] ]],
  ['title'=>'Comercial','description'=>'Destaques e monetização','links'=>[['guia','guia-comercial.php','Guia Comercial' ],['destaques','destaques.php','Destaque da Home' ],['comercial','area-comercial.php','Área Comercial' ],['monetizacao','monetizacao.php','Monetização' ] ]],
  ['title'=>'Auditoria e acesso','description'=>'Histórico e ferramentas','links'=>[['log_editorial','log-editorial.php','Log Editorial' ],['lixeira','lixeira.php','Lixeira' ] ]],
];
?>
<?php if($activeAdmin!=='dashboard'):
  $currentTitle='Área administrativa';
  $currentSection='TV Sumaré Enterprise';
  foreach($adminGroups as $group){
    foreach($group['links'] as $link){
      if($link[0]===$activeAdmin){
        $currentTitle=$link[2];
        $currentSection=$group['title'];
        break 2;
      }
    }
  }
?>
<header class="admin-module-header" aria-label="Navegação administrativa">
  <a class="admin-module-back" href="index.php" aria-label="Voltar ao painel de cards">← Voltar ao painel</a>
  <div class="admin-module-location">
    <span class="admin-module-section"><?=htmlspecialchars($currentSection,ENT_QUOTES,'UTF-8')?></span>
    <strong><?=htmlspecialchars($currentTitle,ENT_QUOTES,'UTF-8')?></strong>
  </div>
  <a class="admin-module-logo" href="index.php" aria-label="TV Sumaré — Painel">TV SUMARÉ</a>
</header>
<?php else: ?>
<aside class="side admin-card-navigation" data-nav-component data-dashboard="<?=($activeAdmin==='dashboard'?'1':'0')?>">
  <div class="admin-menu-header">
    <a class="logo" href="index.php" aria-label="TV Sumaré — Dashboard">
      <img src="../assets/logo-tv-sumare.jpeg" alt="">
      <span><b>TV SUMARÉ</b><small>Enterprise 2.0 · by Vitrine AI Pro</small></span>
    </a>
    <button type="button" class="admin-menu-toggle" data-nav-toggle aria-controls="tvs-admin-nav" aria-expanded="<?=($activeAdmin==='dashboard'?'true':'false')?>">
      <span class="nav-menu-icon" aria-hidden="true">☰</span><span data-nav-label><?=($activeAdmin==='dashboard'?'Ocultar áreas':'Explorar áreas')?></span>
    </button>
  </div>
  <nav class="menu admin-card-grid" id="tvs-admin-nav" aria-label="Áreas do painel administrativo">
    <?php foreach($adminGroups as $group):
      $isActive=false;
      foreach($group['links'] as $link){if($link[0]===$activeAdmin){$isActive=true;break;}}
    ?>
      <details class="admin-nav-card" <?=($isActive && $activeAdmin!=='dashboard'?'open':'')?>>
        <summary><span class="admin-nav-card-title"><?=htmlspecialchars($group['title'],ENT_QUOTES,'UTF-8')?></span><span class="admin-nav-card-description"><?=htmlspecialchars($group['description'],ENT_QUOTES,'UTF-8')?></span></summary>
        <div class="admin-nav-card-links">
          <?php foreach($group['links'] as $link): ?>
            <?=tvs_admin_link($link[0],$link[1],$link[2],$activeAdmin)?>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
    <div class="admin-nav-card admin-nav-shortcuts"><strong>Atalhos</strong><div class="admin-nav-card-links"><a href="../videos.php" target="_blank" rel="noopener">Ver vídeos</a><a href="../index.php" target="_blank" rel="noopener">Ver portal</a><a href="logout.php">Sair</a></div></div>
  </nav>
</aside>
<?php endif; ?>
<link rel="stylesheet" href="admin-navigation.css?v=20261016-home-only">
<script src="admin-navigation.js?v=20261016-home-only" defer></script>