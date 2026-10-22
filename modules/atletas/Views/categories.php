<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Categorias e posições | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.css', $config)) ?>">
</head>
<body class="admin-page">
<header class="admin-header"><a class="auth-brand" href="../admin/index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="button-secondary" type="submit">Sair</button></form></header>
<main class="admin-content athletes-page">
    <a class="athlete-link" href="atletas">← Voltar a atletas</a><p class="eyebrow">CONFIGURAÇÃO ESPORTIVA</p><h1>Categorias, posições e cargos</h1>
    <p class="athletes-lead">Cadastre as categorias, posições e funções da equipe. As posições e os números de camisa são independentes em cada categoria.</p>
    <?php if (is_string($notice) && $notice !== ''): ?><p class="athlete-notice" role="status"><?= $escape($notice) ?></p><?php endif; ?>
    <?php if (is_string($error) && $error !== ''): ?><p class="athlete-errors" role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <div class="category-config-grid">
        <form class="category-config-card athlete-form" method="post" action="atletas-categorias">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="create-category">
            <div class="category-form-heading"><span>01</span><div><h2>Nova categoria</h2><p>Crie uma modalidade para um gênero específico.</p></div></div>
            <label>Nome da modalidade<input name="modalidade" maxlength="40" placeholder="Ex.: X12" required></label>
            <label>Gênero da categoria<select name="genero" required><option value="">Selecione o gênero</option><option value="masculino">Masculino</option><option value="feminino">Feminino</option></select></label>
            <p class="category-form-help">Ataque, defesa e time especial serão criados automaticamente para esta categoria.</p><button class="athlete-button" type="submit">Criar categoria</button>
        </form>
        <form class="category-config-card athlete-form" method="post" action="atletas-categorias">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="add-position">
            <div class="category-form-heading"><span>02</span><div><h2>Nova posição</h2><p>Associe cada posição a uma categoria e a um side.</p></div></div>
            <label>Categoria<select name="categoria_id" required><option value="">Selecione uma categoria</option><?php foreach ($categoryOptions as $category): ?><option value="<?= $category['id'] ?>"><?= $escape($category['modalidade']) ?> · <?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></option><?php endforeach; ?></select></label>
            <label>Side<select name="side" required><option value="">Selecione um side</option><option value="ataque">Ataque</option><option value="defesa">Defesa</option><option value="time_especial">Time especial</option></select></label>
            <label>Nome da posição<input name="nome_posicao" maxlength="60" placeholder="Ex.: QB, LB ou kicker" required></label>
            <p class="category-form-help">O nome da posição deve ser único na categoria, mesmo em sides diferentes.</p><button class="athlete-button" type="submit">Adicionar posição</button>
        </form>
        <form class="category-config-card athlete-form" method="post" action="atletas-categorias">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="add-role">
            <div class="category-form-heading"><span>03</span><div><h2>Novo cargo</h2><p>Cadastre uma função para vincular a pessoas em cada categoria.</p></div></div>
            <label>Nome do cargo<input name="nome_cargo" maxlength="60" placeholder="Ex.: Preparador físico" required></label>
            <p class="category-form-help">Um atleta pode ter mais de um cargo, e os cargos podem mudar de uma categoria para outra.</p><button class="athlete-button" type="submit">Cadastrar cargo</button>
        </form>
    </div>
    <section class="category-catalog"><h2>Catálogo atual</h2>
    <?php foreach ($categoryOptions as $category): ?>
        <article class="category-catalog-card"><h3><?= $escape($category['modalidade']) ?> <span><?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></span></h3>
            <div class="category-catalog-sides"><?php foreach ($category['sides'] as $side): ?><section><h4><?= $escape($side['nome']) ?></h4><?php if ($side['posicoes'] === []): ?><p>Nenhuma posição cadastrada</p><?php else: ?><ul><?php foreach ($side['posicoes'] as $position): ?><li><?= $escape($position['nome']) ?></li><?php endforeach; ?></ul><?php endif; ?></section><?php endforeach; ?></div>
        </article>
    <?php endforeach; ?>
    </section>
    <section class="cargo-catalog"><h2>Cargos cadastrados</h2>
        <article class="cargo-catalog-card"><div class="cargo-chip-list">
            <?php foreach ($roles as $role): ?><span class="cargo-chip"><?= $escape($role['nome']) ?></span><?php endforeach; ?>
        </div></article>
    </section>
</main>
</body>
</html>
