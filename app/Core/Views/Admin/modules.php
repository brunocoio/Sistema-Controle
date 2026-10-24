<?php

declare(strict_types=1);
$adminSidebarCollapsed = \App\Core\ModuleSettings::sidebarCollapsed((int) ($user['id'] ?? 0));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Controle de módulos | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
</head>
<body class="admin-page admin-has-sidebar<?= $adminSidebarCollapsed ? ' admin-sidebar-collapsed' : '' ?>">
<?php require __DIR__ . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button></form></header>
<main class="admin-content admin-modules-content">
    <p class="eyebrow">ADMINISTRAÇÃO DO SISTEMA</p><h1>Controle de módulos</h1>
    <p class="admin-modules-lead">Ative os módulos que devem aparecer no site e no painel. O padrão do sistema é obrigatório e não pode ser desativado.</p>
    <?php if (is_array($notice) && is_string($notice['message'] ?? null)): ?><div class="app-toast is-<?= $escape($notice['tone'] ?? 'success') ?>" data-app-toast role="status" aria-live="polite"><?= $escape($notice['message']) ?></div><?php endif; ?>
    <?php if (!$schemaAvailable): ?><div class="app-errors" role="status"><p>O controle ainda não foi preparado no banco.</p><p>Execute <code>php setup/install.php --migrate-system</code> uma vez em cada banco usado (Laragon e Vercel).</p></div><?php endif; ?>
    <section class="admin-module-cards" aria-label="Módulos do sistema">
        <article class="admin-module-card admin-module-locked"><div class="admin-module-heading"><span class="admin-module-icon"><?= \App\Core\View::icon('shield') ?></span><div><h2>Padrão do sistema</h2><p>Componentes, segurança, navegação e serviços compartilhados.</p></div></div><span class="admin-module-status">Obrigatório · sempre ativo</span></article>
        <?php foreach ($moduleCatalog as $moduleKey => $module): $enabled = $moduleStates[$moduleKey]; $installed = $moduleInstalled[$moduleKey]; ?>
            <article class="admin-module-card"><div class="admin-module-heading"><span class="admin-module-icon"><?= \App\Core\View::icon($module['icon']) ?></span><div><h2><?= $escape($module['title']) ?></h2><p><?= $escape($module['description']) ?></p></div></div><div class="admin-module-card-actions"><span class="admin-module-status <?= $installed && $enabled ? 'is-enabled' : 'is-disabled' ?>"><?= !$installed ? 'Arquivos não instalados' : ($enabled ? 'Ativo' : 'Desativado') ?></span><?php if ($installed): ?><form method="post" action="modulos.php"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="module" value="<?= $escape($moduleKey) ?>"><input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>"><button class="app-button <?= $enabled ? 'app-button-danger' : '' ?>" type="submit" <?= !$schemaAvailable ? 'disabled' : '' ?>><?= $enabled ? 'Desativar módulo' : 'Ativar módulo' ?></button></form><?php endif; ?></div></article>
        <?php endforeach; ?>
    </section>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script>
<?php require dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Layout' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body>
</html>
