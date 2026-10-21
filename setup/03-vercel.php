<?php

/**
 * =========================================================
 * Sistema-Controle - Configuração Vercel
 * =========================================================
 */

function createFile(string $path, string $content): void
{
    $directory = dirname($path);

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (file_exists($path)) {
        echo "Arquivo já existe: {$path}" . PHP_EOL;
        return;
    }

    file_put_contents($path, $content);

    echo "Arquivo criado: {$path}" . PHP_EOL;
}

$projectRoot = dirname(__DIR__);

$dockerfile = <<<'DOCKER'
FROM dunglas/frankenphp:php8.3-alpine

WORKDIR /app

COPY . /app

EXPOSE 80

CMD ["frankenphp", "php-server", "--listen", ":80", "--root", "/app"]
DOCKER;

$dockerfilePath = $projectRoot . DIRECTORY_SEPARATOR . 'Dockerfile.vercel';

createFile($dockerfilePath, $dockerfile);

echo PHP_EOL;
echo "Configuração inicial da Vercel criada com sucesso." . PHP_EOL;