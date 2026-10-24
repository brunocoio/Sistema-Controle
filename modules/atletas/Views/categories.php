<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
$adminActiveModule = 'atletas';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Categorias e posições | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.css', $config)) ?>">
    <script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script>
    <script src="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.js', $config)) ?>" defer></script>
</head>
<body class="admin-page admin-has-sidebar<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="../admin/index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button></form></header>
<main class="admin-content athletes-page">
    <a class="app-link" href="atletas">← Voltar a atletas</a><p class="eyebrow">CONFIGURAÇÃO ESPORTIVA</p><h1>Categorias, posições e cargos</h1>
    <p class="athletes-lead">Cadastre as categorias, posições e funções da equipe. As posições e os números de camisa são independentes em cada categoria.</p>
    <?php $noticeMessage = is_array($notice) ? ($notice['message'] ?? '') : $notice; $noticeTone = is_array($notice) ? ($notice['tone'] ?? 'success') : 'success'; ?>
    <?php if (is_string($noticeMessage) && $noticeMessage !== ''): ?><div class="app-toast is-<?= $escape($noticeTone) ?>" data-app-toast role="status" aria-live="polite"><?= $escape($noticeMessage) ?></div><?php endif; ?>
    <?php if (is_string($error) && $error !== ''): ?><div class="app-toast is-error" data-app-toast role="alert" aria-live="assertive"><?= $escape($error) ?></div><?php endif; ?>
    <div class="category-config-grid">
        <form id="category-editor" class="category-config-card athlete-form app-form" method="post" action="atletas.php?route=categories" data-config-editor="category">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="create-category"><input type="hidden" name="id" value="">
            <div class="category-form-heading"><span>01</span><div><h2 data-editor-title>Nova categoria</h2><p>Crie uma modalidade para um gênero específico.</p></div></div>
            <label>Nome da modalidade<input name="modalidade" maxlength="40" placeholder="Ex.: X12" required></label>
            <label>Gênero da categoria<select name="genero" required><option value="">Selecione o gênero</option><option value="masculino">Masculino</option><option value="feminino">Feminino</option></select></label>
            <p class="category-form-help">Ataque, defesa e time especial serão criados automaticamente para esta categoria.</p><div class="category-editor-actions"><button class="app-button" type="submit" data-editor-submit>Criar categoria</button><button class="app-button app-button-subtle" type="button" data-editor-cancel hidden>Cancelar edição</button></div>
        </form>
        <form id="position-editor" class="category-config-card athlete-form app-form" method="post" action="atletas.php?route=categories" data-config-editor="position">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="add-position"><input type="hidden" name="id" value="">
            <div class="category-form-heading"><span>02</span><div><h2 data-editor-title>Nova posição</h2><p>Associe cada posição a uma categoria e a um side.</p></div></div>
            <label>Categoria<select name="categoria_id" required><option value="">Selecione uma categoria</option><?php foreach ($categoryOptions as $category): ?><option value="<?= $category['id'] ?>"><?= $escape($category['modalidade']) ?> · <?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></option><?php endforeach; ?></select></label>
            <label>Side<select name="side" required><option value="">Selecione um side</option><option value="ataque">Ataque</option><option value="defesa">Defesa</option><option value="time_especial">Time especial</option></select></label>
            <label>Nome da posição<input name="nome_posicao" maxlength="60" placeholder="Ex.: QB, LB ou kicker" required></label>
            <p class="category-form-help">O nome da posição deve ser único na categoria, mesmo em sides diferentes.</p><div class="category-editor-actions"><button class="app-button" type="submit" data-editor-submit>Adicionar posição</button><button class="app-button app-button-subtle" type="button" data-editor-cancel hidden>Cancelar edição</button></div>
        </form>
        <form id="role-editor" class="category-config-card athlete-form app-form" method="post" action="atletas.php?route=categories" data-config-editor="role">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="add-role"><input type="hidden" name="id" value="">
            <div class="category-form-heading"><span>03</span><div><h2 data-editor-title>Novo cargo</h2><p>Cadastre uma função para vincular a pessoas em cada categoria.</p></div></div>
            <label>Nome do cargo<input name="nome_cargo" maxlength="60" placeholder="Ex.: Preparador físico" required></label>
            <p class="category-form-help">Um atleta pode ter mais de um cargo, e os cargos podem mudar de uma categoria para outra.</p><div class="category-editor-actions"><button class="app-button" type="submit" data-editor-submit>Cadastrar cargo</button><button class="app-button app-button-subtle" type="button" data-editor-cancel hidden>Cancelar edição</button></div>
        </form>
    </div>
    <section class="category-catalog"><h2>Catálogo atual</h2>
    <?php foreach ($categoryOptions as $category): ?>
        <article class="category-catalog-card" id="category-row-<?= $category['id'] ?>" data-category-row="<?= $category['id'] ?>"><div class="category-row-heading"><h3><?= $escape($category['modalidade']) ?> <span><?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></span></h3><div class="app-item-actions"><button type="button" class="button-secondary app-edit-button" data-edit-category data-id="<?= $category['id'] ?>" data-name="<?= $escape($category['modalidade']) ?>" data-gender="<?= $escape($category['genero']) ?>" aria-label="Editar categoria <?= $escape($category['modalidade']) ?>" title="Editar categoria"><?= \App\Core\View::icon('edit') ?><span class="app-visually-hidden">Editar</span></button><form method="post" action="atletas.php?route=categories" data-delete-config data-item-name="a categoria <?= $escape($category['modalidade']) ?>" data-anchor-selector=".category-catalog" data-remove-selector="[data-category-row='<?= $category['id'] ?>']"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete-category"><input type="hidden" name="id" value="<?= $category['id'] ?>"><button type="submit" class="button-secondary app-delete-button" aria-label="Excluir categoria <?= $escape($category['modalidade']) ?>" title="Excluir categoria"><?= \App\Core\View::icon('delete') ?><span class="app-visually-hidden">Excluir</span></button></form></div></div>
            <div class="category-catalog-sides"><?php foreach ($category['sides'] as $side): ?><section><h4><?= $escape($side['nome']) ?></h4><?php if ($side['posicoes'] === []): ?><p>Nenhuma posição cadastrada</p><?php else: ?><ul><?php foreach ($side['posicoes'] as $position): ?><li class="category-position-row" id="position-row-<?= $position['id'] ?>" data-position-row="<?= $position['id'] ?>"><span><?= $escape($position['nome']) ?></span><div class="app-item-actions"><button type="button" class="button-secondary app-edit-button" data-edit-position data-id="<?= $position['id'] ?>" data-name="<?= $escape($position['nome']) ?>" data-category="<?= $category['id'] ?>" data-side="<?= $escape($side['codigo']) ?>" aria-label="Editar posição <?= $escape($position['nome']) ?>" title="Editar posição"><?= \App\Core\View::icon('edit') ?><span class="app-visually-hidden">Editar</span></button><form method="post" action="atletas.php?route=categories" data-delete-config data-item-name="a posição <?= $escape($position['nome']) ?>" data-anchor-selector="[data-category-row='<?= $category['id'] ?>']" data-remove-selector="[data-position-row='<?= $position['id'] ?>']"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete-position"><input type="hidden" name="id" value="<?= $position['id'] ?>"><button type="submit" class="button-secondary app-delete-button" aria-label="Excluir posição <?= $escape($position['nome']) ?>" title="Excluir posição"><?= \App\Core\View::icon('delete') ?><span class="app-visually-hidden">Excluir</span></button></form></div></li><?php endforeach; ?></ul><?php endif; ?></section><?php endforeach; ?></div>
        </article>
    <?php endforeach; ?>
    </section>
    <section class="cargo-catalog"><h2>Cargos cadastrados</h2>
        <article class="cargo-catalog-card"><div class="cargo-chip-list">
            <?php foreach ($roles as $role): ?><div class="cargo-chip" id="role-row-<?= $role['id'] ?>" data-role-row="<?= $role['id'] ?>"><span><?= $escape($role['nome']) ?></span><div class="app-item-actions"><button type="button" class="button-secondary app-edit-button" data-edit-role data-id="<?= $role['id'] ?>" data-name="<?= $escape($role['nome']) ?>" aria-label="Editar cargo <?= $escape($role['nome']) ?>" title="Editar cargo"><?= \App\Core\View::icon('edit') ?><span class="app-visually-hidden">Editar</span></button><form method="post" action="atletas.php?route=categories" data-delete-config data-item-name="o cargo <?= $escape($role['nome']) ?>" data-anchor-selector=".cargo-catalog" data-remove-selector="[data-role-row='<?= $role['id'] ?>']"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete-role"><input type="hidden" name="id" value="<?= $role['id'] ?>"><button type="submit" class="button-secondary app-delete-button" aria-label="Excluir cargo <?= $escape($role['nome']) ?>" title="Excluir cargo"><?= \App\Core\View::icon('delete') ?><span class="app-visually-hidden">Excluir</span></button></form></div></div><?php endforeach; ?>
        </div></article>
    </section>
</main>
<div class="app-toast" data-app-toast role="status" aria-live="polite" hidden></div>
<dialog class="category-delete-dialog" data-delete-dialog aria-labelledby="delete-dialog-title">
    <div class="category-delete-dialog-content">
        <span class="category-delete-dialog-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18.6A1.6 1.6 0 0 0 3.2 21h17.6a1.6 1.6 0 0 0 1.4-2.4L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span>
        <div><h2 id="delete-dialog-title">Confirmar exclusão</h2><p>Deseja excluir <strong data-delete-item-name>este registro</strong>? Se houver atletas vinculados, o sistema bloqueará a exclusão.</p></div>
    </div>
    <div class="category-delete-dialog-actions"><button type="button" class="app-button app-button-subtle" data-delete-cancel>Cancelar</button><button type="button" class="app-button app-button-danger" data-delete-confirm>Excluir</button></div>
</dialog>
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Layout' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body>
</html>
