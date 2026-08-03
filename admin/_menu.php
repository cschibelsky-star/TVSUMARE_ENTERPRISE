<?php
$activeAdmin = $activeAdmin ?? '';
function tvs_admin_active($key,$active){ return $key===$active ? 'active' : ''; }
function tvs_admin_link($key,$href,$label,$active){
  return '<a class="'.tvs_admin_active($key,$active).'" href="'.$href.'">'.$label.'</a>';
}
?>
<aside class="side">
  <div class="logo">
    <img src="../assets/logo-tv-sumare.jpeg" alt="TV Sumaré">
    <div><b>TV SUMARÉ</b><br><small>Enterprise 2.0<br>by Vitrine AI Pro</small></div>
  </div>
  <nav class="menu">
    <span class="menu-group">Operação</span>
    <?=tvs_admin_link('dashboard','index.php','Dashboard',$activeAdmin)?>
    <?=tvs_admin_link('status','status.php','Status do Sistema',$activeAdmin)?>
    <?=tvs_admin_link('fontes_status','fontes-status.php','Saúde das Fontes',$activeAdmin)?>

    <span class="menu-group">Redação</span>
    <?=tvs_admin_link('radar','radar-regional.php','Aprovações',$activeAdmin)?>
    <?=tvs_admin_link('noticias','noticias.php','Publicadas',$activeAdmin)?>
    <?=tvs_admin_link('lixeira','lixeira.php','Lixeira',$activeAdmin)?>
    <?=tvs_admin_link('log_editorial','log-editorial.php','Log Editorial',$activeAdmin)?>

    <span class="menu-group">Inteligência Artificial</span>
    <?=tvs_admin_link('editor_ia','editor-ia.php','Editor IA',$activeAdmin)?>
    <?=tvs_admin_link('gemini','gemini.php','Gemini',$activeAdmin)?>
    <?=tvs_admin_link('reporter_ia','reporter-ia.php','Repórter IA',$activeAdmin)?>
    <?=tvs_admin_link('tvplay','tvplay.php','TV Play IA',$activeAdmin)?>

    <span class="menu-group">Conteúdo</span>
    <?=tvs_admin_link('rss','rss-central.php','Central RSS',$activeAdmin)?>
    <?=tvs_admin_link('fontes','fontes.php','Fontes',$activeAdmin)?>
    <?=tvs_admin_link('videos','videos.php','Vídeos',$activeAdmin)?>
    <?=tvs_admin_link('aovivo','aovivo.php','Ao Vivo',$activeAdmin)?>
    <?=tvs_admin_link('colunas','colunas.php','Colunistas',$activeAdmin)?>

    <span class="menu-group">Negócios</span>
    <?=tvs_admin_link('guia','guia-comercial.php','Guia Comercial',$activeAdmin)?>
    <?=tvs_admin_link('comercial','area-comercial.php','Área Comercial',$activeAdmin)?>
    <?=tvs_admin_link('monetizacao','monetizacao.php','Monetização',$activeAdmin)?>

    <span class="menu-group">Atalhos</span>
    <a href="../index.php" target="_blank">Ver site</a>
    <a href="logout.php">Sair</a>
  </nav>
</aside>
