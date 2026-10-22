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
    <a class="auth-brand" href="./"><?= $escape($appName) ?></a>
    <h1>Entrar</h1>
    <p class="auth-intro">Acesse sua área do Sistema FLAG.</p>
    <?php if ($message !== ''): ?>
        <div class="app-toast is-error" data-app-toast role="alert" aria-live="assertive"><?= $escape($message) ?></div>
    <?php endif; ?>
    <form class="app-form" method="post" action="login" autocomplete="on" data-auth-form>
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">

        <div class="auth-step is-active" data-step="email">
            <label for="email">E-mail</label>
            <div class="auth-input-group">
                <input id="email" name="email" type="email" autocomplete="username" maxlength="254" required placeholder="Digite seu e-mail">
                <button class="auth-step-button" type="button" data-auth-next aria-label="Avançar para a senha" title="Avançar">
                    <?= \App\Core\View::icon('arrow-right') ?>
                </button>
            </div>
            <div class="auth-field-feedback" data-auth-feedback aria-live="polite"></div>
        </div>

        <div class="auth-step" data-step="password" hidden>
            <div class="auth-user-badge">
                <div class="auth-user-avatar" data-user-avatar>
                    <?= \App\Core\View::icon('user') ?>
                </div>
                <div class="auth-user-info">
                    <strong class="auth-user-name" data-user-name></strong>
                    <span class="auth-user-email" data-user-email></span>
                </div>
            </div>

            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" maxlength="4096" required placeholder="Digite sua senha">
            <button class="app-button" type="submit" data-auth-submit>Entrar</button>

            <button type="button" class="auth-back-link" data-auth-back-link aria-label="Voltar para a identificação" title="Voltar">
                <?= \App\Core\View::icon('arrow-left') ?>
            </button>
        </div>
    </form>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('assets/js/app.js', $config)) ?>" defer></script>
</body>
</html>
