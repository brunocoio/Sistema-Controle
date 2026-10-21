<?php

declare(strict_types=1);

/**
 * Sistema FLAG — instalador único
 *
 * Prepara a estrutura local, arquivos-base, conexão PostgreSQL, Core e Router.
 * Todos os arquivos existentes são preservados, exceto atualizações de modelos
 * Arquivos existentes são preservados; a migração local é explícita e não cria backups temporários.
 *
 * Uso:
 *   php setup/install.php
 *   php setup/install.php --migrate-local-config (migra configuração local antiga)
 *   php setup/install.php --check-db           (executa somente SELECT 1)
 */

if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80000) {
    fwrite(STDERR, '[ERRO] Execute este instalador pela linha de comando com PHP 8.0 ou superior.' . PHP_EOL);
    exit(1);
}

$root = realpath(dirname(__DIR__));
if ($root === false || !is_dir($root)) {
    throw new RuntimeException('Não foi possível localizar a raiz do projeto.');
}

$arguments = array_slice($argv, 1);
$knownOptions = ['--migrate-local-config', '--migrate-auth', '--create-admin', '--check-db', '--help'];
foreach ($arguments as $argument) {
    if (!in_array($argument, $knownOptions, true)) {
        fwrite(STDERR, '[ERRO] Opção desconhecida. Consulte --help.' . PHP_EOL);
        exit(2);
    }
}
if (in_array('--help', $arguments, true)) {
    echo "Instalador único do Sistema FLAG\n\n";
    echo "php setup/install.php                  Prepara a estrutura e os arquivos ausentes\n";
    echo "php setup/install.php --migrate-local-config  Migra configuração local antiga para config/conf.php\n";
    echo "php setup/install.php --migrate-auth    Aplica a migration de autenticação ao banco selecionado\n";
    echo "php setup/install.php --create-admin   Cria administrador inicial; requer senha no ambiente\n";
    echo "php setup/install.php --check-db       Testa o banco local com SELECT 1\n";
    exit(0);
}

$directories = [
    'admin', 'app', 'app/Core', 'app/Core/Views', 'app/Core/Views/Layout',
    'app/Core/Views/Pages', 'app/Core/Views/Pages/Public', 'config', 'modules', 'modules/autenticacao', 'modules/autenticacao/Controllers', 'modules/autenticacao/Views', 'modules/autenticacao/Database', 'modules/autenticacao/Database/migrations',
    'assets', 'assets/css', 'assets/js', 'storage', 'storage/logs',
    'storage/uploads', 'storage/cache', 'setup',
];

$templates = [
    '.gitignore' => <<<'FLAG_INSTALL_TEMPLATE_0000'
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

# Private configuration
/config/conf.php
FLAG_INSTALL_TEMPLATE_0000,
    'Dockerfile.vercel' => <<<'FLAG_INSTALL_TEMPLATE_0001'
FROM dunglas/frankenphp:php8.3-alpine

RUN install-php-extensions pdo_pgsql

WORKDIR /app

COPY . /app

EXPOSE 80

CMD ["sh", "-c", "frankenphp php-server --listen :${PORT:-80} --root /app"]
FLAG_INSTALL_TEMPLATE_0001,
    'index.php' => <<<'FLAG_INSTALL_TEMPLATE_0002'
<?php

declare(strict_types=1);

$config = require __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
\App\Core\Application::run($config);
FLAG_INSTALL_TEMPLATE_0002,
    'login.php' => <<<'FLAG_INSTALL_TEMPLATE_0003'
<?php

declare(strict_types=1);

$config = require __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'autenticacao'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'LoginController.php';

(new \Modules\Autenticacao\Controllers\LoginController())->handle($config);
FLAG_INSTALL_TEMPLATE_0003,
    'logout.php' => <<<'FLAG_INSTALL_TEMPLATE_0004'
<?php

declare(strict_types=1);

$config = require __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'autenticacao'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'LogoutController.php';

(new \Modules\Autenticacao\Controllers\LogoutController())->handle();
FLAG_INSTALL_TEMPLATE_0004,
    'admin/index.php' => <<<'FLAG_INSTALL_TEMPLATE_0005'
<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'autenticacao'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'AdminController.php';

(new \Modules\Autenticacao\Controllers\AdminController())->handle($config);
FLAG_INSTALL_TEMPLATE_0005,
    'config/conf_sample.php' => <<<'FLAG_INSTALL_TEMPLATE_0006'
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
FLAG_INSTALL_TEMPLATE_0006,
    'app/bootstrap.php' => <<<'FLAG_INSTALL_TEMPLATE_0007'
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
}

return $config;
FLAG_INSTALL_TEMPLATE_0007,
    'app/Core/Database.php' => <<<'FLAG_INSTALL_TEMPLATE_0008'
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
            'url_environment_variable' => 'DATABASE_URL',
            'sslmode' => 'require',
            'connect_timeout' => 20,
        ], is_array($config['database'] ?? null) ? $config['database'] : []);

        $url = $databaseConfig['url'] ?? null;
        if (!is_string($url) || $url === '' || $url === 'xxx') {
            $variable = (string) ($databaseConfig['url_environment_variable'] ?? 'DATABASE_URL');
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
            $endpointOption = '';
            if (str_starts_with($hostLabel, 'ep-')) {
                if (!is_string($endpointId) || preg_match('/\Aep-[a-zA-Z0-9-]+\z/', $endpointId) !== 1) {
                    throw new RuntimeException('Identificador do endpoint PostgreSQL inválido.');
                }
                $endpointOption = ";options='endpoint=" . $endpointId . "'";
            }

            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=%d%s',
                $host,
                $port,
                $database,
                $sslmode,
                max(1, min($timeout, 30)),
                $endpointOption
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
                throw new RuntimeException('A tentativa de conexão ao PostgreSQL falhou antes da consulta. Confira a disponibilidade do banco e da rede.');
            }
            throw new RuntimeException('Falha do driver PostgreSQL (SQLSTATE ' . $sqlState . ').');
        }
    }
}
FLAG_INSTALL_TEMPLATE_0008,
    'app/Core/DatabaseSessionHandler.php' => <<<'FLAG_INSTALL_TEMPLATE_0009'
<?php

declare(strict_types=1);

namespace App\Core;

use SessionHandlerInterface;
use Throwable;

final class DatabaseSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        try {
            $statement = Database::connection()->prepare(
                'SELECT payload FROM app_sessions WHERE session_id = :id AND expires_at > CURRENT_TIMESTAMP'
            );
            $statement->execute(['id' => $id]);
            $payload = $statement->fetchColumn();
            if (!is_string($payload)) {
                return '';
            }
            $decoded = base64_decode($payload, true);
            return is_string($decoded) ? $decoded : '';
        } catch (Throwable $exception) {
            error_log('Falha ao ler sessão no banco: ' . get_class($exception));
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $statement = Database::connection()->prepare(
                'INSERT INTO app_sessions (session_id, payload, expires_at)
                 VALUES (:id, :payload, CURRENT_TIMESTAMP + INTERVAL \'2 hours\')
                 ON CONFLICT (session_id) DO UPDATE
                 SET payload = EXCLUDED.payload, expires_at = EXCLUDED.expires_at'
            );
            return $statement->execute(['id' => $id, 'payload' => base64_encode($data)]);
        } catch (Throwable $exception) {
            error_log('Falha ao gravar sessão no banco: ' . get_class($exception));
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            return Database::connection()->prepare('DELETE FROM app_sessions WHERE session_id = :id')
                ->execute(['id' => $id]);
        } catch (Throwable $exception) {
            error_log('Falha ao remover sessão do banco: ' . get_class($exception));
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $statement = Database::connection()->prepare('DELETE FROM app_sessions WHERE expires_at <= CURRENT_TIMESTAMP');
            if (!$statement->execute()) {
                return false;
            }
            return $statement->rowCount();
        } catch (Throwable $exception) {
            error_log('Falha ao limpar sessões do banco: ' . get_class($exception));
            return false;
        }
    }
}
FLAG_INSTALL_TEMPLATE_0009,
    'app/Core/Auth.php' => <<<'FLAG_INSTALL_TEMPLATE_0010'
<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public static function attempt(string $email, string $password): bool
    {
        Session::start();
        $email = strtolower(trim($email));
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (strlen($email) > 254 || strlen($password) > 4096 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            self::dummyVerify($password);
            return false;
        }

        $db = Database::connection();
        if (random_int(1, 100) === 1) {
            $db->exec("DELETE FROM autenticacao_tentativas WHERE inicio_janela < CURRENT_TIMESTAMP - INTERVAL '1 day'");
        }
        $keys = [
            hash('sha256', "email-ip\0" . $email . "\0" . $ip),
            hash('sha256', "ip\0" . $ip),
        ];
        if (self::isRateLimited($db, $keys)) {
            self::dummyVerify($password);
            return false;
        }

        $statement = $db->prepare(
            'SELECT id, nome, email, password_hash, perfil
             FROM usuarios
             WHERE email_normalizado = :email AND ativo = TRUE
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        $hash = is_array($user) && is_string($user['password_hash'] ?? null)
            ? $user['password_hash']
            : self::dummyHash();
        $valid = password_verify($password, $hash);

        if (!$valid || !is_array($user)) {
            self::recordFailure($db, $keys);
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $rehash = $db->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
            $rehash->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }
        self::clearFailures($db, $keys);
        Session::regenerate();
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['nome'],
            'email' => (string) $user['email'],
            'role' => (string) $user['perfil'],
        ];
        Csrf::rotate();
        return true;
    }

    public static function user(): ?array
    {
        Session::start();
        $stored = $_SESSION['user'] ?? null;
        if (!is_array($stored) || !isset($stored['id']) || !is_numeric($stored['id'])) {
            return null;
        }
        $statement = Database::connection()->prepare(
            'SELECT id, nome, email, perfil FROM usuarios WHERE id = :id AND ativo = TRUE LIMIT 1'
        );
        $statement->execute(['id' => (int) $stored['id']]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            unset($_SESSION['user']);
            return null;
        }
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['nome'],
            'email' => (string) $user['email'],
            'role' => (string) $user['perfil'],
        ];
        return $_SESSION['user'];
    }

    public static function hasRole(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array($user['role'] ?? null, $roles, true);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    private static function isRateLimited(PDO $db, array $keys): bool
    {
        $statement = $db->prepare(
            'SELECT 1 FROM autenticacao_tentativas
             WHERE chave_hash = :key AND bloqueado_ate > CURRENT_TIMESTAMP'
        );
        foreach ($keys as $key) {
            $statement->execute(['key' => $key]);
            if ($statement->fetchColumn() !== false) {
                return true;
            }
        }
        return false;
    }

    private static function recordFailure(PDO $db, array $keys): void
    {
        $statement = $db->prepare(
            'INSERT INTO autenticacao_tentativas (chave_hash, tentativas, inicio_janela, bloqueado_ate)
             VALUES (:key, 1, CURRENT_TIMESTAMP, NULL)
             ON CONFLICT (chave_hash) DO UPDATE SET
                tentativas = CASE
                    WHEN autenticacao_tentativas.inicio_janela < CURRENT_TIMESTAMP - INTERVAL \'15 minutes\' THEN 1
                    ELSE autenticacao_tentativas.tentativas + 1
                END,
                inicio_janela = CASE
                    WHEN autenticacao_tentativas.inicio_janela < CURRENT_TIMESTAMP - INTERVAL \'15 minutes\' THEN CURRENT_TIMESTAMP
                    ELSE autenticacao_tentativas.inicio_janela
                END,
                bloqueado_ate = CASE
                    WHEN (CASE
                        WHEN autenticacao_tentativas.inicio_janela < CURRENT_TIMESTAMP - INTERVAL \'15 minutes\' THEN 1
                        ELSE autenticacao_tentativas.tentativas + 1
                    END) >= 5 THEN CURRENT_TIMESTAMP + INTERVAL \'15 minutes\'
                    ELSE NULL
                END'
        );
        foreach ($keys as $key) {
            $statement->execute(['key' => $key]);
        }
    }

    private static function clearFailures(PDO $db, array $keys): void
    {
        $statement = $db->prepare('DELETE FROM autenticacao_tentativas WHERE chave_hash = :key');
        foreach ($keys as $key) {
            $statement->execute(['key' => $key]);
        }
    }

    private static function dummyVerify(string $password): void
    {
        password_verify($password, self::dummyHash());
    }

    private static function dummyHash(): string
    {
        return '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    }

    public static function requireRole(string ...$roles): array
    {
        $user = self::user();
        if ($user === null) {
            header('Location: ../login.php', true, 303);
            exit;
        }
        if (!in_array($user['role'] ?? null, $roles, true)) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        return $user;
    }
}
FLAG_INSTALL_TEMPLATE_0010,
    'app/Core/Session.php' => <<<'FLAG_INSTALL_TEMPLATE_0011'
<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            throw new RuntimeException('A sessão não pode ser iniciada depois do envio da resposta.');
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
        $cookiePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if (basename($cookiePath) === 'admin') {
            $cookiePath = dirname($cookiePath);
        }
        if ($cookiePath === '.' || $cookiePath === '') {
            $cookiePath = '/';
        }
        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || $forwardedProto === 'https';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_name('FLAGSESSID');
        session_set_save_handler(new DatabaseSessionHandler(), true);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new RuntimeException('Não foi possível iniciar a sessão.');
        }
    }

    public static function regenerate(): void
    {
        self::start();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Não foi possível renovar a sessão.');
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}
FLAG_INSTALL_TEMPLATE_0011,
    'app/Core/Csrf.php' => <<<'FLAG_INSTALL_TEMPLATE_0012'
<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(mixed $token): bool
    {
        Session::start();
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && is_string($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function rotate(): void
    {
        Session::start();
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
FLAG_INSTALL_TEMPLATE_0012,
    'app/Core/Request.php' => <<<'FLAG_INSTALL_TEMPLATE_0013'
<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private string $method;
    private string $path;
    private bool $valid;

    private function __construct(string $method, string $path, bool $valid)
    {
        $this->method = $method;
        $this->path = $path;
        $this->valid = $valid;
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        if (preg_match('/\A[A-Z]{1,16}\z/', $method) !== 1 || !is_string($path)) {
            return new self($method, '/', false);
        }

        if (preg_match('/%(?:2f|5c|00)/i', $path) === 1) {
            return new self($method, '/', false);
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        }

        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath));
        }

        $path = preg_replace('#/+#', '/', $path) ?? '/';
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach (explode('/', trim($path, '/')) as $segment) {
            $decoded = rawurldecode($segment);
            if ($decoded === '.' || $decoded === '..' || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
                return new self($method, '/', false);
            }
        }

        return new self($method, $path, true);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }
}
FLAG_INSTALL_TEMPLATE_0013,
    'app/Core/Response.php' => <<<'FLAG_INSTALL_TEMPLATE_0014'
<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int $status;
    private array $headers;
    private string $body;

    public function __construct(int $status = 200, array $headers = [], string $body = '')
    {
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException('Código HTTP inválido.');
        }

        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=UTF-8'] + $headers, $body);
    }

    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=UTF-8'] + $headers, $body);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(string $requestMethod = 'GET'): void
    {
        http_response_code($this->status);

        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'",
        ];

        foreach ($securityHeaders + $this->headers as $name => $value) {
            if (!is_string($name) || !is_string($value)
                || preg_match('/\A[a-zA-Z0-9!#$%&\'*+.^_`|~-]+\z/', $name) !== 1
                || preg_match('/[\r\n]/', $value) === 1
            ) {
                continue;
            }

            header($name . ': ' . $value, true);
        }

        if (strtoupper($requestMethod) !== 'HEAD') {
            echo $this->body;
        }
    }
}
FLAG_INSTALL_TEMPLATE_0014,
    'app/Core/Router.php' => <<<'FLAG_INSTALL_TEMPLATE_0015'
<?php

declare(strict_types=1);

namespace App\Core;

use LogicException;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);
        $path = $this->normalizeRoutePath($path);

        if (preg_match('/\A[A-Z]{1,16}\z/', $method) !== 1) {
            throw new \InvalidArgumentException('Método HTTP inválido.');
        }

        if (isset($this->routes[$path][$method])) {
            throw new LogicException('Rota duplicada registrada.');
        }

        $this->routes[$path][$method] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        if (!$request->isValid()) {
            return Response::text('Requisição inválida.', 400);
        }

        $path = $request->path();
        $method = $request->method();
        $handlers = $this->routes[$path] ?? [];

        if ($method === 'HEAD' && isset($handlers['GET'])) {
            $response = $handlers['GET']($request);
            if (!$response instanceof Response) {
                throw new LogicException('O handler da rota deve retornar Response.');
            }

            return new Response($response->status(), $response->headers(), '');
        }

        if (isset($handlers[$method])) {
            $response = $handlers[$method]($request);
            if (!$response instanceof Response) {
                throw new LogicException('O handler da rota deve retornar Response.');
            }

            return $response;
        }

        if ($handlers !== []) {
            $allowed = array_keys($handlers);
            if (isset($handlers['GET']) && !in_array('HEAD', $allowed, true)) {
                $allowed[] = 'HEAD';
            }

            return Response::text('Método não permitido.', 405, ['Allow' => implode(', ', $allowed)]);
        }

        return Response::text('Página não encontrada.', 404);
    }

    private function normalizeRoutePath(string $path): string
    {
        if ($path === '' || $path[0] !== '/' || preg_match('/[\r\n\x00]/', $path) === 1) {
            throw new \InvalidArgumentException('Caminho de rota inválido.');
        }

        $path = preg_replace('#/+#', '/', $path) ?? '/';
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
FLAG_INSTALL_TEMPLATE_0015,
    'app/Core/Application.php' => <<<'FLAG_INSTALL_TEMPLATE_0016'
<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Application
{
    public static function run(array $config): void
    {
        $router = new Router();
        $router->add('GET', '/', static function () use ($config): Response {
            $name = (string) ($config['app']['name'] ?? 'Sistema FLAG');
            return Response::html(View::renderPublic('home', ['appName' => $name], $config));
        });

        $request = Request::fromGlobals();
        try {
            $router->dispatch($request)->send($request->method());
        } catch (Throwable $exception) {
            $incidentId = bin2hex(random_bytes(6));
            error_log('Falha interna da aplicação. Referência: ' . $incidentId . '; tipo: ' . get_class($exception));
            Response::text('Erro interno. Referência: ' . $incidentId, 500)->send($request->method());
        }
    }
}
FLAG_INSTALL_TEMPLATE_0016,
    'app/Core/View.php' => <<<'FLAG_INSTALL_TEMPLATE_0017'
<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class View
{
    public static function escape(mixed $value): string
    {
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function assetUrl(string $path, array $config): string
    {
        $prefix = '';
        if (str_starts_with($path, '../assets/')) {
            $prefix = '../';
            $path = substr($path, 3);
        } elseif (str_starts_with($path, './assets/')) {
            $prefix = './';
            $path = substr($path, 2);
        }
        if (preg_match('#\Aassets/[A-Za-z0-9._/-]+\z#D', $path) !== 1) {
            throw new RuntimeException('Caminho de recurso local inválido.');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Caminho de recurso local inválido.');
            }
        }
        $version = $config['assets']['version'] ?? '1.0.0';
        if (!is_string($version) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $version) !== 1) {
            throw new RuntimeException('Versão de recursos estáticos inválida.');
        }

        return $prefix . $path . '?v=' . rawurlencode($version);
    }

    public static function renderPublic(string $page, array $data, array $config): string
    {
        if (preg_match('/\A[a-z][a-z0-9_-]*\z/', $page) !== 1) {
            throw new RuntimeException('Identificador de página inválido.');
        }
        $viewsRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Views');
        if ($viewsRoot === false) {
            throw new RuntimeException('As views públicas não estão disponíveis.');
        }
        $pagesRoot = realpath($viewsRoot . DIRECTORY_SEPARATOR . 'Pages' . DIRECTORY_SEPARATOR . 'Public');
        if ($pagesRoot === false) {
            throw new RuntimeException('As páginas públicas não estão disponíveis.');
        }
        $pagePath = realpath($pagesRoot . DIRECTORY_SEPARATOR . $page . '.php');
        $pagePrefix = rtrim($pagesRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($pagePath === false || !str_starts_with($pagePath, $pagePrefix) || !is_file($pagePath)) {
            throw new RuntimeException('A página solicitada não está disponível.');
        }
        $layoutRoot = realpath($viewsRoot . DIRECTORY_SEPARATOR . 'Layout');
        $layoutPath = $layoutRoot === false ? false : realpath($layoutRoot . DIRECTORY_SEPARATOR . 'layout.php');
        $layoutPrefix = $layoutRoot === false ? '' : rtrim($layoutRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($layoutRoot === false || $layoutPath === false || !str_starts_with($layoutPath, $layoutPrefix) || !is_file($layoutPath)) {
            throw new RuntimeException('O layout público não está disponível.');
        }
        $appName = (string) ($data['appName'] ?? $config['app']['name'] ?? 'Sistema FLAG');
        $pageTitle = (string) ($data['pageTitle'] ?? $appName);
        $escape = [self::class, 'escape'];
        $content = self::capture(static function () use ($pagePath, $data, $escape): void {
            extract($data, EXTR_SKIP);
            require $pagePath;
        });
        return self::capture(static function () use ($layoutPath, $appName, $pageTitle, $content, $escape, $config): void {
            require $layoutPath;
        });
    }

    private static function capture(callable $render): string
    {
        ob_start();
        try {
            $render();
            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
FLAG_INSTALL_TEMPLATE_0017,
    'app/Core/Views/Layout/layout.php' => <<<'FLAG_INSTALL_TEMPLATE_0018'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plataforma em desenvolvimento para organização de FLAG Football.">
    <title><?= $escape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('./assets/css/app.css', $config)) ?>">
</head>
<body>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'header.php'; ?>
    <main id="conteudo">
        <?= $content ?>
    </main>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body>
</html>
FLAG_INSTALL_TEMPLATE_0018,
    'app/Core/Views/Layout/header.php' => <<<'FLAG_INSTALL_TEMPLATE_0019'
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="./" aria-label="<?= $escape($appName) ?> — início">
            <span class="brand-mark" aria-hidden="true">F</span>
            <span class="brand-name"><?= $escape($appName) ?><small>GESTÃO ESPORTIVA</small></span>
        </a>
        <nav class="main-nav" aria-label="Navegação principal">
            <a href="./">Início</a>
            <a href="#sobre">Sobre</a>
            <a class="button nav-login" href="login.php">Entrar</a>
        </nav>
    </div>
</header>
FLAG_INSTALL_TEMPLATE_0019,
    'app/Core/Views/Layout/footer.php' => <<<'FLAG_INSTALL_TEMPLATE_0020'
<footer class="site-footer">
    <div class="container footer-inner">
        <span><?= $escape($appName) ?></span>
        <span>Plataforma em desenvolvimento</span>
    </div>
</footer>
FLAG_INSTALL_TEMPLATE_0020,
    'app/Core/Views/Pages/Public/home.php' => <<<'FLAG_INSTALL_TEMPLATE_0021'
<section class="hero" aria-labelledby="hero-title">
    <div class="container hero-inner">
        <p class="eyebrow"><span class="eyebrow-line"></span> SISTEMA DE GESTÃO PARA FLAG FOOTBALL</p>
        <h1 id="hero-title">Mais organização.<br><span>Mais jogo.</span></h1>
        <p class="hero-copy">Uma plataforma em construção para apoiar a rotina esportiva e aproximar atletas, equipes e comissão.</p>
        <a class="button" href="#sobre">Conheça o projeto <span aria-hidden="true">↓</span></a>
        <div class="hero-orbit orbit-one" aria-hidden="true"></div>
        <div class="hero-orbit orbit-two" aria-hidden="true"></div>
        <div class="hero-ball" aria-hidden="true"><span>FLAG</span></div>
    </div>
</section>
<section class="about" id="sobre" aria-labelledby="about-title">
    <div class="container about-inner">
        <p class="eyebrow"><span class="eyebrow-line"></span> EM DESENVOLVIMENTO</p>
        <h2 id="about-title">Feito para a dinâmica do esporte.</h2>
        <p>O Sistema FLAG está sendo construído por etapas. Os recursos de gestão serão apresentados conforme cada módulo ficar pronto.</p>
        <div class="status-note"><span class="status-dot" aria-hidden="true"></span> Primeira etapa da plataforma</div>
    </div>
</section>
FLAG_INSTALL_TEMPLATE_0021,
    'modules/autenticacao/Controllers/LoginController.php' => <<<'FLAG_INSTALL_TEMPLATE_0022'
<?php

declare(strict_types=1);

namespace Modules\Autenticacao\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use Throwable;

final class LoginController
{
    public function handle(array $config): void
    {
        try {
            Session::start();
            header('Cache-Control: no-store, private');
            if (Auth::user() !== null) {
                header('Location: admin/index.php', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Sessão de autenticação indisponível: ' . get_class($exception));
            http_response_code(503);
            header('Cache-Control: no-store, private');
            exit('Autenticação temporariamente indisponível. A configuração do banco precisa ser concluída.');
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD, POST');
            exit('Método não permitido.');
        }

        $message = '';
        if ($method === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                http_response_code(400);
                $message = 'Sua sessão expirou. Atualize a página e tente novamente.';
            } else {
                try {
                    $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
                    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                    if (Auth::attempt($email, $password)) {
                        header('Location: admin/index.php', true, 303);
                        exit;
                    }
                    $message = 'E-mail ou senha inválidos, ou acesso temporariamente bloqueado.';
                } catch (Throwable $exception) {
                    error_log('Falha no fluxo de autenticação: ' . get_class($exception));
                    http_response_code(503);
                    $message = 'Não foi possível entrar agora. Tente novamente mais tarde.';
                }
            }
        }

        $csrfToken = Csrf::token();
        $appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
        $escape = [View::class, 'escape'];
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'login.php';
    }
}
FLAG_INSTALL_TEMPLATE_0022,
    'modules/autenticacao/Controllers/LogoutController.php' => <<<'FLAG_INSTALL_TEMPLATE_0023'
<?php

declare(strict_types=1);

namespace Modules\Autenticacao\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;

final class LogoutController
{
    public function handle(): void
    {
        header('Cache-Control: no-store, private');
        Session::start();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método não permitido.');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('Solicitação inválida.');
        }

        Auth::logout();
        header('Location: login.php', true, 303);
        exit;
    }
}
FLAG_INSTALL_TEMPLATE_0023,
    'modules/autenticacao/Controllers/AdminController.php' => <<<'FLAG_INSTALL_TEMPLATE_0024'
<?php

declare(strict_types=1);

namespace Modules\Autenticacao\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use Throwable;

final class AdminController
{
    public function handle(array $config): void
    {
        try {
            Session::start();
            header('Cache-Control: no-store, private');
            $user = Auth::requireRole('admin');
            $csrfToken = Csrf::token();
        } catch (Throwable $exception) {
            error_log('Sessão administrativa indisponível: ' . get_class($exception));
            http_response_code(503);
            header('Cache-Control: no-store, private');
            exit('Área administrativa temporariamente indisponível.');
        }

        $escape = [View::class, 'escape'];
        $appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin.php';
    }
}
FLAG_INSTALL_TEMPLATE_0024,
    'modules/autenticacao/Views/login.php' => <<<'FLAG_INSTALL_TEMPLATE_0025'
<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Entrar | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('assets/css/app.css', $config)) ?>">
</head>
<body class="auth-page">
<main class="auth-card" id="conteudo">
    <a class="auth-brand" href="index.php"><?= $escape($appName) ?></a>
    <h1>Entrar</h1>
    <p class="auth-intro">Acesse sua área do Sistema FLAG.</p>
    <?php if ($message !== ''): ?>
        <p class="auth-message" role="alert"><?= $escape($message) ?></p>
    <?php endif; ?>
    <form method="post" action="login.php" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" maxlength="254" required>
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" autocomplete="current-password" maxlength="4096" required>
        <button type="submit">Entrar</button>
    </form>
    <a class="back-link" href="index.php">Voltar ao início</a>
</main>
</body>
</html>
FLAG_INSTALL_TEMPLATE_0025,
    'modules/autenticacao/Views/admin.php' => <<<'FLAG_INSTALL_TEMPLATE_0026'
<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Painel | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
</head>
<body class="admin-page">
<header class="admin-header">
    <a class="auth-brand" href="../index.php"><?= $escape($appName) ?></a>
    <form method="post" action="../logout.php">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <button class="button-secondary" type="submit">Sair</button>
    </form>
</header>
<main class="admin-content">
    <p class="eyebrow">ÁREA ADMINISTRATIVA</p>
    <h1>Bem-vindo, <?= $escape($user['name']) ?></h1>
    <p>Esta área está protegida por autenticação e autorização.</p>
</main>
</body>
</html>
FLAG_INSTALL_TEMPLATE_0026,
    'modules/autenticacao/Database/migrations/001_autenticacao.sql' => <<<'FLAG_INSTALL_TEMPLATE_0027'
CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    email_normalizado VARCHAR(254) GENERATED ALWAYS AS (lower(email)) STORED,
    password_hash VARCHAR(255) NOT NULL,
    perfil VARCHAR(20) NOT NULL CHECK (perfil IN ('admin', 'staff', 'atleta')),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS usuarios_email_normalizado_unique
    ON usuarios (email_normalizado);

CREATE TABLE IF NOT EXISTS autenticacao_tentativas (
    chave_hash CHAR(64) PRIMARY KEY,
    tentativas SMALLINT NOT NULL DEFAULT 0 CHECK (tentativas >= 0),
    inicio_janela TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_ate TIMESTAMPTZ NULL
);

CREATE INDEX IF NOT EXISTS autenticacao_tentativas_bloqueado_idx
    ON autenticacao_tentativas (bloqueado_ate)
    WHERE bloqueado_ate IS NOT NULL;
CREATE TABLE IF NOT EXISTS app_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    payload TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL
);

CREATE INDEX IF NOT EXISTS app_sessions_expires_at_idx ON app_sessions (expires_at);
FLAG_INSTALL_TEMPLATE_0027,
    'assets/css/app.css' => <<<'FLAG_INSTALL_TEMPLATE_0028'
:root{color-scheme:light;--ink:#1d2950;--muted:#626b7b;--paper:#fff;--blue:#2879b6;--navy:#1d2950;--red:#b50c14;--gray-100:#f3f4f6;--gray-300:#d4d8df;--line:rgba(29,41,80,.16);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--paper);color:var(--ink)}a{color:inherit}.container{width:min(1120px,calc(100% - 48px));margin-inline:auto}.skip-link{position:absolute;top:8px;left:8px;z-index:5;padding:12px 16px;background:#fff;transform:translateY(-150%)}.skip-link:focus{transform:translateY(0)}.site-header{position:relative;z-index:2;border-bottom:1px solid var(--line)}.header-inner,.brand,.main-nav,.footer-inner{display:flex;align-items:center}.header-inner{min-height:82px;justify-content:space-between;gap:24px}.brand{gap:12px;text-decoration:none}.brand-mark{display:grid;width:42px;height:42px;place-items:center;border-radius:12px 12px 12px 3px;background:var(--blue);color:#fff;font-size:25px;font-weight:850}.brand-name{font-size:15px;font-weight:800;letter-spacing:.02em}.brand-name small{display:block;margin-top:3px;color:var(--muted);font-size:9px;letter-spacing:.16em}.main-nav{gap:32px}.main-nav a{text-decoration:none;font-size:14px;font-weight:650}.main-nav a:hover,.main-nav a:focus-visible{color:var(--blue)}.hero{position:relative;overflow:hidden;min-height:560px;background:var(--navy);color:#fff}.hero-inner{position:relative;display:flex;min-height:560px;flex-direction:column;align-items:flex-start;justify-content:center;padding-block:72px}.eyebrow{display:flex;align-items:center;gap:10px;margin:0 0 24px;color:var(--blue);font-size:11px;font-weight:800;letter-spacing:.16em}.hero .eyebrow{color:#fff}.eyebrow-line{width:26px;height:2px;background:currentColor}.hero h1{z-index:1;margin:0;font-size:clamp(56px,8vw,102px);line-height:.98;letter-spacing:-.065em}.hero h1 span{color:#7fc5f2}.hero-copy{z-index:1;max-width:480px;margin:25px 0 30px;color:rgba(255,255,255,.78);font-size:17px;line-height:1.7}.button{z-index:1;display:inline-flex;min-height:48px;align-items:center;gap:18px;padding:0 20px;border-radius:5px;background:var(--blue);color:#fff;font-size:14px;font-weight:800;text-decoration:none;transition:transform .18s ease,background .18s ease}.button:hover{transform:translateY(-2px);background:#7fc5f2;color:var(--navy)}.button:focus-visible,a:focus-visible{outline:3px solid var(--red);outline-offset:4px}.hero-orbit{position:absolute;right:-115px;border:1px solid rgba(255,255,255,.13);border-radius:50%}.orbit-one{width:590px;height:590px}.orbit-two{right:-35px;width:430px;height:430px}.hero-ball{position:absolute;right:12%;top:50%;display:grid;width:116px;height:116px;place-items:center;border:2px solid rgba(255,255,255,.8);border-radius:50%;background:#7fc5f2;color:var(--navy);font-size:14px;font-weight:900;box-shadow:0 0 0 18px rgba(40,121,182,.18),0 20px 60px rgba(0,0,0,.16);transform:translateY(-50%) rotate(-15deg)}.about{padding-block:100px}.about-inner{max-width:760px}.about h2{max-width:620px;margin:0 0 20px;font-size:clamp(34px,5vw,54px);line-height:1.08;letter-spacing:-.045em}.about p:not(.eyebrow){max-width:650px;color:var(--muted);font-size:17px;line-height:1.8}.status-note{display:inline-flex;align-items:center;gap:10px;margin-top:18px;padding:12px 15px;border:1px solid var(--line);border-radius:4px;background:var(--gray-100);color:var(--muted);font-size:12px;font-weight:700}.status-dot{width:8px;height:8px;border-radius:50%;background:var(--blue)}.site-footer{border-top:1px solid var(--line)}.footer-inner{min-height:76px;justify-content:space-between;gap:20px;color:var(--muted);font-size:12px}.footer-inner span:first-child{color:var(--ink);font-weight:800}@media(max-width:700px){.container{width:min(100% - 36px,540px)}.header-inner{min-height:72px}.main-nav{gap:18px}.main-nav a{font-size:13px}.hero,.hero-inner{min-height:530px}.hero-inner{padding-block:64px}.hero h1{font-size:clamp(55px,15vw,82px)}.hero-copy{max-width:390px;font-size:15px}.hero-ball{right:10%;top:auto;bottom:28px;width:72px;height:72px;font-size:10px;transform:rotate(-15deg)}.orbit-one{right:-390px}.orbit-two{right:-310px}.about{padding-block:72px}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*::before,*::after{transition-duration:.01ms!important}}
.auth-page{display:grid;min-height:100vh;place-items:center;padding:24px;background:var(--navy)}.auth-card{width:min(100%,440px);padding:clamp(24px,6vw,48px);border-radius:8px;background:#fff;box-shadow:0 20px 70px rgba(0,0,0,.18)}.auth-brand{display:inline-block;color:var(--navy);font-size:15px;font-weight:850;text-decoration:none}.auth-card h1{margin:34px 0 8px;font-size:clamp(32px,8vw,42px);letter-spacing:-.04em}.auth-intro{margin:0 0 28px;color:var(--muted);line-height:1.6}.auth-card form{display:grid;gap:10px}.auth-card label{margin-top:10px;font-size:14px;font-weight:700}.auth-card input{width:100%;min-height:48px;padding:10px 12px;border:1px solid var(--gray-300);border-radius:4px;background:#fff;color:var(--ink);font:inherit}.auth-card input:focus-visible{outline:3px solid rgba(40,121,182,.22);border-color:var(--blue)}.auth-card button,.button-secondary{min-height:48px;padding:0 18px;border:0;border-radius:4px;background:var(--blue);color:#fff;font:inherit;font-weight:750;cursor:pointer}.auth-card button{margin-top:16px}.auth-card button:hover,.button-secondary:hover{background:var(--navy)}.auth-message{padding:12px;border-left:4px solid var(--red);background:#fff1f1;color:#7a1117;font-size:14px;line-height:1.5}.back-link{display:inline-block;margin-top:24px;color:var(--navy);font-size:14px;font-weight:650}.admin-page{min-height:100vh;background:var(--paper)}.admin-header{display:flex;min-height:76px;align-items:center;justify-content:space-between;gap:16px;padding:12px max(24px,calc((100vw - 1120px)/2));border-bottom:1px solid var(--line);background:#fff}.admin-header form{margin:0}.button-secondary{min-height:42px}.admin-content{width:min(1120px,calc(100% - 48px));margin:64px auto}.admin-content h1{font-size:clamp(32px,6vw,54px);letter-spacing:-.04em}.admin-content>p:last-child{color:var(--muted);line-height:1.7}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
FLAG_INSTALL_TEMPLATE_0028,
];

$templates['assets/css/app.css'] = str_replace(
    '.admin-content>p:last-child{color:var(--muted);line-height:1.7}',
    '.admin-content>p:last-child{color:var(--muted);line-height:1.7}.main-nav .nav-login,.main-nav .nav-login:hover{color:#fff}@media(max-width:700px){.main-nav{gap:12px}.main-nav a:first-child{display:none}.nav-login{min-height:42px;padding:0 14px}}',
    $templates['assets/css/app.css']
);
$templates['assets/css/app.css'] .= '.hero h1 span{color:#7fc5f2}.button,.main-nav .nav-login{color:#fff}.button:hover,.main-nav .nav-login:hover,.hero-ball{color:var(--navy)}';

function flagInstallInsideRoot(string $path): bool
{
    global $root;
    $resolved = realpath($path);
    if ($resolved === false) {
        return false;
    }
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (DIRECTORY_SEPARATOR === '\\') {
        return str_starts_with(strtolower($resolved . DIRECTORY_SEPARATOR), strtolower($prefix))
            || strtolower($resolved) === strtolower($root);
    }
    return str_starts_with($resolved . DIRECTORY_SEPARATOR, $prefix) || $resolved === $root;
}

function flagInstallDirectory(string $relativePath): void
{
    global $root;
    if ($relativePath === '' || str_contains($relativePath, '..') || str_contains($relativePath, ':') || str_starts_with($relativePath, '/')) {
        throw new RuntimeException('Caminho de pasta inválido.');
    }
    $current = $root;
    foreach (explode('/', str_replace('\\', '/', $relativePath)) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new RuntimeException('Caminho de pasta inválido.');
        }
        $current .= DIRECTORY_SEPARATOR . $segment;
        if (is_link($current)) {
            throw new RuntimeException('Link simbólico no caminho de pasta; operação cancelada.');
        }
        if (!file_exists($current) && !mkdir($current, 0755)) {
            throw new RuntimeException('Não foi possível criar uma pasta prevista pelo projeto.');
        }
        if (!is_dir($current) || !flagInstallInsideRoot($current)) {
            throw new RuntimeException('Pasta de destino fora do projeto ou inválida.');
        }
    }
}

function flagInstallCreateFile(string $relativePath, string $contents): void
{
    global $root;
    if ($relativePath === '' || str_contains($relativePath, '..') || str_contains($relativePath, ':') || str_starts_with($relativePath, '/')) {
        throw new RuntimeException('Caminho de arquivo inválido.');
    }
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $parent = realpath(dirname($path));
    if ($parent === false || !flagInstallInsideRoot($parent)) {
        throw new RuntimeException('Pasta de destino inválida.');
    }
    if (is_link($path)) {
        throw new RuntimeException('Link simbólico no arquivo de destino; arquivo preservado.');
    }
    if (file_exists($path)) {
        echo '[SKIP] Preservado: ' . $relativePath . PHP_EOL;
        return;
    }
    $handle = @fopen($path, 'x');
    if ($handle === false) {
        if (file_exists($path) || is_link($path)) {
            echo '[SKIP] Preservado: ' . $relativePath . PHP_EOL;
            return;
        }
        throw new RuntimeException('Não foi possível criar: ' . $relativePath);
    }
    try {
        if (fwrite($handle, $contents) !== strlen($contents) || !fflush($handle)) {
            throw new RuntimeException('Falha ao gravar: ' . $relativePath);
        }
    } catch (Throwable $exception) {
        fclose($handle);
        @unlink($path);
        throw $exception;
    }
    fclose($handle);
    echo '[OK] Criado: ' . $relativePath . PHP_EOL;
}
foreach ($directories as $directory) {
    flagInstallDirectory($directory);
}
foreach ($templates as $relativePath => $contents) {
    flagInstallCreateFile($relativePath, $contents . PHP_EOL);
}
foreach (['storage/logs/.gitkeep', 'storage/uploads/.gitkeep', 'storage/cache/.gitkeep'] as $relativePath) {
    flagInstallCreateFile($relativePath, '');
}

if (in_array('--migrate-local-config', $arguments, true)) {
    $privatePath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'conf.php';
    $legacyConfigPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    $legacyEnvPath = $root . DIRECTORY_SEPARATOR . '.env.local';
    if (file_exists($privatePath) || is_link($privatePath)) {
        fwrite(STDERR, '[ERRO] config/conf.php já existe; nenhum arquivo local foi alterado.' . PHP_EOL);
        exit(1);
    }
    $legacyConfig = [];
    if (is_file($legacyConfigPath) && !is_link($legacyConfigPath)) {
        $loaded = require $legacyConfigPath;
        if (!is_array($loaded)) {
            fwrite(STDERR, '[ERRO] A configuração antiga é inválida; migração cancelada.' . PHP_EOL);
            exit(1);
        }
        $legacyConfig = $loaded;
    }
    $url = null;
    if (is_file($legacyEnvPath) && !is_link($legacyEnvPath)) {
        $envContents = file_get_contents($legacyEnvPath);
        if (is_string($envContents) && preg_match('/(?m)^\s*DATABASE_URL\s*=\s*(.*?)\s*$/', $envContents, $matches) === 1) {
            $url = trim($matches[1]);
            if (strlen($url) >= 2 && (($url[0] === '"' && str_ends_with($url, '"')) || ($url[0] === "'" && str_ends_with($url, "'")))) {
                $url = substr($url, 1, -1);
            }
        }
    }
    if (!is_string($url) || $url === '') {
        $url = getenv('DATABASE_URL') ?: null;
    }
    if (is_string($url)) {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)
            || empty($parts['host']) || !isset($parts['user']) || !array_key_exists('pass', $parts)
            || preg_match('/[\r\n\x00]/', $url) === 1) {
            fwrite(STDERR, '[ERRO] A URL local não passou na validação; arquivos antigos foram preservados.' . PHP_EOL);
            exit(1);
        }
    }
    $privateConfig = [
        'app' => array_replace(['name' => 'Sistema FLAG', 'version' => '0.1.0', 'environment' => 'development', 'debug' => false], is_array($legacyConfig['app'] ?? null) ? $legacyConfig['app'] : []),
        'assets' => array_replace(['version' => '1.0.0'], is_array($legacyConfig['assets'] ?? null) ? $legacyConfig['assets'] : []),
        'database' => array_replace(['url' => $url, 'url_environment_variable' => 'DATABASE_URL', 'sslmode' => 'require', 'connect_timeout' => 20], is_array($legacyConfig['database'] ?? null) ? $legacyConfig['database'] : []),
    ];
    if (is_string($url)) {
        $privateConfig['database']['url'] = $url;
    }
    $handle = @fopen($privatePath, 'x');
    if ($handle === false) {
        fwrite(STDERR, '[ERRO] Não foi possível criar config/conf.php.' . PHP_EOL);
        exit(1);
    }
    $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($privateConfig, true) . ";\n";
    $written = fwrite($handle, $contents);
    $flushed = fflush($handle);
    fclose($handle);
    if ($written !== strlen($contents) || !$flushed) {
        @unlink($privatePath);
        fwrite(STDERR, '[ERRO] Falha ao gravar config/conf.php; arquivos antigos foram preservados.' . PHP_EOL);
        exit(1);
    }
    if (DIRECTORY_SEPARATOR !== '\\') {
        @chmod($privatePath, 0600);
    }
    $verified = require $privatePath;
    if (!is_array($verified) || !is_array($verified['database'] ?? null)) {
        @unlink($privatePath);
        fwrite(STDERR, '[ERRO] config/conf.php não passou na validação; arquivos antigos foram preservados.' . PHP_EOL);
        exit(1);
    }
    if (is_file($legacyEnvPath) && !is_link($legacyEnvPath)) {
        @unlink($legacyEnvPath);
    }
    if (is_file($legacyConfigPath) && !is_link($legacyConfigPath)) {
        @unlink($legacyConfigPath);
    }
    echo '[OK] Configuração local centralizada em config/conf.php; arquivos antigos removidos após validação.' . PHP_EOL;
}
if (in_array('--migrate-auth', $arguments, true)) {
    require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
    $migrationPath = $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'autenticacao'
        . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '001_autenticacao.sql';
    $resolvedMigration = realpath($migrationPath);
    $migrationPrefix = $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'autenticacao' . DIRECTORY_SEPARATOR;
    if ($resolvedMigration === false || is_link($migrationPath)
        || !str_starts_with($resolvedMigration, $migrationPrefix) || !is_file($resolvedMigration)) {
        fwrite(STDERR, '[ERRO] Migration de autenticação não encontrada dentro do projeto.' . PHP_EOL);
        exit(1);
    }
    $sql = file_get_contents($resolvedMigration);
    if (!is_string($sql) || trim($sql) === '') {
        fwrite(STDERR, '[ERRO] Migration de autenticação vazia ou ilegível.' . PHP_EOL);
        exit(1);
    }
    try {
        $db = \App\Core\Database::connection();
        $db->beginTransaction();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $db->exec($statement);
        }
        $db->commit();
        echo '[OK] Estrutura de autenticação criada/validada no banco selecionado.' . PHP_EOL;
    } catch (Throwable $exception) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        fwrite(STDERR, '[ERRO] A migration não foi aplicada. Confira o banco e a migration; detalhes de conexão foram ocultados.' . PHP_EOL);
        exit(1);
    }
}
if (in_array('--create-admin', $arguments, true)) {
    require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
    $isProduction = strtolower((string) (getenv('APP_ENV') ?: '')) === 'production'
        || getenv('VERCEL') === '1';
    $password = getenv('FLAG_ADMIN_PASSWORD');
    $usingDefaultCredentials = !is_string($password) || $password === '';
    if ($usingDefaultCredentials) {
        if ($isProduction) {
            fwrite(STDERR, '[ERRO] Credenciais padrão são bloqueadas em produção. Defina FLAG_ADMIN_PASSWORD com uma senha temporária forte.' . PHP_EOL);
            exit(1);
        }
        $password = 'RootTest2026';
        $defaultEmail = 'root@localhost.test';
        fwrite(STDOUT, '[INFO] Usando credenciais padrão locais de teste.' . PHP_EOL);
    } elseif (strlen($password) < 8 || strlen($password) > 4096) {
        fwrite(STDERR, '[ERRO] FLAG_ADMIN_PASSWORD deve ter entre 8 e 4096 caracteres.' . PHP_EOL);
        exit(1);
    }
    if ($usingDefaultCredentials) {
        $name = 'Root Teste';
        $email = $defaultEmail;
        fwrite(STDOUT, 'Criando administrador local padrão: ' . $name . ' (' . $email . ')' . PHP_EOL);
    } else {
        fwrite(STDOUT, 'Nome do administrador: ');
        $name = trim((string) fgets(STDIN));
        fwrite(STDOUT, 'E-mail do administrador: ');
        $email = strtolower(trim((string) fgets(STDIN)));
    }
    if ($name === '' || strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
        putenv('FLAG_ADMIN_PASSWORD');
        fwrite(STDERR, '[ERRO] Nome ou e-mail inválido.' . PHP_EOL);
        exit(1);
    }
    try {
        $statement = \App\Core\Database::connection()->prepare(
            'INSERT INTO usuarios (nome, email, password_hash, perfil, ativo)
             VALUES (:name, :email, :hash, :role, TRUE)
             ON CONFLICT (email_normalizado) DO NOTHING'
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
        ]);
        unset($password);
        putenv('FLAG_ADMIN_PASSWORD');
        if ($statement->rowCount() === 0) {
            echo '[INFO] Já existe uma conta com esse e-mail; nenhuma senha ou cadastro existente foi alterado.' . PHP_EOL;
        } else {
            echo $usingDefaultCredentials
                ? '[OK] Administrador local de teste criado (' . $email . '). Senha padrão: RootTest2026. Troque-a antes de qualquer uso fora do ambiente local.' . PHP_EOL
                : '[OK] Administrador criado. O valor da senha não foi exibido nem gravado em arquivo.' . PHP_EOL;
        }
    } catch (Throwable $exception) {
        unset($password);
        putenv('FLAG_ADMIN_PASSWORD');
        fwrite(STDERR, '[ERRO] Não foi possível criar o administrador. Confirme se a migration foi aplicada e se o e-mail já não existe.' . PHP_EOL);
        exit(1);
    }
}
if (in_array('--check-db', $arguments, true)) {
    try {
        require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
        $result = \App\Core\Database::connection()->query('SELECT 1')->fetchColumn();
        if ((string) $result !== '1') {
            throw new RuntimeException('Resultado inesperado.');
        }
        echo '[OK] Conexão PostgreSQL validada com SELECT 1.' . PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(STDERR, '[ERRO] Não foi possível validar a conexão PostgreSQL. Confira a configuração local e a disponibilidade do banco; detalhes sensíveis foram ocultados.' . PHP_EOL);
        exit(1);
    }
}

echo PHP_EOL . '[OK] Instalador único concluído. Arquivos existentes foram preservados.' . PHP_EOL;
echo 'Opções adicionais: --migrate-local-config, --migrate-auth, --create-admin e --check-db.' . PHP_EOL;
