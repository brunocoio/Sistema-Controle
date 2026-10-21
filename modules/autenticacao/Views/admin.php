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
<body class="admin-page">
<header class="admin-header">
    <a class="auth-brand" href="../index.php"><?= $escape($appName) ?></a>
    <form method="post" action="../logout.php">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <button class="button-secondary" type="submit">Sair</button>
    </form>
</header>
<main class="admin-content">
    <p class="eyebrow">ÁREA ADMINISTRATIVA</p>
    <h1>Bem-vindo, <?= $escape($user['name']) ?></h1>
    <p>Esta área está protegida por autenticação e autorização.</p>
</main>
</body>
</html>
