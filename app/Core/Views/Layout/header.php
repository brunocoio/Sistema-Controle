<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="./" aria-label="<?= $escape($appName) ?> — início">
            <span class="brand-mark" aria-hidden="true">F</span>
            <span class="brand-name"><?= $escape($appName) ?><small>GESTÃO ESPORTIVA</small></span>
        </a>
        <nav class="main-nav" aria-label="Navegação principal">
            <a href="./">Início</a>
            <?php if (\App\Core\Modules::available('eventos')): ?><a href="eventos">Agenda</a><?php endif; ?>
            <?php if (\App\Core\Modules::available('autenticacao')): ?><a class="button nav-login" href="login">Entrar</a><?php endif; ?>
        </nav>
    </div>
</header>
