<?php
declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
$isSelfProfile = (bool) ($profileOnly ?? false);
$adminActiveModule = $isSelfProfile ? 'profile' : 'usuarios';
$isEdit = (int) ($values['id'] ?? 0) > 0;
$isActive = in_array($values['ativo'] ?? true, [true, 1, '1', 't', 'true'], true);
$functions = is_array($values['funcoes'] ?? null) ? $values['funcoes'] : [];
$athlete = is_array($athlete ?? null) ? $athlete : null;
$linkedAthleteId = is_array($user ?? null) && ($user['atleta_id'] ?? null) !== null ? (int) $user['atleta_id'] : 0;
$athleteId = $athlete === null ? $linkedAthleteId : (int) $athlete['id'];
$isIdentityLocked = !$isSelfProfile && $athleteId > 0;
$getValue = static fn(string $key, mixed $default = ''): string => (string) ($values[$key] ?? $default);
$formAction = $isSelfProfile ? 'meu-perfil' : ($isEdit ? 'usuario-' . (int) $values['id'] . '-editar' : ($athleteId > 0 ? 'usuario-atleta-' . $athleteId . '-novo' : 'usuarios-novo'));
?>
<!doctype html>
<html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= $isSelfProfile ? 'Meu perfil' : ($isEdit ? 'Editar usuário' : 'Novo usuário') ?> | <?= $escape($appName) ?></title>
<link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
<link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/usuarios/Assets/usuarios.css', $config)) ?>">
</head><body class="admin-page admin-has-sidebar<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button></form></header>
<main class="admin-content users-page">
<a class="app-link" href="<?= $isSelfProfile ? 'index' : 'usuarios' ?>">← <?= $isSelfProfile ? 'Voltar ao painel' : 'Voltar aos usuários' ?></a><p class="eyebrow">GESTÃO DE ACESSO</p><h1><?= $isSelfProfile ? 'Meu perfil' : ($isEdit ? 'Editar usuário' : 'Cadastrar usuário') ?></h1>
<?php if (is_string($notice ?? null) && $notice !== ''): ?><div class="app-toast is-success" role="status"><?= $escape($notice) ?></div><?php endif; ?>
<?php if (is_string($error ?? null) && $error !== ''): ?><div class="app-toast is-error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<form class="users-form app-form" method="post" action="<?= $escape($formAction) ?>">
<input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="save"><?php if (!$isSelfProfile): ?><input type="hidden" name="id" value="<?= $escape($getValue('id', 0)) ?>"><?php endif; ?>
<fieldset><legend>Dados da conta</legend><div class="app-field-grid">
<label>Nome completo *<input name="nome" maxlength="120" autocomplete="name" required value="<?= $escape($getValue('nome')) ?>"></label>
<label>E-mail *<input name="email" type="email" maxlength="254" autocomplete="email" required value="<?= $escape($getValue('email')) ?>" <?= $isIdentityLocked ? 'readonly' : '' ?>></label>
<label>CPF *<input name="cpf" inputmode="numeric" maxlength="14" autocomplete="off" required value="<?= $escape($getValue('cpf')) ?>" <?= $isIdentityLocked ? 'readonly' : '' ?>><small>CPF e e-mail são únicos.<?= $isIdentityLocked ? ' Para alterar esses dados, edite primeiro o cadastro do atleta.' : '' ?></small></label>
<?php if (!$isSelfProfile): ?><fieldset class="users-functions"><legend>Funções na equipe *</legend><div class="users-function-options">
<label><input type="checkbox" name="funcoes[]" value="staff" <?= in_array('staff', $functions, true) ? 'checked' : '' ?>> Staff</label>
<label><input type="checkbox" name="funcoes[]" value="atleta" <?= in_array('atleta', $functions, true) ? 'checked' : '' ?>> Atleta</label>
</div><small>Marque uma ou as duas funções. As permissões de cada função serão configuradas em uma etapa posterior.</small></fieldset><?php else: ?><div class="users-profile-functions"><strong>Funções:</strong> <?= $escape($functions === [] ? 'Não definidas' : implode(' e ', array_map(static fn(string $function): string => $function === 'staff' ? 'Staff' : 'Atleta', $functions))) ?></div><?php endif; ?>
<?php if ($isSelfProfile): ?><label id="password">Nova senha (opcional)<input name="password" type="password" minlength="12" maxlength="4096" autocomplete="new-password"><small>Deixe em branco para manter a senha atual. Use ao menos 12 caracteres.</small></label>
<?php elseif (!$isEdit): ?><p class="users-initial-password">Senha inicial: os 11 dígitos do CPF informado. No primeiro acesso, a pessoa poderá trocar ou manter essa senha.</p><?php endif; ?>
<?php if (!$isSelfProfile): ?><label>Status<select name="ativo"><option value="1" <?= $isActive ? 'selected' : '' ?>>Ativo</option><option value="0" <?= !$isActive ? 'selected' : '' ?>>Inativo</option></select><small>Usuário inativo não poderá entrar no sistema.</small></label><?php endif; ?>
</div></fieldset>
<fieldset><legend>Vínculo com atleta</legend>
<?php if ($athlete !== null): ?><div class="users-linked-athlete"><strong><?= $escape($athlete['nome']) ?></strong><p>E-mail: <?= $escape($athlete['email'] ?: 'não informado') ?></p><p>CPF: <?= $escape($athlete['cpf'] ?: 'não informado') ?></p><small>Este vínculo é fixo nesta tela. <?php if (empty($athlete['email']) || empty($athlete['cpf'])): ?>Preencha e salve e-mail e CPF no <a href="atleta-<?= (int) $athlete['id'] ?>-editar">cadastro do atleta</a> antes de continuar.<?php else: ?>Para corrigir a associação, use o cadastro do atleta.<?php endif; ?></small></div>
<?php elseif ($linkedAthleteId > 0): ?><div class="users-linked-athlete"><strong><?= $escape($user['atleta_nome']) ?></strong><p>E-mail: <?= $escape($getValue('email')) ?></p><p>CPF: <?= $escape($getValue('cpf')) ?></p><small>O vínculo é mantido para preservar o histórico e não pode ser trocado nesta tela.</small></div>
<?php else: ?><p class="users-linked-athlete">Sem vínculo com atleta. Para vincular esta conta, use o botão “Usuário” no cadastro do atleta.</p><?php endif; ?>
</fieldset>
<div class="users-form-actions"><button class="app-button" type="submit">Salvar <?= $isSelfProfile ? 'perfil' : 'usuário' ?></button><a class="app-button app-button-outline" href="<?= $isSelfProfile ? 'index' : 'usuarios' ?>">Cancelar</a></div>
</form></main><script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script></body></html>
