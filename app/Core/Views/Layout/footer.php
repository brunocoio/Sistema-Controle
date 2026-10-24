<?php
$footerAppName = isset($appName) && is_string($appName) && $appName !== ''
    ? $appName
    : (is_array($config ?? null) ? (string) ($config['app']['name'] ?? 'Sistema FLAG') : 'Sistema FLAG');
$footerSystemVersion = 1;
$footerModuleCount = 1 + count(\App\Core\Modules::catalog()); // Autenticação + módulos do catálogo.
// Incrementar uma unidade a cada nova entrega no Git; novos módulos são contados pelo catálogo acima.
$footerDelivery = 7;
$footerVersion = $footerSystemVersion . '.' . $footerModuleCount . '.' . $footerDelivery;
?>
<footer class="site-footer">
    <div class="container footer-inner">
        <span><?= $escape($footerAppName) ?></span>
        <?php $gitRevision = getenv('VERCEL_GIT_COMMIT_SHA'); ?>
        <span>Plataforma em desenvolvimento · v<?= $escape($footerVersion) ?><?= is_string($gitRevision) && preg_match('/\A[a-f0-9]{7,40}\z/i', $gitRevision) === 1 ? ' · | · ' . $escape(substr($gitRevision, 0, 7)) : '' ?></span>
    </div>
</footer>
