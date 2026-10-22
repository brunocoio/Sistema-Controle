<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
$value = static fn(string $key, mixed $default = ''): string => (string) ($values[$key] ?? $default);
$isEdit = (int) ($values['id'] ?? 0) > 0;
$isActive = in_array($values['ativo'] ?? true, [true, 1, '1', 't', 'true'], true);
$hasPhoto = in_array($values['tem_foto'] ?? false, [true, 1, '1', 't', 'true'], true);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title><?= $isEdit ? 'Editar atleta' : 'Novo atleta' ?> | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.css', $config)) ?>">
</head>
<body class="admin-page">
<header class="admin-header"><a class="auth-brand" href="../admin/index"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="button-secondary" type="submit">Sair</button></form></header>
<main class="admin-content athletes-page">
    <a class="athlete-link" href="atletas">← Voltar à lista</a><p class="eyebrow">GESTÃO ESPORTIVA</p><h1><?= $isEdit ? 'Editar atleta' : 'Novo atleta' ?></h1>
    <?php if (is_array($errors) && $errors !== []): ?><div class="athlete-errors" role="alert"><p>Confira os dados:</p><ul><?php foreach ($errors as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="athlete-form" method="post" action="atletas" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $escape($value('id', 0)) ?>">
        <fieldset><legend>Identificação</legend><div class="athlete-fields">
            <label>Nome *<input name="nome" maxlength="120" required autocomplete="name" value="<?= $escape($value('nome')) ?>"></label>
            <label>Apelido<input name="apelido" maxlength="80" value="<?= $escape($value('apelido')) ?>"></label>
            <label>Gênero para elegibilidade nas categorias *<select id="athlete-gender" name="genero" required><option value="">Selecione</option><option value="masculino" <?= $value('genero') === 'masculino' ? 'selected' : '' ?>>Masculino</option><option value="feminino" <?= $value('genero') === 'feminino' ? 'selected' : '' ?>>Feminino</option></select><small>Define quais categorias podem ser vinculadas ao atleta.</small></label>
            <label>Data de nascimento<input name="data_nascimento" type="date" max="<?= date('Y-m-d') ?>" value="<?= $escape(substr($value('data_nascimento'), 0, 10)) ?>"><small>A idade é calculada a partir desta data.</small></label>
            <label>Status<select name="ativo"><option value="1" <?= $isActive ? 'selected' : '' ?>>Ativo</option><option value="0" <?= !$isActive ? 'selected' : '' ?>>Inativo</option></select></label>
        </div></fieldset>

        <fieldset><legend>Vínculos esportivos</legend>
            <p class="athlete-privacy">Cada categoria tem seu próprio número de camisa, cargos e posições. A mesma pessoa pode ter funções diferentes no X5 e no X8.</p>
            <p><a class="athlete-link" href="atletas-categorias">Configurar categorias, posições e cargos</a></p>
            <?php if ($categoryOptions === []): ?><p class="athlete-empty">Não há categorias ativas. Configure uma categoria antes de salvar a participação.</p>
            <?php else: foreach ($categoryOptions as $category):
                $categoryId = $category['id'];
                $picked = $selectedCategories[$categoryId] ?? null;
                $participates = array_key_exists($categoryId, $selectedCategories);
                $pickedPositions = is_array($picked) ? ($picked['posicoes'] ?? $picked) : [];
                if (!is_array($pickedPositions)) $pickedPositions = [];
                $pickedRoles = is_array($picked) && is_array($picked['cargos'] ?? null) ? $picked['cargos'] : [];
                $pickedNumber = is_array($picked) ? (string) ($picked['numero_camisa'] ?? '') : '';
                $availableNumbers = $numberOptions[$categoryId] ?? [];
                $pickedNumberAvailable = $pickedNumber !== '' && in_array((int) $pickedNumber, $availableNumbers, true);
                $compatible = $category['genero'] === $value('genero');
            ?>
                <section class="athlete-category-choice" data-category-gender="<?= $escape($category['genero']) ?>">
                    <label class="athlete-category-toggle"><input class="category-link-toggle" type="checkbox" name="categorias[<?= $categoryId ?>][participa]" value="1" <?= $participates ? 'checked' : '' ?>> Vincular a <?= $escape($category['modalidade']) ?> · <?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></label>
                    <?php if (!$compatible): ?><p class="athlete-role-hint">Para este gênero, a pessoa pode receber cargos da equipe. Camisa, posições e o cargo Atleta exigem gênero esportivo compatível.</p><?php endif; ?>
                    <div class="athlete-category-fields">
                        <label class="athlete-jersey-field athlete-player-field">Número da camisa
                            <select name="categorias[<?= $categoryId ?>][numero_camisa]" class="category-assignment-control athlete-player-control" <?= ($compatible && $participates) ? '' : 'disabled' ?>>
                                <option value="">Sem número</option>
                                <?php if ($pickedNumber !== '' && !$pickedNumberAvailable): ?><option value="<?= $escape($pickedNumber) ?>" selected disabled>Nº <?= $escape($pickedNumber) ?> · já ocupado</option><?php endif; ?>
                                <?php foreach ($availableNumbers as $availableNumber): ?><option value="<?= $availableNumber ?>" <?= (string) $availableNumber === $pickedNumber ? 'selected' : '' ?>>Nº <?= $availableNumber ?></option><?php endforeach; ?>
                            </select>
                            <small>Os números já usados nesta categoria não aparecem.</small>
                        </label>
                        <fieldset class="athlete-role-group"><legend>Cargos nesta categoria</legend>
                            <?php if ($roles === []): ?><small>Nenhum cargo cadastrado. <a href="atletas-categorias">Cadastrar cargos</a></small>
                            <?php else: ?><div class="athlete-role-options"><?php foreach ($roles as $role): $playerRole = in_array(mb_strtolower((string) $role['nome'], 'UTF-8'), ['atleta', 'jogador', 'jogadora'], true); ?><label class="athlete-role-option"><input class="category-assignment-control" type="checkbox" name="categorias[<?= $categoryId ?>][cargos][]" value="<?= $role['id'] ?>" <?= in_array((int) $role['id'], array_map('intval', $pickedRoles), true) ? 'checked' : '' ?> <?= (!$participates || ($playerRole && !$compatible)) ? 'disabled' : '' ?> <?= $playerRole ? 'data-player-role="true"' : '' ?>> <?= $escape($role['nome']) ?></label><?php endforeach; ?></div><?php endif; ?>
                        </fieldset>
                    </div>
                    <div class="athlete-side-groups athlete-player-fields">
                    <?php foreach ($category['sides'] as $side): ?>
                        <fieldset class="athlete-side"><legend><?= $escape($side['nome']) ?></legend>
                            <?php if ($side['posicoes'] === []): ?><small>Nenhuma posição configurada. <a href="atletas-categorias">Adicionar posições</a></small>
                            <?php else: foreach ($side['posicoes'] as $position): ?><label class="athlete-position"><input class="category-assignment-control athlete-player-control" type="checkbox" name="categorias[<?= $categoryId ?>][posicoes][]" value="<?= $position['id'] ?>" <?= in_array($position['id'], $pickedPositions, false) ? 'checked' : '' ?> <?= ($compatible && $participates) ? '' : 'disabled' ?>> <?= $escape($position['nome']) ?></label><?php endforeach; endif; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; endif; ?>
        </fieldset>

        <fieldset><legend>Contato</legend><div class="athlete-fields">
            <label>Celular do atleta<input name="celular" type="tel" inputmode="tel" maxlength="30" autocomplete="tel" value="<?= $escape($value('celular')) ?>"></label>
            <label>E-mail<input name="email" type="email" maxlength="254" autocomplete="email" value="<?= $escape($value('email')) ?>"></label>
        </div></fieldset>
        <fieldset><legend>Contato de emergência</legend><div class="athlete-fields">
            <label>Nome<input name="contato_emergencia_nome" maxlength="120" value="<?= $escape($value('contato_emergencia_nome')) ?>"></label>
            <label>Celular<input name="contato_emergencia_celular" type="tel" inputmode="tel" maxlength="30" value="<?= $escape($value('contato_emergencia_celular')) ?>"></label>
            <label>Parentesco<input name="contato_emergencia_parentesco" maxlength="60" value="<?= $escape($value('contato_emergencia_parentesco')) ?>"></label>
        </div></fieldset>
        <fieldset><legend>Documentos e saúde <span class="athlete-private-label">Dados restritos</span></legend><p class="athlete-privacy">Acesso restrito a administradores. Informe somente o necessário para a gestão da equipe.</p><div class="athlete-fields">
            <label>RG<input name="rg" maxlength="30" value="<?= $escape($value('rg')) ?>"></label>
            <label>CPF<input name="cpf" inputmode="numeric" maxlength="14" autocomplete="off" value="<?= $escape($value('cpf')) ?>"><small>O CPF não pode estar associado a outro cadastro.</small></label>
            <label>Tipo sanguíneo<select name="tipo_sanguineo"><option value="">Não informado</option><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $blood): ?><option value="<?= $blood ?>" <?= $value('tipo_sanguineo') === $blood ? 'selected' : '' ?>><?= $blood ?></option><?php endforeach; ?></select></label>
            <label class="athlete-wide">Alergias / observações de saúde<textarea name="alergias" maxlength="2000" rows="4"><?= $escape($value('alergias')) ?></textarea></label>
        </div></fieldset>
        <fieldset><legend>Foto</legend><div class="athlete-fields">
            <?php if ($hasPhoto): ?><div class="athlete-photo-preview"><img src="atleta-<?= (int) $values['id'] ?>-foto" alt="Foto atual do atleta" width="120" height="120"><label><input type="checkbox" name="remover_foto" value="1"> Remover foto atual</label></div><?php endif; ?>
            <label class="athlete-wide">Enviar ou substituir foto<input type="file" name="foto" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG ou WebP. Máximo 5 MB e 6 megapixels. A imagem será redimensionada e convertida para JPEG.</small></label>
        </div></fieldset>
        <div class="athlete-actions"><button class="athlete-button" type="submit">Salvar cadastro</button><a class="athlete-link" href="atletas">Cancelar</a></div>
    </form>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.js', $config)) ?>" defer></script>
</body>
</html>
