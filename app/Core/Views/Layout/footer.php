<footer class="site-footer">
    <div class="container footer-inner">
        <span><?= $escape($appName) ?></span>
        <?php $version = (string) ($config['app']['version'] ?? '1.3.4'); $gitRevision = getenv('VERCEL_GIT_COMMIT_SHA'); ?>
        <span>Plataforma em desenvolvimento · v<?= $escape($version) ?><?= is_string($gitRevision) && preg_match('/\A[a-f0-9]{7,40}\z/i', $gitRevision) === 1 ? ' · Git ' . $escape(substr($gitRevision, 0, 7)) : '' ?></span>
    </div>
</footer>
