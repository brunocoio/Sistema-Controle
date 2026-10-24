<?php

declare(strict_types=1);
$adminActiveModule = 'financeiro-equipe';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Situação da equipe | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/financeiro/Assets/financeiro.css', $config)) ?>">
</head>
<body class="admin-page admin-has-sidebar finance-page<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button></form></header>
<main class="admin-content finance-content">
    <header class="admin-page-heading"><div class="admin-page-heading-copy"><p class="eyebrow">ACESSO DA EQUIPE</p><h1>Situação financeira</h1><p class="admin-page-description">Os atletas autenticados podem consultar quem está em dia ou com pendências. Valores e detalhes só aparecem para a própria pessoa e para o financeiro.</p></div><div class="admin-page-heading-actions"><a class="app-button" href="meu-financeiro">Minha situação</a></div></header>
    <?php if (is_string($financeNotice) && $financeNotice !== ''): ?><div class="app-toast is-success" role="status"><?= $escape($financeNotice) ?></div><?php endif; ?>
    <section class="finance-team-list" aria-label="Situação financeira dos atletas">
        <?php if ($athletes === []): ?><p class="finance-empty">Não há atletas ativos com uma conta vinculada.</p><?php endif; ?>
        <?php foreach ($athletes as $athlete): $isOwn = (int) $athlete['usuario_id'] === (int) ($_SESSION['user']['id'] ?? 0); $irregular = $athlete['situacao'] === 'irregular'; ?>
            <article class="finance-team-row"><div><h2><?= $escape($athlete['nome']) ?><?= !empty($athlete['apelido']) ? ' <span>“' . $escape($athlete['apelido']) . '”</span>' : '' ?></h2><span class="finance-status <?= $irregular ? 'is-overdue' : 'is-current' ?>"><?= $irregular ? 'Com pendência' : 'Em dia' ?></span></div><?php if ($isOwn && $irregular): ?><a class="app-button finance-regularize" href="meu-financeiro">Regularizar</a><?php endif; ?></article>
        <?php endforeach; ?>
    </section>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script>
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Layout' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body></html>
