<?php
$activeAdmin = $activeAdmin ?? '';
function tvs_admin_active($key,$active){ return $key===$active ? 'active' : ''; }
?>
<aside class="side">
  <div class="logo"><img src="../assets/logo-tv-sumare.jpeg" alt="TV Sumaré"><div><b>TV SUMARÉ</b><br><small>Painel Administrativo</small></div></div>
  <nav class="menu">
    <a class="<?=tvs_admin_active('dashboard',$activeAdmin)?>" href="index.php">Dashboard</a>
    <a class="<?=tvs_admin_active('radar',$activeAdmin)?>" href="radar-regional.php">Matérias para Aprovação</a>
    <a class="<?=tvs_admin_active('fontes',$activeAdmin)?>" href="fontes.php">Fontes de Conteúdo</a>
    <a class="<?=tvs_admin_active('fontes_status',$activeAdmin)?>" href="fontes-status.php">Saúde das Fontes</a>
    <a class="<?=tvs_admin_active('log_editorial',$activeAdmin)?>" href="log-editorial.php">Log Editorial</a>
    <a class="<?=tvs_admin_active('noticias',$activeAdmin)?>" href="noticias.php">Notícias Publicadas</a>
    <a class="<?=tvs_admin_active('guia',$activeAdmin)?>" href="guia-comercial.php">Guia Comercial</a>
    <a class="<?=tvs_admin_active('comercial',$activeAdmin)?>" href="area-comercial.php">Área Comercial</a>

    <a class="<?=tvs_admin_active('rss',$activeAdmin)?>" href="rss-central.php">Central RSS</a>
    <a class="<?=tvs_admin_active('colunas',$activeAdmin)?>" href="colunas.php">Colunas Jornalísticas</a>
    <a class="<?=tvs_admin_active('monetizacao',$activeAdmin)?>" href="monetizacao.php">Monetização</a>
    <a class="<?=tvs_admin_active('status',$activeAdmin)?>" href="status.php">Status do Sistema</a>
    <a href="../index.php" target="_blank">Ver site</a>
    <a class="<?=tvs_admin_active('reporter_ia',$activeAdmin)?>" href="reporter-ia.php">Repórter IA</a>
    <a class="<?=tvs_admin_active('tvplay',$activeAdmin)?>" href="tvplay.php">TV Play IA</a>
    <a class="<?=tvs_admin_active('videos',$activeAdmin)?>" href="videos.php">Vídeos</a>
    <a class="<?=tvs_admin_active('aovivo',$activeAdmin)?>" href="aovivo.php">Ao Vivo</a>
    <a href="logout.php">Sair</a>
  </nav>
</aside>
