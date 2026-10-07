<?php

declare(strict_types=1);

/**
 * Sistema FLAG
 *
 * Setup 02 - Arquivos do Git
 *
 * Responsabilidade:
 * - Criar o arquivo .gitignore.
 * - Criar os arquivos .gitkeep necessários.
 * - Não sobrescrever arquivos existentes.
 *
 * Execução:
 * php setup/02-git.php
 */

$projectRoot = dirname(__DIR__);

/**
 * ---------------------------------------------------------
 * Funções auxiliares
 * ---------------------------------------------------------
 */

function createFile(string $path, string $content = ''): void
{
    if (file_exists($path)) {
        echo "[SKIP] Arquivo já existe: " . getRelativePath($path) . PHP_EOL;
        return;
    }

    $directory = dirname($path);

    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(
                "Não foi possível criar a pasta: {$directory}"
            );
        }
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
echo " SISTEMA FLAG - SETUP 02" . PHP_EOL;
echo " Arquivos do Git" . PHP_EOL;
echo "==============================================" . PHP_EOL;
echo PHP_EOL;

echo "[INFO] Raiz do projeto:" . PHP_EOL;
echo "       {$projectRoot}" . PHP_EOL;
echo PHP_EOL;

/**
 * ---------------------------------------------------------
 * .gitignore
 * ---------------------------------------------------------
 */

$gitignore = <<<'GITIGNORE'
# =========================================================
# Sistema FLAG - Git Ignore
# =========================================================

# ---------------------------------------------------------
# Ambiente
# ---------------------------------------------------------

.env
.env.*

# ---------------------------------------------------------
# Dependências
# ---------------------------------------------------------

/vendor/

# ---------------------------------------------------------
# Logs
# ---------------------------------------------------------

/storage/logs/*
!/storage/logs/.gitkeep

# ---------------------------------------------------------
# Uploads
# ---------------------------------------------------------

/storage/uploads/*
!/storage/uploads/.gitkeep

# ---------------------------------------------------------
# Cache
# ---------------------------------------------------------

/storage/cache/*
!/storage/cache/.gitkeep

# ---------------------------------------------------------
# Sistema operacional
# ---------------------------------------------------------

.DS_Store
Thumbs.db

# ---------------------------------------------------------
# IDE / Editor
# ---------------------------------------------------------

.vscode/
.idea/

# ---------------------------------------------------------
# Arquivos temporários
# ---------------------------------------------------------

*.tmp
*.temp
*.swp
*.swo
GITIGNORE;

createFile(
    $projectRoot . DIRECTORY_SEPARATOR . '.gitignore',
    $gitignore . PHP_EOL
);

/**
 * ---------------------------------------------------------
 * .gitkeep
 * ---------------------------------------------------------
 */

$gitkeepDirectories = [
    'storage/logs',
    'storage/uploads',
    'storage/cache',
];

foreach ($gitkeepDirectories as $directory) {
    createFile(
        $projectRoot
            . DIRECTORY_SEPARATOR
            . $directory
            . DIRECTORY_SEPARATOR
            . '.gitkeep',
        ''
    );
}

/**
 * ---------------------------------------------------------
 * Finalização
 * ---------------------------------------------------------
 */

echo PHP_EOL;
echo "==============================================" . PHP_EOL;
echo " SETUP 02 CONCLUÍDO" . PHP_EOL;
echo "==============================================" . PHP_EOL;
echo PHP_EOL;

echo "Arquivos do Git preparados." . PHP_EOL;
echo "Nenhum arquivo existente foi sobrescrito." . PHP_EOL;
echo PHP_EOL;

echo "Arquivos criados/verificados:" . PHP_EOL;
echo ".gitignore" . PHP_EOL;
echo "storage/logs/.gitkeep" . PHP_EOL;
echo "storage/uploads/.gitkeep" . PHP_EOL;
echo "storage/cache/.gitkeep" . PHP_EOL;
echo PHP_EOL;

echo "Próxima etapa:" . PHP_EOL;
echo "Validação do ambiente local e Git." . PHP_EOL;
echo PHP_EOL;