<?php

declare(strict_types=1);

/**
 * Modelo de configuração do Sistema FLAG.
 * Copie para conf.php e substitua somente os campos secretos.
 * Nunca coloque conf.php no Git.
 */

return [
    'app' => [
        'name' => 'Sistema FLAG',
        'version' => '0.1.0',
        'environment' => 'development',
        'debug' => false,
    ],
    'assets' => [
        'version' => '1.0.0',
    ],
    'database' => [
        'url' => 'xxx',
        'url_environment_variable' => 'DATABASE_URL',
        'sslmode' => 'require',
        'connect_timeout' => 20,
    ],
];
