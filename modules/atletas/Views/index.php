<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Atletas | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.css', $config)) ?>">
</head>
<body class="admin-page">
<header class="admin-header"><a class="auth-brand" href="../admin/index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="button-secondary" type="submit">Sair</button></form></header>
<main class="admin-content athletes-page">
    <p class="eyebrow">GESTÃO ESPORTIVA</p><h1>Atletas</h1>
    <p class="athletes-lead">Cadastro administrativo da equipe.</p>
    <?php if (is_string($notice) && $notice !== ''): ?><p class="athlete-notice" role="status"><?= $escape($notice) ?></p><?php endif; ?>
    <div class="athlete-toolbar"><form method="get" action="atletas" class="athlete-search" data-athlete-search><label for="q">Buscar atleta</label><div><input id="q" name="q" type="search" value="<?= $escape($search) ?>" maxlength="100" placeholder="Digite o nome ou apelido" autocomplete="off"><select name="status" aria-label="Filtrar status"><option value="todos" <?= !$activeOnly ? 'selected' : '' ?>>Todos</option><option value="ativos" <?= $activeOnly ? 'selected' : '' ?>>Ativos</option></select><label class="athlete-page-size">Por página<select name="per_page" aria-label="Atletas por página"><?php foreach ([10, 25, 50, 100] as $option): ?><option value="<?= $option ?>" <?= $pageSize === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></label><input type="hidden" name="page" value="1"></div><noscript><button class="athlete-button" type="submit">Buscar</button></noscript></form><div class="athlete-toolbar-actions"><a class="athlete-button" href="atletas-novo">Novo atleta</a><a class="athlete-button button-outline" href="atletas-categorias">Categorias, posições e cargos</a></div></div>
    <p class="athlete-privacy">A lista não exibe documentos, contato de emergência ou informações de saúde.</p>
    <?php if ($totalAthletes > 0): ?><p class="athlete-result-count">Exibindo <?= ($page - 1) * $pageSize + 1 ?>–<?= min($page * $pageSize, $totalAthletes) ?> de <?= $totalAthletes ?> <?= $totalAthletes === 1 ? 'atleta' : 'atletas' ?></p><?php endif; ?>
    <div class="athlete-list" aria-live="polite">
    <?php if ($athletes === []): ?>
        <section class="athlete-empty">
            <span class="athlete-empty-icon" aria-hidden="true">🏈</span>
            <h2><?= $search !== '' ? 'Nenhum atleta encontrado' : 'Ainda não há atletas cadastrados' ?></h2>
            <p><?= $search !== '' ? 'Não encontramos resultados para “' . $escape($search) . '”. Confira a escrita ou limpe a busca.' : 'Cadastre o primeiro atleta para começar a montar as equipes e atribuir categorias e posições.' ?></p>
            <div class="athlete-empty-actions">
                <?php if ($search !== ''): ?><a class="athlete-button button-outline" href="atletas">Limpar busca</a><?php endif; ?>
                <a class="athlete-button" href="atletas-novo">Cadastrar atleta</a>
            </div>
        </section>
    <?php endif; ?>
    <?php foreach ($athletes as $row):
        $birth = $row['data_nascimento'] ?? null;
        $age = is_string($birth) ? (new DateTimeImmutable($birth))->diff(new DateTimeImmutable('today'))->y : null;
        $isActive = in_array($row['ativo'] ?? false, [true, 1, '1', 't', 'true'], true);
    ?>
        <article class="athlete-card">
            <?php if (in_array($row['tem_foto'] ?? false, [true, 1, '1', 't', 'true'], true)): ?><img class="img-fluid athlete-photo" src="atletas.php?route=photo&amp;id=<?= (int) $row['id'] ?>&amp;v=<?= $escape($row['foto_version'] ?? '') ?>" alt="Foto de <?= $escape($row['nome']) ?>" width="72" height="72" loading="lazy"><?php else: ?><span class="athlete-photo athlete-photo-empty" aria-hidden="true">🏈</span><?php endif; ?>
            <div class="athlete-main"><h2><?= $escape($row['nome']) ?><?= !empty($row['apelido']) ? ' <span>“' . $escape($row['apelido']) . '”</span>' : '' ?></h2><p><?= $row['genero'] === 'masculino' ? 'Masculino' : ($row['genero'] === 'feminino' ? 'Feminino' : 'Gênero pendente') ?><?= $age !== null ? ' · ' . $age . ' anos' : '' ?></p>
                <?php foreach ($categorySummary[(int) $row['id']] ?? [] as $category): ?><p class="athlete-category-summary"><strong><?= $escape($category['modalidade']) ?> · <?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></strong><?= $category['numero_camisa'] !== null ? ' · Camisa ' . $escape($category['numero_camisa']) : '' ?><?= !empty($category['cargos']) ? ' · Cargos: ' . $escape($category['cargos']) : '' ?></p><?php endforeach; ?>
                <?php foreach ($positionSummary[(int) $row['id']] ?? [] as $assignment): ?><p class="athlete-assignment"><strong>· <?= $escape($assignment['side_nome']) ?>:</strong> <?= $escape($assignment['posicoes'] ?? '') ?></p><?php endforeach; ?>
            </div>
            <span class="athlete-status <?= $isActive ? 'is-active' : 'is-inactive' ?>"><?= $isActive ? 'Ativo' : 'Inativo' ?></span><a class="athlete-link" href="atleta-<?= (int) $row['id'] ?>-editar">Editar</a>
            <form method="post" action="atletas"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="athlete-text-button" type="submit"><?= $isActive ? 'Desativar' : 'Ativar' ?></button></form>
        </article>
    <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?><nav class="athlete-pagination" aria-label="Paginação dos atletas">
        <?php $stateUrl = static function (int $targetPage) use ($search, $activeOnly, $pageSize): string { $state = json_encode(['q' => $search, 'status' => $activeOnly ? 'ativos' : 'todos', 'per_page' => (string) $pageSize, 'page' => (string) $targetPage], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); return 'atletas-filtros-' . rtrim(strtr(base64_encode($state), '+/', '-_'), '='); }; ?>
        <?php if ($page > 1): ?><a class="athlete-button button-outline" href="<?= $escape($stateUrl($page - 1)) ?>" rel="prev">Anterior</a><?php else: ?><span class="athlete-button button-outline is-disabled" aria-disabled="true">Anterior</span><?php endif; ?>
        <span class="athlete-page-indicator">Página <?= $page ?> de <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a class="athlete-button button-outline" href="<?= $escape($stateUrl($page + 1)) ?>" rel="next">Próxima</a><?php else: ?><span class="athlete-button button-outline is-disabled" aria-disabled="true">Próxima</span><?php endif; ?>
    </nav><?php endif; ?>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.js', $config)) ?>" defer></script>
</body>
</html>
