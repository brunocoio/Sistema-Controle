<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Registro indisponível | <?= $escape($appName) ?></title>
<link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
</head><body class="admin-page admin-has-sidebar<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button></form></header>
<main class="admin-content users-page"><header class="admin-page-heading"><div class="admin-page-heading-copy"><p class="eyebrow">GESTÃO DE ACESSO</p><h1>Não foi possível abrir este cadastro</h1><p class="admin-page-description">Confira a mensagem e escolha para onde deseja voltar.</p></div><div class="admin-page-heading-actions"><a class="app-button" href="atletas">Voltar aos atletas</a><a class="app-button app-button-outline" href="usuarios">Voltar aos usuários</a></div></header><p class="app-toast is-error" role="alert"><?= $escape($message) ?></p></main>
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Layout' . DIRECTORY_SEPARATOR . 'footer.php'; ?></body></html>
