<?php
$footerAppName = isset($appName) && is_string($appName) && $appName !== ''
    ? $appName
    : (is_array($config ?? null) ? (string) ($config['app']['name'] ?? 'Sistema FLAG') : 'Sistema FLAG');
$footerVersion = is_array($config ?? null) ? (string) ($config['app']['version'] ?? '1.3.5') : '1.3.5';
?>
<footer class="site-footer">
    <div class="container footer-inner">
        <span><?= $escape($footerAppName) ?></span>
        <?php $gitRevision = getenv('VERCEL_GIT_COMMIT_SHA'); ?>
        <span>Plataforma em desenvolvimento · v<?= $escape($footerVersion) ?><?= is_string($gitRevision) && preg_match('/\A[a-f0-9]{7,40}\z/i', $gitRevision) === 1 ? ' · Git ' . $escape(substr($gitRevision, 0, 7)) : '' ?></span>
    </div>
</footer>
