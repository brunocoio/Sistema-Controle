<?php

declare(strict_types=1);
$adminActiveModule = 'eventos';
$isEdit = (int) ($values['id'] ?? 0) > 0;
$value = static fn(string $key, mixed $default = ''): string => (string) ($values[$key] ?? $default);
$dateValue = static function (mixed $raw): string {
    if (!is_string($raw) || $raw === '') return '';
    try { return (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d\\TH:i'); }
    catch (Throwable) { return ''; }
};
$releaseEnabled = in_array($values['release_ativo'] ?? false, [true, 1, '1', 't', 'true'], true);
$imageExists = in_array($values['tem_release_imagem'] ?? false, [true, 1, '1', 't', 'true'], true);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title><?= $isEdit ? 'Editar evento' : 'Novo evento' ?> | <?= $escape($config['app']['name'] ?? 'Sistema FLAG') ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/eventos/Assets/eventos.css', $config)) ?>">
</head>
<body class="admin-page admin-has-sidebar events-page<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="index"><?= $escape($config['app']['name'] ?? 'Sistema FLAG') ?> · Painel</a><a class="app-link" href="eventos">Voltar aos eventos</a></header>
<main class="admin-content events-admin-content">
    <p class="eyebrow">GESTÃO ESPORTIVA</p><h1><?= $isEdit ? 'Editar evento' : 'Novo evento' ?></h1>
    <?php if ($errors !== []): ?><div class="app-toast is-error" data-app-toast role="alert" aria-live="assertive"><?= $escape(implode(' ', $errors)) ?></div><div class="app-errors" role="alert"><p>Confira os dados:</p><ul><?php foreach ($errors as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="event-form app-form" method="post" action="eventos.php" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="save"><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $escape($value('id')) ?>"><?php endif; ?>
        <fieldset><legend>Informações do evento</legend><div class="app-field-grid">
            <label>Tipo<select name="tipo" required><?php foreach (['jogo'=>'Jogo','treino'=>'Treino','amistoso'=>'Amistoso','compromisso'=>'Compromisso'] as $key=>$label): ?><option value="<?= $key ?>" <?= $value('tipo', 'treino') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <label><span class="app-label-title">Categoria <span class="app-tooltip" tabindex="0" title="Modalidade e gênero, por exemplo X5 masculino." aria-label="Modalidade e gênero, por exemplo X5 masculino.">i</span></span><select name="categoria_id"><option value="">Sem categoria definida</option><?php foreach ($categories as $category): $categoryId = (string) $category['id']; ?><option value="<?= (int) $category['id'] ?>" <?= $value('categoria_id') === $categoryId ? 'selected' : '' ?>><?= $escape($category['modalidade']) ?> · <?= $category['genero'] === 'feminino' ? 'Feminino' : 'Masculino' ?></option><?php endforeach; ?></select></label>
            <label>Título *<input name="titulo" maxlength="120" required value="<?= $escape($value('titulo')) ?>" placeholder="Ex.: Treino de preparação"></label>
            <label>Data e hora de início *<input name="inicio" type="datetime-local" step="900" required value="<?= $escape($dateValue($values['inicio'] ?? null)) ?>"></label>
            <label>Data e hora de término<input name="fim" type="datetime-local" step="900" value="<?= $escape($dateValue($values['fim'] ?? null)) ?>"></label>
            <label class="event-opponent-field">Adversário<input name="adversario" maxlength="120" value="<?= $escape($value('adversario')) ?>"></label>
            <label>Local<input name="local" maxlength="180" value="<?= $escape($value('local')) ?>" placeholder="Ginásio, endereço ou link"></label>
            <label>Status<select name="status"><?php foreach (['agendado'=>'Agendado','concluido'=>'Concluído','cancelado'=>'Cancelado'] as $key=>$label): ?><option value="<?= $key ?>" <?= $value('status', 'agendado') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        </div></fieldset>
        <fieldset class="event-result-fields"><legend>Resultado</legend><p class="event-help">Disponível para jogos e amistosos. O placar pode ser preenchido após o evento.</p><div class="app-field-grid">
            <label>Placar da equipe<input name="placar_nos" type="number" min="0" max="999" value="<?= $escape($value('placar_nos')) ?>"></label>
            <label>Placar do adversário<input name="placar_adversario" type="number" min="0" max="999" value="<?= $escape($value('placar_adversario')) ?>"></label>
            <label>Jogador destaque<select name="atleta_destaque_id"><option value="">Não selecionado</option><?php foreach ($athletes as $athlete): ?><option value="<?= (int) $athlete['id'] ?>" <?= $value('atleta_destaque_id') === (string) $athlete['id'] ? 'selected' : '' ?>><?= $escape($athlete['nome']) ?></option><?php endforeach; ?></select></label>
        </div></fieldset>
        <fieldset><legend>Release do evento</legend><label class="event-release-toggle"><input type="checkbox" name="release_ativo" value="1" data-release-toggle <?= $releaseEnabled ? 'checked' : '' ?>> Publicar um release junto ao evento</label>
            <div class="event-release-fields" data-release-fields <?= $releaseEnabled ? '' : 'hidden' ?>><label class="app-field">Texto do release<textarea name="release_texto" maxlength="12000" rows="7" placeholder="Conte os principais momentos do evento..."><?= $escape($value('release_texto')) ?></textarea></label>
                <?php if ($imageExists && $isEdit): ?><div class="event-release-preview"><img src="../eventos.php?image=<?= (int) $values['id'] ?>" alt="Imagem atual do release" width="320" height="180"><label><input type="checkbox" name="remover_imagem" value="1"> Remover imagem atual</label></div><?php endif; ?>
                <label class="app-field">Imagem do release<input type="file" name="release_imagem" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG ou WebP; até 5 MB no envio e 1 MB após otimização.</small></label>
            </div>
        </fieldset>
        <div class="event-actions"><button class="button-secondary" type="submit">Salvar evento</button><a class="app-link" href="eventos.php">Cancelar</a></div>
    </form>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script>
<script src="<?= $escape(\App\Core\View::assetUrl('../modules/eventos/Assets/eventos.js', $config)) ?>" defer></script>
</body>
</html>
