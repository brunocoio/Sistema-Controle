<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Entrar | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('assets/css/app.css', $config)) ?>">
</head>
<body class="auth-page">
<main class="auth-card" id="conteudo">
    <a class="auth-brand" href="index.php"><?= $escape($appName) ?></a>
    <h1>Entrar</h1>
    <p class="auth-intro">Acesse sua área do Sistema FLAG.</p>
    <?php if ($message !== ''): ?>
        <p class="auth-message" role="alert"><?= $escape($message) ?></p>
    <?php endif; ?>
    <form method="post" action="login.php" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" maxlength="254" required>
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" autocomplete="current-password" maxlength="4096" required>
        <button type="submit">Entrar</button>
    </form>
    <a class="back-link" href="index.php">Voltar ao início</a>
</main>
</body>
</html>
