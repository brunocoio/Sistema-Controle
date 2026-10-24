<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
$adminActiveModule = 'usuarios';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Usuários | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/usuarios/Assets/usuarios.css', $config)) ?>">
</head>
<body class="admin-page admin-has-sidebar<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button></form></header>
<main class="admin-content users-page">
    <p class="eyebrow">GESTÃO DE ACESSO</p><h1>Usuários</h1><p class="users-lead">Cadastre pessoas que podem acessar o sistema. O vínculo com atleta é independente do perfil da conta.</p>
    <?php if (is_string($notice) && $notice !== ''): ?><div class="app-toast is-success" role="status"><?= $escape($notice) ?></div><?php endif; ?>
    <div class="users-toolbar"><a class="app-button" href="usuarios-novo">Cadastrar usuário</a><a class="app-button app-button-outline" href="atletas">Ver atletas</a></div>
    <div class="users-list">
    <?php foreach ($users as $row): $isActive = in_array($row['ativo'], [true, 1, '1', 't', 'true'], true); ?>
        <?php $functionLabels = array_map(static fn(string $function): string => $function === 'staff' ? 'Staff' : 'Atleta', is_array($row['funcoes']) ? $row['funcoes'] : []); ?>
        <article class="users-card">
            <div class="users-card-main"><h2><?= $escape($row['nome']) ?></h2><p><?= $escape($row['email']) ?> <span>·</span> CPF <?= $row['cpf'] !== null ? $escape(substr((string) $row['cpf'], 0, 3) . '.***.***-' . substr((string) $row['cpf'], -2)) : 'não informado' ?></p>
                <?php if ($row['atleta_id'] !== null): ?><p class="users-athlete-link">Atleta vinculado: <a href="editar-<?= $escape($row['athlete_edit_token']) ?>"><?= $escape($row['atleta_nome']) ?></a></p><?php else: ?><p class="users-athlete-empty">Sem vínculo com atleta</p><?php endif; ?>
            </div>
            <span class="users-role"><?= $escape($functionLabels === [] ? ucfirst((string) $row['perfil']) : implode(' · ', $functionLabels)) ?></span><span class="users-status <?= $isActive ? 'is-active' : 'is-inactive' ?>"><?= $isActive ? 'Ativo' : 'Inativo' ?></span>
            <a class="app-button app-button-outline" href="usuario-editar-<?= $escape($row['edit_token']) ?>">Editar</a>
            <?php if ((int) $row['id'] !== (int) ($_SESSION['user']['id'] ?? 0)): ?><form method="post" action="usuarios"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="app-button app-button-subtle" type="submit"><?= $isActive ? 'Desativar' : 'Ativar' ?></button></form><?php else: ?><span class="users-current-account">Sua conta</span><?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if ($users === []): ?><section class="app-empty"><h2>Nenhum usuário cadastrado</h2><p>Cadastre um usuário para começar.</p><a class="app-button" href="usuarios-novo">Cadastrar usuário</a></section><?php endif; ?>
    </div>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script>
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Layout' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body></html>
