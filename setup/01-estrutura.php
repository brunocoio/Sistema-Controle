<?php

declare(strict_types=1);

/**
 * Sistema FLAG
 *
 * Setup 01 - Estrutura inicial do projeto
 *
 * Responsabilidade:
 * - Criar a estrutura física inicial do projeto.
 * - Criar os arquivos de entrada principais.
 * - Não sobrescrever arquivos existentes.
 *
 * Execução:
 * php setup/01-estrutura.php
 */

$projectRoot = dirname(__DIR__);

/**
 * ---------------------------------------------------------
 * Funções auxiliares
 * ---------------------------------------------------------
 */

function createDirectory(string $path): void
{
    if (is_dir($path)) {
        echo "[SKIP] Pasta já existe: " . getRelativePath($path) . PHP_EOL;
        return;
    }

    if (!mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException(
            "Não foi possível criar a pasta: {$path}"
        );
    }

    echo "[OK] Pasta criada: " . getRelativePath($path) . PHP_EOL;
}

function createFile(string $path, string $content = ''): void
{
    if (file_exists($path)) {
        echo "[SKIP] Arquivo já existe: " . getRelativePath($path) . PHP_EOL;
        return;
    }

    $directory = dirname($path);

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException(
            "Não foi possível criar o arquivo: {$path}"
        );
    }

    echo "[OK] Arquivo criado: " . getRelativePath($path) . PHP_EOL;
}

function getRelativePath(string $path): string
{
    global $projectRoot;

    $relative = str_replace($projectRoot, '', $path);
    $relative = str_replace('\\', '/', $relative);

    return ltrim($relative, '/');
}

/**
 * ---------------------------------------------------------
 * Início
 * ---------------------------------------------------------
 */

echo PHP_EOL;
echo "==============================================" . PHP_EOL;
echo " SISTEMA FLAG - SETUP 01" . PHP_EOL;
echo " Estrutura inicial do projeto" . PHP_EOL;
echo "==============================================" . PHP_EOL;
echo PHP_EOL;

echo "[INFO] Raiz do projeto:" . PHP_EOL;
echo "       {$projectRoot}" . PHP_EOL;
echo PHP_EOL;

/**
 * ---------------------------------------------------------
 * Diretórios principais
 * ---------------------------------------------------------
 */

$directories = [
    'admin',

    'app',
    'app/Core',

    'config',

    'modules',

    'assets',
    'assets/css',
    'assets/js',

    'storage',
    'storage/logs',
    'storage/uploads',
    'storage/cache',

    'setup',
];

foreach ($directories as $directory) {
    createDirectory($projectRoot . DIRECTORY_SEPARATOR . $directory);
}

/**
 * ---------------------------------------------------------
 * Arquivos de entrada
 * ---------------------------------------------------------
 */

createFile(
    $projectRoot . DIRECTORY_SEPARATOR . 'index.php',
    <<<'PHP'
<?php

declare(strict_types=1);

/**
 * Sistema FLAG
 *
 * Front Controller público.
 *
 * A implementação será adicionada
 * nas próximas etapas do setup.
 */

PHP
);

createFile(
    $projectRoot . DIRECTORY_SEPARATOR . 'login.php',
    <<<'PHP'
<?php

declare(strict_types=1);

/**
 * Sistema FLAG
 *
 * Entrada de autenticação.
 *
 * A implementação será adicionada
 * nas próximas etapas do setup.
 */

PHP
);

createFile(
    $projectRoot . DIRECTORY_SEPARATOR . 'logout.php',
    <<<'PHP'
<?php

declare(strict_types=1);

/**
 * Sistema FLAG
 *
 * Encerramento da sessão.
 *
 * A implementação será adicionada
 * nas próximas etapas do setup.
 */

PHP
);

createFile(
    $projectRoot . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'index.php',
    <<<'PHP'
<?php

declare(strict_types=1);

/**
 * Sistema FLAG
 *
 * Front Controller da área administrativa.
 *
 * A implementação será adicionada
 * nas próximas etapas do setup.
 */

PHP
);

/**
 * ---------------------------------------------------------
 * Finalização
 * ---------------------------------------------------------
 */

echo PHP_EOL;
echo "==============================================" . PHP_EOL;
echo " SETUP 01 CONCLUÍDO" . PHP_EOL;
echo "==============================================" . PHP_EOL;
echo PHP_EOL;

echo "Nenhum arquivo existente foi sobrescrito." . PHP_EOL;
echo PHP_EOL;

echo "Próxima etapa:" . PHP_EOL;
echo "setup/02-core.php" . PHP_EOL;
echo PHP_EOL;
