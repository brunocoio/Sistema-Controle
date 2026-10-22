<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $root = dirname(__DIR__, 2);
        $configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'conf.php';
        $config = [];
        if (is_link($configPath)) {
            throw new RuntimeException('A configuração privada do banco é inválida.');
        }
        if (is_file($configPath)) {
            $loadedConfig = require $configPath;
            if (!is_array($loadedConfig)) {
                throw new RuntimeException('A configuração privada do banco é inválida.');
            }
            $config = $loadedConfig;
        }

        $databaseConfig = array_replace_recursive([
            'url' => null,
            'url_environment_variable' => 'DATABASE_URL_UNPOOLED',
            'sslmode' => 'require',
            'connect_timeout' => 20,
        ], is_array($config['database'] ?? null) ? $config['database'] : []);

        $url = $databaseConfig['url'] ?? null;
        if (!is_string($url) || $url === '' || $url === 'xxx') {
            $variable = (string) ($databaseConfig['url_environment_variable'] ?? 'DATABASE_URL_UNPOOLED');
            $url = getenv($variable);
        }
        if (!is_string($url) || $url === '') {
            throw new RuntimeException('A configuração do banco não está disponível.');
        }
        if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('O driver PDO PostgreSQL não está habilitado.');
        }

        try {
            $parts = parse_url($url);
            if (!is_array($parts)
                || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)
                || empty($parts['host'])
                || empty($parts['user'])
                || !array_key_exists('pass', $parts)
                || empty($parts['path'])
            ) {
                throw new RuntimeException('Formato de conexão PostgreSQL inválido.');
            }

            $host = $parts['host'];
            $port = (int) ($parts['port'] ?? 5432);
            $database = rawurldecode(ltrim($parts['path'], '/'));
            $username = rawurldecode($parts['user']);
            $password = rawurldecode($parts['pass']);
            if ($port < 1 || $port > 65535 || $database === '') {
                throw new RuntimeException('Parâmetros de conexão PostgreSQL inválidos.');
            }

            $sslmode = (string) ($databaseConfig['sslmode'] ?? 'require');
            $timeout = (int) ($databaseConfig['connect_timeout'] ?? 20);
            if (preg_match('/\A[a-zA-Z0-9](?:[a-zA-Z0-9.-]{0,251}[a-zA-Z0-9])?\z/', $host) !== 1) {
                throw new RuntimeException('Hostname PostgreSQL inválido.');
            }

            $hostLabel = explode('.', $host, 2)[0];
            $endpointId = preg_replace('/-pooler\z/', '', $hostLabel);
            $databaseName = $database;
            if (str_starts_with($hostLabel, 'ep-')) {
                if (!is_string($endpointId) || preg_match('/\Aep-[a-zA-Z0-9-]+\z/', $endpointId) !== 1) {
                    throw new RuntimeException('Identificador do endpoint PostgreSQL inválido.');
                }
                $databaseName .= ' options=endpoint=' . $endpointId;
            }

            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=%d',
                $host,
                $port,
                $databaseName,
                $sslmode,
                max(1, min($timeout, 30))
            );
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return self::$connection;
        } catch (PDOException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            if (in_array($sqlState, ['28P01', '28000'], true)) {
                throw new RuntimeException('Conexão recusada pelo PostgreSQL: autenticação inválida. Confira a configuração privada.');
            }
            if (str_starts_with($sqlState, '08') || $sqlState === '7' || $driverCode === 7) {
                $driverMessage = strtolower(implode(' ', array_filter([
                    (string) ($exception->errorInfo[2] ?? ''),
                    $exception->getMessage(),
                ])));
                $category = match (true) {
                    str_contains($driverMessage, 'endpoint id is not specified'),
                    str_contains($driverMessage, 'endpoint is not specified'),
                    str_contains($driverMessage, 'sni') => 'endpoint_sni',
                    str_contains($driverMessage, 'could not translate host name'),
                    str_contains($driverMessage, 'name or service not known'),
                    str_contains($driverMessage, 'no such host'),
                    str_contains($driverMessage, 'temporary failure in name resolution') => 'dns',
                    str_contains($driverMessage, 'timeout expired'),
                    str_contains($driverMessage, 'connection timed out') => 'timeout',
                    str_contains($driverMessage, 'network is unreachable') => 'network_unreachable',
                    str_contains($driverMessage, 'no route to host') => 'no_route',
                    str_contains($driverMessage, 'connection refused') => 'refused',
                    str_contains($driverMessage, 'invalid connection option'),
                    str_contains($driverMessage, 'invalid keyword') => 'dsn',
                    str_contains($driverMessage, 'ssl'),
                    str_contains($driverMessage, 'tls') => 'tls',
                    str_contains($driverMessage, 'server closed the connection'),
                    str_contains($driverMessage, 'connection reset by peer'),
                    str_contains($driverMessage, 'eof detected') => 'server_closed',
                    default => 'unknown',
                };
                $safeState = preg_match('/\A[A-Z0-9]{5}\z/', $sqlState) === 1 ? $sqlState : 'UNKNOWN';
                error_log('PDO_CONNECT_DIAGNOSTIC category=' . $category
                    . ' sqlstate=' . $safeState . ' driver_code=' . $driverCode);
                throw new RuntimeException('A tentativa de conexão ao PostgreSQL falhou antes da consulta. Confira a disponibilidade do banco e da rede.');
            }
            throw new RuntimeException('Falha do driver PostgreSQL (SQLSTATE ' . $sqlState . ').');
        }
    }
}
