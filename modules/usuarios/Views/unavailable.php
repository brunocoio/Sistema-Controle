<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Preparar usuários | <?= $escape($appName) ?></title><link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>"><link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/usuarios/Assets/usuarios.css', $config)) ?>"></head>
<body class="admin-page admin-has-sidebar<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><a class="auth-brand" href="index"><?= $escape($appName) ?> · Painel</a></header>
<main class="admin-content users-page"><p class="eyebrow">PREPARAÇÃO DO BANCO</p><h1>Usuários</h1><div class="app-errors" role="status"><p>A estrutura de CPF e vínculo entre usuários e atletas ainda não foi aplicada neste banco.</p><p>No SQL Editor do Neon deste ambiente, execute a migration <code>modules/autenticacao/Database/migrations/002_usuarios_cpf_vinculo_atletas.sql</code>. A migration preserva os atletas existentes e eles deverão ser vinculados manualmente.</p></div><a class="app-button app-button-outline" href="index">Voltar ao painel</a></main>
</body></html>
