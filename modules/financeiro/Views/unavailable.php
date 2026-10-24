<?php

declare(strict_types=1);
$adminActiveModule = 'financeiro';
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Financeiro | <?= $escape($appName) ?></title><link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>"><link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/financeiro/Assets/financeiro.css', $config)) ?>"></head>
<body class="admin-page admin-has-sidebar finance-page<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><a class="auth-brand" href="index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button></form></header>
<main class="admin-content finance-content"><header class="admin-page-heading"><div class="admin-page-heading-copy"><p class="eyebrow">GESTÃO FINANCEIRA</p><h1>Financeiro</h1><p class="admin-page-description">A estrutura financeira ainda não está disponível neste ambiente.</p></div><div class="admin-page-heading-actions"><a class="app-button app-button-outline" href="index"><?= \App\Core\View::icon('arrow-left') ?> Voltar ao painel</a></div></header><?php if ($isAdmin): ?><div class="app-errors" role="status"><p>Para preparar este banco, execute no SQL Editor do Neon a migration <code>modules/financeiro/Database/migrations/001_financeiro.sql</code>.</p><p>A migration cria somente as tabelas do módulo financeiro e não altera os cadastros existentes de atletas.</p></div><?php else: ?><p class="finance-empty">O módulo será liberado depois da preparação do banco.</p><?php endif; ?></main>
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Layout' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body></html>
