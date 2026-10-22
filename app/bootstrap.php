<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$coreDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Core');
$rootPrefix = rtrim(realpath($projectRoot) ?: $projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if ($coreDirectory === false || !str_starts_with($coreDirectory . DIRECTORY_SEPARATOR, $rootPrefix)) {
    throw new RuntimeException('A pasta Core não está disponível.');
}

spl_autoload_register(static function (string $class) use ($coreDirectory): void {
    $prefix = 'App\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $className = substr($class, strlen($prefix));
    if (preg_match('/\A[A-Za-z][A-Za-z0-9]*\z/', $className) !== 1) {
        return;
    }
    $candidate = $coreDirectory . DIRECTORY_SEPARATOR . $className . '.php';
    $realCandidate = realpath($candidate);
    $prefixPath = rtrim($coreDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($realCandidate !== false && is_file($realCandidate) && str_starts_with($realCandidate, $prefixPath)) {
        require_once $realCandidate;
    }
});

$privateConfigPath = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'conf.php';
$sampleConfigPath = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'conf_sample.php';
if (is_link($privateConfigPath)) {
    throw new RuntimeException('A configuração privada não pode ser um link simbólico.');
}
if (is_link($sampleConfigPath) || !is_file($sampleConfigPath)) {
    throw new RuntimeException('O modelo de configuração não está disponível.');
}
$config = require $sampleConfigPath;
if (!is_array($config)) {
    throw new RuntimeException('O modelo de configuração é inválido.');
}
if (is_file($privateConfigPath)) {
    $privateConfig = require $privateConfigPath;
    if (!is_array($privateConfig)) {
        throw new RuntimeException('A configuração privada é inválida.');
    }
    $config = array_replace_recursive($config, $privateConfig);
}

$environment = getenv('APP_ENV');
if (is_string($environment) && $environment !== '') {
    $config['app']['environment'] = $environment;
}
$debug = getenv('APP_DEBUG');
if (is_string($debug)) {
    $config['app']['debug'] = filter_var($debug, FILTER_VALIDATE_BOOLEAN);
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || $forwardedProto === 'https';
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

return $config;
