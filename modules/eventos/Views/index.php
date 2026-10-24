<?php

declare(strict_types=1);
$adminActiveModule = 'eventos';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Eventos | <?= $escape($config['app']['name'] ?? 'Sistema FLAG') ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/eventos/Assets/eventos.css', $config)) ?>">
</head>
<body class="admin-page admin-has-sidebar events-page<?= \App\Core\ModuleSettings::sidebarCollapsed((int) ($_SESSION['user']['id'] ?? 0)) ? ' admin-sidebar-collapsed' : '' ?>">
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'sidebar.php'; ?>
<header class="admin-header"><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-mobile" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="true" aria-label="Abrir menu"><?= \App\Core\View::icon('menu') ?><span>Menu</span></button><a class="auth-brand" href="index"><?= $escape($config['app']['name'] ?? 'Sistema FLAG') ?> · Painel</a><a class="app-link" href="../eventos">Ver agenda pública</a></header>
<main class="admin-content events-admin-content">
    <p class="eyebrow">GESTÃO ESPORTIVA</p><h1>Eventos</h1>
    <p class="events-admin-lead">Organize jogos, treinos, amistosos e compromissos da equipe.</p>
    <?php $noticeMessage = is_array($notice) ? ($notice['message'] ?? '') : $notice; $noticeTone = is_array($notice) ? ($notice['tone'] ?? 'success') : 'success'; ?>
    <?php if (is_string($noticeMessage) && $noticeMessage !== ''): ?><div class="app-toast is-<?= $escape($noticeTone) ?>" data-app-toast role="status" aria-live="polite"><?= $escape($noticeMessage) ?></div><?php endif; ?>
    <div class="events-toolbar"><a class="app-button" href="eventos-novo">Novo evento</a></div>
    <?php if ($events === []): ?><section class="events-admin-empty"><span aria-hidden="true">◷</span><h2>A agenda ainda está vazia</h2><p>Cadastre o primeiro jogo, treino ou compromisso da equipe.</p></section>
    <?php else: ?><div class="events-admin-list">
        <?php foreach ($events as $event): $date = new DateTimeImmutable((string) $event['inicio']); $date = $date->setTimezone(new DateTimeZone('America/Sao_Paulo')); ?>
            <article class="events-admin-card">
                <div class="events-admin-date"><strong><?= $date->format('d') ?></strong><span><?= $escape(['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][(int) $date->format('n') - 1]) ?></span></div>
                <div class="events-admin-info"><span class="event-kind event-kind-<?= $escape($event['tipo']) ?>"><?= $escape(ucfirst((string) $event['tipo'])) ?></span><h2><?= $escape($event['titulo']) ?></h2><p><?= $date->format('d/m/Y · H:i') ?><?= !empty($event['local']) ? ' · ' . $escape($event['local']) : '' ?></p><?php if (!empty($event['categoria_modalidade'])): ?><small><?= $escape($event['categoria_modalidade']) ?> · <?= $event['categoria_genero'] === 'feminino' ? 'Feminino' : 'Masculino' ?></small><?php endif; ?><small><?= (int) $event['confirmados'] ?> confirmações · <?= $escape(ucfirst((string) $event['status'])) ?></small></div>
                <div class="app-item-actions events-admin-actions">
                    <a class="button-secondary app-edit-button" href="eventos.php?route=edit&amp;id=<?= (int) $event['id'] ?>" aria-label="Editar evento <?= $escape($event['titulo']) ?>" title="Editar evento"><?= \App\Core\View::icon('edit') ?><span class="app-visually-hidden">Editar</span></a>
                    <form method="post" action="eventos.php" data-app-confirm data-confirm-message="Excluir este evento e suas confirmações?" data-confirm-event-delete><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $event['id'] ?>"><button type="submit" class="button-secondary app-delete-button" aria-label="Excluir evento <?= $escape($event['titulo']) ?>" title="Excluir evento"><?= \App\Core\View::icon('delete') ?><span class="app-visually-hidden">Excluir</span></button></form>
                </div>
            </article>
        <?php endforeach; ?>
    </div><?php endif; ?>
</main>
<dialog class="app-confirm-dialog" data-app-confirm-dialog aria-labelledby="app-confirm-title"><div class="app-confirm-content"><span class="app-confirm-icon" aria-hidden="true">!</span><div><h2 id="app-confirm-title">Confirmar exclusão</h2><p data-app-confirm-message>Deseja excluir este evento?</p></div></div><div class="app-confirm-actions"><button class="app-button app-button-subtle" type="button" data-app-confirm-cancel>Cancelar</button><button class="app-button app-button-danger" type="button" data-app-confirm-accept>Excluir</button></div></dialog>
<script src="<?= $escape(\App\Core\View::assetUrl('../assets/js/app.js', $config)) ?>" defer></script>
<script src="<?= $escape(\App\Core\View::assetUrl('../modules/eventos/Assets/eventos.js', $config)) ?>" defer></script>
<?php require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Layout' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body>
</html>
