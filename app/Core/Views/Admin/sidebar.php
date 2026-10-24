<?php

declare(strict_types=1);
$adminSidebarUser = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
$adminSidebarUserId = (int) ($adminSidebarUser['id'] ?? 0);
$adminSidebarCollapsed = \App\Core\ModuleSettings::sidebarCollapsed($adminSidebarUserId);
$adminSidebarModules = \App\Core\Modules::activeAdminModules();
$adminSidebarActive = is_string($adminActiveModule ?? null) ? $adminActiveModule : '';
?>
<aside class="admin-sidebar" id="admin-module-sidebar" data-admin-sidebar data-collapsed="<?= $adminSidebarCollapsed ? 'true' : 'false' ?>" aria-label="Navegação do painel">
    <div class="admin-sidebar-top"><a class="admin-sidebar-brand" href="index" aria-label="Início do painel"><span class="admin-sidebar-brand-mark" aria-hidden="true">F</span><span class="admin-sidebar-label">Sistema FLAG</span></a><button class="app-button app-button-subtle admin-menu-toggle admin-menu-toggle-sidebar" type="button" data-admin-sidebar-toggle data-csrf="<?= $escape($csrfToken) ?>" aria-controls="admin-module-sidebar" aria-expanded="<?= $adminSidebarCollapsed ? 'false' : 'true' ?>" aria-label="Alternar menu"><?= \App\Core\View::icon('menu') ?><span class="admin-sidebar-label">Menu</span></button></div>
    <nav class="admin-sidebar-nav">
        <a class="admin-sidebar-link <?= $adminSidebarActive === 'home' ? 'is-active' : '' ?>" href="index" title="Início" aria-label="Início"><?= \App\Core\View::icon('home') ?><span class="admin-sidebar-label">Início</span></a>
        <?php if (($adminSidebarUser['role'] ?? '') === 'admin' && $adminSidebarModules !== []): ?><p class="admin-sidebar-group admin-sidebar-label">MÓDULOS</p><?php foreach ($adminSidebarModules as $moduleKey => $module): ?>
            <a class="admin-sidebar-link <?= $adminSidebarActive === $moduleKey ? 'is-active' : '' ?>" href="<?= $escape($module['admin_href']) ?>" title="<?= $escape($module['title']) ?>" aria-label="<?= $escape($module['title']) ?>"><?= \App\Core\View::icon($module['icon']) ?><span class="admin-sidebar-label"><?= $escape($module['title']) ?></span></a>
        <?php endforeach; endif; ?>
        <a class="admin-sidebar-link <?= $adminSidebarActive === 'profile' ? 'is-active' : '' ?>" href="meu-perfil" title="Meu perfil" aria-label="Meu perfil"><?= \App\Core\View::icon('user') ?><span class="admin-sidebar-label">Meu perfil</span></a>
        <?php if (($adminSidebarUser['role'] ?? '') === 'admin'): ?><div class="admin-sidebar-separator"></div><a class="admin-sidebar-link <?= $adminSidebarActive === 'system' ? 'is-active' : '' ?>" href="modulos.php" title="Controle de módulos" aria-label="Controle de módulos"><?= \App\Core\View::icon('shield') ?><span class="admin-sidebar-label">Controle de módulos</span></a><?php endif; ?>
    </nav>
</aside>
<button type="button" class="admin-sidebar-backdrop" data-admin-sidebar-backdrop aria-label="Fechar menu"></button>
