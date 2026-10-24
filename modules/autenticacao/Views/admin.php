<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Painel | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
</head>
<body class="admin-page admin-has-sidebar<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($user['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header">
    <button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button>
    <a class="auth-brand" href="../"><?= $escape($appName) ?></a>
    <form method="post" action="../logout">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button>
    </form>
</header>
<main class="admin-content">
    <p class="eyebrow">ÁREA ADMINISTRATIVA</p>
    <h1>Bem-vindo, <?= $escape($user['name']) ?></h1>
    <p>Esta área está protegida por autenticação e autorização.</p>
    <div class="admin-dashboard-grid">
        <?php if (\App\Core\Modules::available('atletas')): ?><section class="admin-card">
            <p class="admin-card-kicker">GESTÃO CORPORATIVA</p>
            <h2>Atletas</h2>
            <p>Gerencie atletas, vínculos e as configurações relacionadas ao cadastro esportivo.</p>
            <div class="admin-card-actions"><a class="button" href="atletas">Gerenciar atletas <span aria-hidden="true">→</span></a></div>
        </section><?php endif; ?>
        <?php if (\App\Core\Modules::available('usuarios')): ?><section class="admin-card">
            <p class="admin-card-kicker">CONTAS E VÍNCULOS</p>
            <h2>Usuários</h2>
            <p>Gerencie contas de acesso, status e vínculos opcionais com cadastros de atletas.</p>
            <div class="admin-card-actions"><a class="button" href="usuarios">Gerenciar usuários <span aria-hidden="true">→</span></a></div>
        </section><?php endif; ?>
        <?php if (\App\Core\Modules::available('eventos')): ?><section class="admin-card">
            <p class="admin-card-kicker">AGENDA DA EQUIPE</p>
            <h2>Eventos</h2>
            <p>Organize jogos, treinos, amistosos e compromissos em uma área própria.</p>
            <div class="admin-card-actions"><a class="button" href="eventos">Gerenciar eventos <span aria-hidden="true">→</span></a></div>
        </section><?php endif; ?>
    </div>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script>
</body>
</html>
