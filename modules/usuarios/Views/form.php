<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
$adminActiveModule = 'usuarios';
$isEdit = (int) ($values['id'] ?? 0) > 0;
$linkedAthleteId = is_array($user ?? null) && ($user['atleta_id'] ?? null) !== null ? (int) $user['atleta_id'] : 0;
$selectedAthleteId = (int) ($values['atleta_id'] ?? $linkedAthleteId);
$isActive = in_array($values['ativo'] ?? true, [true, 1, '1', 't', 'true'], true);
$getValue = static fn(string $key, mixed $default = ''): string => (string) ($values[$key] ?? $default);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?> | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/usuarios/Assets/usuarios.css', $config)) ?>">
</head>
<body class="admin-page admin-has-sidebar<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button></form></header>
<main class="admin-content users-page">
    <a class="app-link" href="usuarios">← Voltar aos usuários</a><p class="eyebrow">GESTÃO DE ACESSO</p><h1><?= $isEdit ? 'Editar usuário' : 'Cadastrar usuário' ?></h1>
    <?php if (is_string($error ?? null) && $error !== ''): ?><div class="app-toast is-error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
    <form class="users-form app-form" method="post" action="usuarios">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $escape($getValue('id', 0)) ?>">
        <fieldset><legend>Dados da conta</legend><div class="app-field-grid">
            <label>Nome completo *<input name="nome" maxlength="120" autocomplete="name" required value="<?= $escape($getValue('nome')) ?>"></label>
            <label>E-mail *<input name="email" type="email" maxlength="254" autocomplete="email" required value="<?= $escape($getValue('email')) ?>"></label>
            <label>CPF *<input name="cpf" inputmode="numeric" maxlength="14" autocomplete="off" required value="<?= $escape($getValue('cpf')) ?>"><small>CPF e e-mail são únicos no cadastro.</small></label>
            <label>Perfil de acesso *<select name="perfil" required><?php foreach (['staff' => 'Staff', 'atleta' => 'Atleta', 'admin' => 'Administrador'] as $role => $label): ?><option value="<?= $role ?>" <?= $getValue('perfil', 'staff') === $role ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><small>O perfil de acesso é separado do vínculo esportivo com atleta.</small></label>
            <label><?= $isEdit ? 'Nova senha (opcional)' : 'Senha inicial *' ?><input name="password" type="password" minlength="12" maxlength="4096" autocomplete="<?= $isEdit ? 'new-password' : 'new-password' ?>" <?= $isEdit ? '' : 'required' ?>><small><?= $isEdit ? 'Deixe em branco para manter a senha atual.' : 'Use pelo menos 12 caracteres.' ?></small></label>
            <label>Status<select name="ativo"><option value="1" <?= $isActive ? 'selected' : '' ?>>Ativo</option><option value="0" <?= !$isActive ? 'selected' : '' ?>>Inativo</option></select><small>Usuário inativo não poderá acessar ou participar de novas interações.</small></label>
        </div></fieldset>
        <fieldset><legend>Vínculo com atleta</legend>
            <?php if ($linkedAthleteId > 0): ?><input type="hidden" name="atleta_id" value="<?= $linkedAthleteId ?>"><p class="users-linked-athlete">Este usuário está vinculado ao atleta <strong><?= $escape($user['atleta_nome']) ?></strong>. O vínculo é mantido para preservar o histórico.</p>
            <?php else: ?><label>Atleta (opcional)<select name="atleta_id"><option value="">Este usuário não é atleta</option><?php foreach ($athletes as $athlete): ?><option value="<?= (int) $athlete['id'] ?>" <?= $selectedAthleteId === (int) $athlete['id'] ? 'selected' : '' ?>><?= $escape($athlete['nome']) ?> · <?= $escape($athlete['email'] ?: 'sem e-mail') ?> · CPF <?= $escape($athlete['cpf'] ?: 'não informado') ?></option><?php endforeach; ?></select><small>Para vincular, CPF e e-mail do usuário devem corresponder aos do atleta. Um atleta só pode ter um usuário.</small></label><?php endif; ?>
        </fieldset>
        <div class="users-form-actions"><button class="app-button" type="submit">Salvar usuário</button><a class="app-button app-button-outline" href="usuarios">Cancelar</a></div>
    </form>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script>
</body></html>
