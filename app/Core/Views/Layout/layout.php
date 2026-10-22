<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $escape($pageDescription ?? 'Plataforma em desenvolvimento para organização de FLAG Football.') ?>">
    <title><?= $escape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('./assets/css/app.css', $config)) ?>">
    <?php if (isset($pageStylesheets) && is_array($pageStylesheets)): foreach ($pageStylesheets as $stylesheet): ?>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl((string) $stylesheet, $config)) ?>">
    <?php endforeach; endif; ?>
</head>
<body<?= $pageBodyClass !== '' ? ' class="' . $escape($pageBodyClass) . '"' : '' ?>>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'header.php'; ?>
    <main id="conteudo">
        <?= $content ?>
    </main>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body>
</html>
