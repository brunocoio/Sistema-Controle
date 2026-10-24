<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
$adminActiveModule = 'atletas';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Atletas | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.css', $config)) ?>">
</head>
<body class="admin-page admin-has-sidebar<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="../admin/index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="admin-logout-button" type="submit"><?= \App\Core\View::icon('logout') ?><span>Sair</span></button></form></header>
<main class="admin-content athletes-page">
    <p class="eyebrow">GESTÃO ESPORTIVA</p><h1>Atletas</h1>
    <p class="athletes-lead">Cadastro administrativo da equipe.</p>
    <?php $noticeMessage = is_array($notice) ? ($notice['message'] ?? '') : $notice; $noticeTone = is_array($notice) ? ($notice['tone'] ?? 'success') : 'success'; ?>
    <?php if (is_string($noticeMessage) && $noticeMessage !== ''): ?><div class="app-toast is-<?= $escape($noticeTone) ?>" data-app-toast role="status" aria-live="polite"><?= $escape($noticeMessage) ?></div><?php endif; ?>
    <div class="athlete-toolbar"><form method="post" action="atletas" class="athlete-search app-search" data-athlete-search><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="filter"><label for="q">Buscar atleta</label><div><input id="q" name="q" type="search" value="<?= $escape($search) ?>" maxlength="100" placeholder="Digite o nome ou apelido" autocomplete="off"><select name="status" aria-label="Filtrar status"><option value="todos" <?= !$activeOnly ? 'selected' : '' ?>>Todos</option><option value="ativos" <?= $activeOnly ? 'selected' : '' ?>>Ativos</option></select><label class="athlete-page-size">Por página<select name="per_page" aria-label="Atletas por página"><?php foreach ([10, 25, 50, 100] as $option): ?><option value="<?= $option ?>" <?= $pageSize === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></label><input type="hidden" name="page" value="1"></div><noscript><button class="app-button" type="submit">Buscar</button></noscript></form><div class="athlete-toolbar-actions"><a class="app-button" href="atletas-novo">Novo atleta</a><a class="app-button app-button-outline" href="atletas-categorias">Categorias, posições e cargos</a></div></div>
    <p class="athlete-privacy">A lista não exibe documentos, contato de emergência ou informações de saúde.</p>
    <?php if ($totalAthletes > 0): ?><p class="athlete-result-count">Exibindo <?= ($page - 1) * $pageSize + 1 ?>–<?= min($page * $pageSize, $totalAthletes) ?> de <?= $totalAthletes ?> <?= $totalAthletes === 1 ? 'atleta' : 'atletas' ?></p><?php endif; ?>
    <div class="athlete-list" aria-live="polite">
    <?php if ($athletes === []): ?>
        <section class="athlete-empty">
            <span class="athlete-empty-icon" aria-hidden="true">🏈</span>
            <h2><?= $search !== '' ? 'Nenhum atleta encontrado' : 'Ainda não há atletas cadastrados' ?></h2>
            <p><?= $search !== '' ? 'Não encontramos resultados para “' . $escape($search) . '”. Confira a escrita ou limpe a busca.' : 'Cadastre o primeiro atleta para começar a montar as equipes e atribuir categorias e posições.' ?></p>
            <div class="athlete-empty-actions">
                <?php if ($search !== ''): ?><a class="app-button app-button-outline" href="atletas">Limpar busca</a><?php endif; ?>
                <a class="app-button" href="atletas-novo">Cadastrar atleta</a>
            </div>
        </section>
    <?php endif; ?>
    <?php foreach ($athletes as $row):
        $birth = $row['data_nascimento'] ?? null;
        $age = is_string($birth) ? (new DateTimeImmutable($birth))->diff(new DateTimeImmutable('today'))->y : null;
        $isActive = in_array($row['ativo'] ?? false, [true, 1, '1', 't', 'true'], true);
        $hasUser = isset($row['usuario_id']) && (int) $row['usuario_id'] > 0;
        $userIsActive = in_array($row['usuario_ativo'] ?? false, [true, 1, '1', 't', 'true'], true);
    ?>
        <article class="athlete-card">
            <?php if (in_array($row['tem_foto'] ?? false, [true, 1, '1', 't', 'true'], true)): ?><img class="img-fluid athlete-photo" src="foto-atleta-<?= $escape($row['photo_token']) ?>" alt="Foto de <?= $escape($row['nome']) ?>" width="72" height="72" loading="lazy"><?php else: ?><span class="athlete-photo athlete-photo-empty" aria-hidden="true">🏈</span><?php endif; ?>
            <div class="athlete-main"><h2><?= $escape($row['nome']) ?><?= !empty($row['apelido']) ? ' <span>“' . $escape($row['apelido']) . '”</span>' : '' ?></h2><p><?= $row['genero'] === 'masculino' ? 'Masculino' : ($row['genero'] === 'feminino' ? 'Feminino' : 'Gênero pendente') ?><?= $age !== null ? ' · ' . $age . ' anos' : '' ?></p>
                <?php foreach ($categorySummary[(int) $row['id']] ?? [] as $category): ?><p class="athlete-category-summary"><strong><?= $escape($category['modalidade']) ?> · <?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></strong><?= $category['numero_camisa'] !== null ? ' · Camisa ' . $escape($category['numero_camisa']) : '' ?><?= !empty($category['cargos']) ? ' · Cargos: ' . $escape($category['cargos']) : '' ?></p><?php endforeach; ?>
                <?php foreach ($positionSummary[(int) $row['id']] ?? [] as $assignment): ?><p class="athlete-assignment"><strong>· <?= $escape($assignment['side_nome']) ?>:</strong> <?= $escape($assignment['posicoes'] ?? '') ?></p><?php endforeach; ?>
            </div>
            <div class="athlete-card-actions">
                <a class="app-icon-action is-edit" href="editar-<?= $escape($row['edit_token']) ?>" title="Editar atleta" aria-label="Editar atleta <?= $escape($row['nome']) ?>"><?= \App\Core\View::icon('edit') ?></a>
                <a class="app-icon-action athlete-user-button <?= !$hasUser ? 'is-unlinked' : ($userIsActive ? 'is-user-active' : 'is-user-inactive') ?>" href="<?= $hasUser ? 'usuario-editar-' . $escape($row['user_edit_token']) : 'usuario-atleta-' . $escape($row['user_link_token']) ?>" title="<?= !$hasUser ? 'Vincular usuário ao atleta' : ($userIsActive ? 'Usuário ativo' : 'Usuário inativo') ?>" aria-label="<?= !$hasUser ? 'Vincular usuário ao atleta ' . $escape($row['nome']) : ($userIsActive ? 'Editar usuário ativo do atleta ' : 'Editar usuário inativo do atleta ') . $escape($row['nome']) ?>"><?= \App\Core\View::icon('user') ?></a>
                <form method="post" action="atletas"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="app-icon-action <?= $isActive ? 'is-deactivate' : 'is-activate' ?>" type="submit" title="<?= $isActive ? 'Desativar atleta' : 'Ativar atleta' ?>" aria-label="<?= $isActive ? 'Desativar atleta ' : 'Ativar atleta ' ?><?= $escape($row['nome']) ?>"><?= \App\Core\View::icon($isActive ? 'deactivate' : 'activate') ?></button></form>
            </div>
            <span class="athlete-status <?= $isActive ? 'is-active' : 'is-inactive' ?>"><?= $isActive ? 'Ativo' : 'Inativo' ?></span>
        </article>
    <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?><nav class="athlete-pagination" aria-label="Paginação dos atletas">
        <?php if ($page > 1): ?><a class="app-button app-button-outline" href="<?= $escape($stateUrl($page - 1)) ?>" rel="prev">Anterior</a><?php else: ?><span class="app-button app-button-outline is-disabled" aria-disabled="true">Anterior</span><?php endif; ?>
        <span class="athlete-page-indicator">Página <?= $page ?> de <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a class="app-button app-button-outline" href="<?= $escape($stateUrl($page + 1)) ?>" rel="next">Próxima</a><?php else: ?><span class="app-button app-button-outline is-disabled" aria-disabled="true">Próxima</span><?php endif; ?>
    </nav><?php endif; ?>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script>
<script src="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.js', $config)) ?>" defer></script>
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Layout' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body>
</html>
