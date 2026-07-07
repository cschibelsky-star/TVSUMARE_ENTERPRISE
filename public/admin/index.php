<?php

declare(strict_types=1);

$kpis = [
    'Captadas hoje' => 0,
    'Pendentes' => 0,
    'Publicadas' => 0,
    'Descartadas' => 0,
    'Sem imagem' => 0,
    'Vídeos IA' => 0,
];
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Centro Operacional | TV Sumaré Enterprise</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<aside class="sidebar">
    <div class="sidebar__brand">
        <strong>TV Sumaré</strong>
        <span>Enterprise 3.0</span>
    </div>
    <a class="active" href="/admin/">Dashboard</a>
    <h4>Redação</h4>
    <a href="#">Aprovações</a>
    <a href="#">Publicadas</a>
    <a href="#">Descartadas</a>
    <h4>IA</h4>
    <a href="#">Editor IA</a>
    <a href="#">Repórter IA</a>
    <a href="#">TV Play IA</a>
    <h4>Conteúdo</h4>
    <a href="#">Fontes</a>
    <a href="#">Content Hub</a>
    <h4>Sistema</h4>
    <a href="#">Saúde</a>
    <a href="#">Configurações</a>
</aside>

<main class="workspace">
    <header class="workspace__header">
        <div>
            <span class="eyebrow">Centro Operacional</span>
            <h1>Dashboard Enterprise</h1>
        </div>
        <div class="powered">Powered by <strong>Vitrine IA Pro</strong></div>
    </header>

    <section class="kpi-grid">
        <?php foreach ($kpis as $label => $value): ?>
            <article class="kpi-card">
                <span><?= htmlspecialchars($label) ?></span>
                <strong><?= (int)$value ?></strong>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="panel-grid">
        <article class="panel">
            <h2>Fila Editorial</h2>
            <p>Nenhuma pendência importada ainda. A próxima etapa é conectar o Enterprise News Engine aos dados reais da instalação atual.</p>
        </article>
        <article class="panel">
            <h2>Status IA</h2>
            <ul>
                <li>Gemini: aguardando configuração da instância</li>
                <li>HeyGen: aguardando configuração da instância</li>
                <li>Image Engine: ativo</li>
            </ul>
        </article>
    </section>
</main>
</body>
</html>
