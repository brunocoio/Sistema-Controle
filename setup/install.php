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
$knownOptions = ['--migrate-local-config', '--migrate-auth', '--migrate-athletes', '--create-admin', '--check-db', '--help'];
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
    echo "php setup/install.php --migrate-athletes Aplica a migration aditiva de atletas ao banco selecionado\n";
    echo "php setup/install.php --create-admin   Cria administrador inicial; requer senha no ambiente\n";
    echo "php setup/install.php --check-db       Testa o banco local com SELECT 1\n";
    exit(0);
}

$directories = [
    'admin', 'app', 'app/Core', 'app/Core/Views', 'app/Core/Views/Layout',
    'app/Core/Views/Pages', 'app/Core/Views/Pages/Public', 'config', 'modules', 'modules/autenticacao', 'modules/autenticacao/Controllers', 'modules/autenticacao/Views', 'modules/autenticacao/Database', 'modules/autenticacao/Database/migrations', 'modules/atletas', 'modules/atletas/Controllers', 'modules/atletas/Models', 'modules/atletas/Views', 'modules/atletas/Assets', 'modules/atletas/Database', 'modules/atletas/Database/migrations',
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

RUN install-php-extensions pdo_pgsql gd mbstring

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
        'version' => '1.0.4',
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

        // The homepage is served by index.php, so both / and /index.php
        // must resolve to the same route in Apache/Laragon and Vercel.
        if (preg_match('#/index\\.php\\z#i', $path) === 1) {
            $path = substr($path, 0, -strlen('/index.php'));
            if ($path === '') {
                $path = '/';
            }
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
        } elseif (str_starts_with($path, '../modules/')) {
            $prefix = '../';
            $path = substr($path, 3);
        }
        $isGlobalAsset = preg_match('#\Aassets/[A-Za-z0-9._/-]+\z#D', $path) === 1;
        $isModuleAsset = preg_match('#\Amodules/[a-z0-9-]+/Assets/[A-Za-z0-9._/-]+\z#D', $path) === 1;
        if (!$isGlobalAsset && !$isModuleAsset) {
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
    <section class="admin-card">
        <h2>Gestão esportiva</h2>
        <p>Cadastre e mantenha os dados da equipe.</p>
        <a class="button" href="../admin/atletas.php">Gerenciar atletas <span aria-hidden="true">→</span></a>
    </section>
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

$templates += [
    'admin/atletas.php' => <<<'FLAG_SETUP_ATLETAS_ENTRY'
<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'atletas'
    . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'AthleteModel.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'atletas'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'AthleteController.php';

(new \Modules\Atletas\Controllers\AthleteController())->handle($config);
FLAG_SETUP_ATLETAS_ENTRY,
    'modules/atletas/Models/AthleteModel.php' => <<<'FLAG_SETUP_ATLETAS_MODEL'
<?php

declare(strict_types=1);

namespace Modules\Atletas\Models;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class AthleteModel
{
    public function list(string $search, bool $activeOnly, int $limit, int $offset): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, nome, apelido, genero, data_nascimento, ativo, (foto IS NOT NULL) AS tem_foto
             FROM atletas WHERE (:active = FALSE OR ativo = TRUE)
             AND (:search = \'\' OR nome ILIKE :pattern_name OR apelido ILIKE :pattern_alias)
             ORDER BY ativo DESC, lower(nome), id LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('active', $activeOnly, PDO::PARAM_BOOL);
        $statement->bindValue('search', $search, PDO::PARAM_STR);
        $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $statement->bindValue('pattern_name', $pattern, PDO::PARAM_STR);
        $statement->bindValue('pattern_alias', $pattern, PDO::PARAM_STR);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** Category/side/position catalog. Each position belongs to exactly one category and side. */
    public function categoryCatalog(bool $activeOnly = true): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.id AS categoria_id, c.modalidade, c.genero, c.ativo AS categoria_ativa,
                    s.id AS side_id, s.codigo AS side_codigo, s.nome AS side_nome,
                    p.id AS posicao_id, p.nome AS posicao_nome, p.ativo AS posicao_ativa
             FROM categorias c
             JOIN categoria_sides s ON s.categoria_id = c.id
             LEFT JOIN categoria_posicoes p ON p.categoria_id = c.id AND p.side_id = s.id AND p.ativo = TRUE
             WHERE (:active = FALSE OR c.ativo = TRUE)
             ORDER BY lower(c.modalidade), c.genero, s.id, lower(p.nome)'
        );
        $statement->execute(['active' => $activeOnly]);
        return $statement->fetchAll();
    }

    public function assignmentsForAthlete(int $athleteId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT ac.categoria_id, ac.genero, ac.numero_camisa, cp.id AS posicao_id
             FROM atleta_categorias ac
             LEFT JOIN atleta_categoria_posicoes acp ON acp.atleta_categoria_id = ac.id
             LEFT JOIN categoria_posicoes cp ON cp.id = acp.posicao_id AND cp.ativo = TRUE
             WHERE ac.atleta_id = :athlete ORDER BY ac.categoria_id, cp.id'
        );
        $statement->execute(['athlete' => $athleteId]);
        $assignments = [];
        foreach ($statement->fetchAll() as $row) {
            $categoryId = (int) $row['categoria_id'];
            $assignments[$categoryId] ??= [
                'genero' => (string) $row['genero'],
                'numero_camisa' => $row['numero_camisa'] === null ? null : (int) $row['numero_camisa'],
                'posicoes' => [],
                'cargos' => [],
            ];
            if ($row['posicao_id'] !== null) $assignments[$categoryId]['posicoes'][] = (int) $row['posicao_id'];
        }
        return $assignments;
    }

    public function positionSummary(array $athleteIds): array
    {
        if ($athleteIds === []) return [];
        $params = [];
        $holders = [];
        foreach (array_values(array_unique(array_map('intval', $athleteIds))) as $index => $id) {
            $key = 'athlete' . $index;
            $holders[] = ':' . $key;
            $params[$key] = $id;
        }
        $sql = 'SELECT ac.atleta_id, ac.categoria_id, c.modalidade, c.genero,
                       s.codigo AS side_codigo, s.nome AS side_nome,
                       array_to_string(array_agg(DISTINCT cp.nome ORDER BY cp.nome), chr(44) || chr(32)) AS posicoes
                FROM atleta_categorias ac
                JOIN categorias c ON c.id = ac.categoria_id
                JOIN atleta_categoria_posicoes acp ON acp.atleta_categoria_id = ac.id
                JOIN categoria_posicoes cp ON cp.categoria_id = ac.categoria_id AND cp.id = acp.posicao_id AND cp.ativo = TRUE
                JOIN categoria_sides s ON s.categoria_id = c.id AND s.id = cp.side_id
                WHERE ac.atleta_id IN (' . implode(', ', $holders) . ')
                GROUP BY ac.atleta_id, ac.categoria_id, c.modalidade, c.genero, s.id, s.codigo, s.nome
                ORDER BY ac.atleta_id, lower(c.modalidade), c.genero, s.id';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $summary = [];
        foreach ($statement->fetchAll() as $row) $summary[(int) $row['atleta_id']][] = $row;
        return $summary;
    }

    public function categorySummary(array $athleteIds): array
    {
        if ($athleteIds === []) return [];
        $params = [];
        $holders = [];
        foreach (array_values(array_unique(array_map('intval', $athleteIds))) as $index => $id) {
            $key = 'athlete' . $index;
            $holders[] = ':' . $key;
            $params[$key] = $id;
        }
        $sql = 'SELECT membership.atleta_id, c.id AS categoria_id, c.modalidade, c.genero,
                       ac.numero_camisa,
                       array_to_string(array_agg(DISTINCT cg.nome ORDER BY cg.nome), chr(44) || chr(32)) AS cargos
                FROM (
                    SELECT atleta_id, categoria_id FROM atleta_categorias
                    UNION
                    SELECT atleta_id, categoria_id FROM atleta_cargos_por_categoria
                ) membership
                JOIN categorias c ON c.id = membership.categoria_id
                LEFT JOIN atleta_categorias ac ON ac.atleta_id = membership.atleta_id AND ac.categoria_id = membership.categoria_id
                LEFT JOIN atleta_cargos_por_categoria acc ON acc.atleta_id = membership.atleta_id AND acc.categoria_id = membership.categoria_id
                LEFT JOIN cargos cg ON cg.id = acc.cargo_id AND cg.ativo = TRUE
                WHERE membership.atleta_id IN (' . implode(', ', $holders) . ')
                GROUP BY membership.atleta_id, c.id, c.modalidade, c.genero, ac.numero_camisa
                ORDER BY membership.atleta_id, lower(c.modalidade), c.genero';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $summary = [];
        foreach ($statement->fetchAll() as $row) $summary[(int) $row['atleta_id']][] = $row;
        return $summary;
    }

    public function rolesForAthlete(int $athleteId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT acc.categoria_id, acc.cargo_id
             FROM atleta_cargos_por_categoria acc
             JOIN cargos c ON c.id = acc.cargo_id AND c.ativo = TRUE
             WHERE acc.atleta_id = :athlete ORDER BY acc.categoria_id, lower(c.nome)'
        );
        $statement->execute(['athlete' => $athleteId]);
        $roles = [];
        foreach ($statement->fetchAll() as $row) $roles[(int) $row['categoria_id']][] = (int) $row['cargo_id'];
        return $roles;
    }

    public function roleCatalog(bool $activeOnly = true): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, nome FROM cargos WHERE (:active = FALSE OR ativo = TRUE) ORDER BY lower(nome), id'
        );
        $statement->execute(['active' => $activeOnly]);
        return $statement->fetchAll();
    }

    /** Returns unassigned shirt numbers in a category, preserving this athlete's number while editing. */
    public function availableNumbers(int $categoryId, ?int $athleteId = null): array
    {
        $ownFilter = $athleteId === null ? '' : ' AND assigned.atleta_id <> :athlete';
        $statement = Database::connection()->prepare(
            'SELECT available.number::integer
             FROM generate_series(0, 99) AS available(number)
             WHERE NOT EXISTS (
                 SELECT 1 FROM atleta_categorias assigned
                 WHERE assigned.categoria_id = :category
                   AND assigned.numero_camisa = available.number' . $ownFilter . '
             ) ORDER BY available.number'
        );
        $params = ['category' => $categoryId];
        if ($athleteId !== null) $params['athlete'] = $athleteId;
        $statement->execute($params);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT *, (foto IS NOT NULL) AS tem_foto FROM atletas WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function save(array $data, int $userId, ?string $photoBase64, bool $removePhoto, array $assignments): int
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $id = (int) ($data['id'] ?? 0);
            if ($id > 0) {
                // Remove category links before changing gender; composite FKs prevent cross-gender playing assignments.
                $delete = $db->prepare('DELETE FROM atleta_categorias WHERE atleta_id = :id');
                $delete->execute(['id' => $id]);
                $delete = $db->prepare('DELETE FROM atleta_cargos_por_categoria WHERE atleta_id = :id');
                $delete->execute(['id' => $id]);
                $values = $this->athleteValues($data, $userId);
                $values['id'] = $id;
                $photoSql = $photoBase64 !== null ? "decode(:photo, 'base64')" : ($removePhoto ? 'NULL' : 'foto');
                if ($photoBase64 !== null) $values['photo'] = $photoBase64;
                $statement = $db->prepare("UPDATE atletas SET nome=:nome, apelido=:apelido, genero=:genero,
                    data_nascimento=:data_nascimento, celular=:celular, rg=:rg, cpf=:cpf, email=:email,
                    contato_emergencia_nome=:contato_emergencia_nome, contato_emergencia_celular=:contato_emergencia_celular,
                    contato_emergencia_parentesco=:contato_emergencia_parentesco, tipo_sanguineo=:tipo_sanguineo,
                    alergias=:alergias, foto=$photoSql, ativo=:ativo, atualizado_por=:atualizado_por,
                    atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
                $statement->execute($values);
                if ($statement->rowCount() === 0 && $this->find($id) === null) throw new RuntimeException('Cadastro de atleta não encontrado.');
            } else {
                $values = $this->athleteValues($data, $userId);
                $values['criado_por'] = $userId;
                $photoSql = $photoBase64 === null ? 'NULL' : "decode(:photo, 'base64')";
                if ($photoBase64 !== null) $values['photo'] = $photoBase64;
                $statement = $db->prepare("INSERT INTO atletas (nome, apelido, genero, data_nascimento, celular,
                    rg, cpf, email, contato_emergencia_nome, contato_emergencia_celular, contato_emergencia_parentesco,
                    tipo_sanguineo, alergias, foto, ativo, criado_por, atualizado_por)
                    VALUES (:nome, :apelido, :genero, :data_nascimento, :celular,
                    :rg, :cpf, :email, :contato_emergencia_nome, :contato_emergencia_celular, :contato_emergencia_parentesco,
                    :tipo_sanguineo, :alergias, $photoSql, :ativo, :criado_por, :atualizado_por) RETURNING id");
                $statement->execute($values);
                $id = (int) $statement->fetchColumn();
            }
            $this->replaceAssignments($id, (string) $data['genero'], $assignments);
            $db->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    private function athleteValues(array $data, int $userId): array
    {
        return [
            'nome' => $data['nome'], 'apelido' => $data['apelido'],
            'genero' => $data['genero'], 'data_nascimento' => $data['data_nascimento'],
            'celular' => $data['celular'], 'rg' => $data['rg'], 'cpf' => $data['cpf'], 'email' => $data['email'],
            'contato_emergencia_nome' => $data['contato_emergencia_nome'],
            'contato_emergencia_celular' => $data['contato_emergencia_celular'],
            'contato_emergencia_parentesco' => $data['contato_emergencia_parentesco'],
            'tipo_sanguineo' => $data['tipo_sanguineo'], 'alergias' => $data['alergias'],
            'ativo' => $data['ativo'], 'atualizado_por' => $userId,
        ];
    }

    private function replaceAssignments(int $athleteId, string $gender, array $assignments): void
    {
        $db = Database::connection();
        $categoryQuery = $db->prepare('SELECT id, genero FROM categorias WHERE id = :id AND ativo = TRUE');
        $insertCategory = $db->prepare('INSERT INTO atleta_categorias (atleta_id, categoria_id, genero, numero_camisa)
            VALUES (:athlete, :category, :gender, :jersey) RETURNING id');
        $insertPosition = $db->prepare('INSERT INTO atleta_categoria_posicoes (atleta_categoria_id, categoria_id, posicao_id)
            SELECT :athlete_category, :category, id FROM categoria_posicoes WHERE categoria_id = :category_check AND id = :position AND ativo = TRUE');
        $insertRole = $db->prepare('INSERT INTO atleta_cargos_por_categoria (atleta_id, categoria_id, cargo_id)
            SELECT :athlete, :category, id FROM cargos WHERE id = :cargo AND ativo = TRUE');
        $playerRoleIds = $db->query("SELECT id FROM cargos WHERE ativo = TRUE AND lower(nome) IN ('atleta', 'jogador', 'jogadora')")
            ->fetchAll(PDO::FETCH_COLUMN);
        $playerRoleIds = array_map('intval', $playerRoleIds);
        foreach ($assignments as $categoryId => $assignment) {
            $categoryId = (int) $categoryId;
            $categoryQuery->execute(['id' => $categoryId]);
            $category = $categoryQuery->fetch();
            if (!is_array($category)) throw new RuntimeException('Uma categoria escolhida não está disponível.');
            $jersey = $assignment['numero_camisa'] ?? null;
            if ($jersey !== null && (!is_int($jersey) || $jersey < 0 || $jersey > 99)) {
                throw new RuntimeException('Número de camisa inválido para a categoria selecionada.');
            }
            $roleIds = array_values(array_unique(array_map('intval', $assignment['cargos'] ?? [])));
            $positionIds = array_values(array_unique(array_map('intval', $assignment['posicoes'] ?? [])));
            $isPlayer = $jersey !== null || $positionIds !== [] || array_intersect($roleIds, $playerRoleIds) !== [];
            $sameGender = (string) $category['genero'] === $gender;
            if (!$sameGender && $isPlayer) {
                throw new RuntimeException('Camisa e posições só podem ser atribuídas em uma categoria compatível com o gênero esportivo do atleta. Cargos técnicos podem ser vinculados a qualquer categoria.');
            }
            $athleteCategoryId = null;
            if ($sameGender) {
                $insertCategory->execute([
                    'athlete' => $athleteId, 'category' => $categoryId, 'gender' => $category['genero'], 'jersey' => $jersey,
                ]);
                $athleteCategoryId = (int) $insertCategory->fetchColumn();
            }
            foreach ($roleIds as $roleId) {
                $insertRole->execute(['athlete' => $athleteId, 'category' => $categoryId, 'cargo' => $roleId]);
                if ($insertRole->rowCount() !== 1) throw new RuntimeException('Um cargo escolhido não está disponível.');
            }
            foreach ($positionIds as $positionId) {
                if ($athleteCategoryId === null) throw new RuntimeException('Posições exigem uma categoria compatível com o gênero esportivo do atleta.');
                $insertPosition->execute([
                    'athlete_category' => $athleteCategoryId, 'category' => $categoryId,
                    'category_check' => $categoryId, 'position' => $positionId,
                ]);
                if ($insertPosition->rowCount() !== 1) throw new RuntimeException('Uma posição escolhida não pertence à categoria correspondente.');
            }
        }
    }

    public function addCategory(string $modality, string $gender): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $insert = $db->prepare('INSERT INTO categorias (modalidade, genero) VALUES (:name, :gender) ON CONFLICT DO NOTHING RETURNING id');
            $insert->execute(['name' => $modality, 'gender' => $gender]);
            $id = $insert->fetchColumn();
            if ($id === false) throw new RuntimeException('Essa categoria já existe.');
            $sides = $db->prepare("INSERT INTO categoria_sides (categoria_id, codigo, nome)
                VALUES (:id_attack, 'ataque', 'Ataque'), (:id_defense, 'defesa', 'Defesa'), (:id_special, 'time_especial', 'Time especial')");
            $sides->execute(['id_attack' => $id, 'id_defense' => $id, 'id_special' => $id]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function addPosition(int $categoryId, string $sideCode, string $name): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO categoria_posicoes (categoria_id, side_id, nome)
             SELECT categoria_id, id, :name FROM categoria_sides WHERE categoria_id = :category AND codigo = :side'
        );
        $statement->execute(['name' => $name, 'category' => $categoryId, 'side' => $sideCode]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Categoria ou side não encontrado.');
    }

    public function addRole(string $name): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO cargos (nome) VALUES (:name) ON CONFLICT DO NOTHING RETURNING id'
        );
        $statement->execute(['name' => $name]);
        if ($statement->fetchColumn() === false) throw new RuntimeException('Esse cargo já está cadastrado.');
    }

    public function toggle(int $id, int $userId): void
    {
        $statement = Database::connection()->prepare('UPDATE atletas SET ativo = NOT ativo, atualizado_por = :user, atualizado_em = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $id, 'user' => $userId]);
    }

    public function photo(int $id): ?string
    {
        $statement = Database::connection()->prepare("SELECT encode(foto, 'base64') FROM atletas WHERE id = :id AND foto IS NOT NULL");
        $statement->execute(['id' => $id]);
        $value = $statement->fetchColumn();
        return is_string($value) ? base64_decode($value, true) ?: null : null;
    }
}
FLAG_SETUP_ATLETAS_MODEL,
    'modules/atletas/Controllers/AthleteController.php' => <<<'FLAG_SETUP_ATLETAS_CONTROLLER'
<?php

declare(strict_types=1);

namespace Modules\Atletas\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use Modules\Atletas\Models\AthleteModel;
use PDOException;
use RuntimeException;
use Throwable;

final class AthleteController
{
    private const MAX_UPLOAD = 5_242_880;
    private const BLOOD_TYPES = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public function handle(array $config): void
    {
        header('Cache-Control: no-store, private');
        try {
            Session::start();
            $user = Auth::requireRole('admin');
            $model = new AthleteModel();
            $actionValue = $_GET['action'] ?? 'list';
            $action = is_string($actionValue) ? $actionValue : '';
            if ($action === 'photo') {
                $this->photo($model, $this->id($_GET['id'] ?? null));
                return;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->post($model, (int) $user['id'], $config);
                return;
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                header('Allow: GET, POST');
                exit('Método não permitido.');
            }
            if ($action === 'categories') {
                $this->categoriesPage($model, $config, null);
                return;
            }
            if ($action === 'new' || $action === 'edit') {
                $athlete = $action === 'edit' ? $model->find($this->id($_GET['id'] ?? null)) : null;
                if ($action === 'edit' && $athlete === null) {
                    http_response_code(404);
                    exit('Cadastro não encontrado.');
                }
                $this->form($config, $athlete, [], null);
                return;
            }
            if ($action !== 'list') {
                http_response_code(404);
                exit('Página não encontrada.');
            }
            $searchValue = $_GET['q'] ?? '';
            $search = is_string($searchValue) ? trim($searchValue) : '';
            $search = mb_substr($search, 0, 100, 'UTF-8');
            $activeOnly = ($_GET['status'] ?? 'ativos') !== 'todos';
            $athletes = $model->list($search, $activeOnly, 100, 0);
            $athleteIds = array_column($athletes, 'id');
            $positionSummary = $model->positionSummary($athleteIds);
            $categorySummary = $model->categorySummary($athleteIds);
            $this->listing($config, $athletes, $search, $activeOnly, $positionSummary, $categorySummary);
        } catch (Throwable $exception) {
            if ($exception instanceof PDOException) {
                error_log('Falha de banco no módulo de atletas: ' . (string) ($exception->errorInfo[0] ?? 'indisponível'));
                http_response_code(503);
                exit('Não foi possível carregar o cadastro de atletas neste momento.');
            }
            error_log('Falha no módulo de atletas: ' . get_class($exception));
            http_response_code(503);
            exit('O módulo de atletas está temporariamente indisponível.');
        }
    }

    private function post(AthleteModel $model, int $userId, array $config): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('A sessão do formulário expirou. Atualize a página e tente novamente.');
        }
        $actionValue = $_POST['action'] ?? 'save';
        $action = is_string($actionValue) ? $actionValue : '';
        if (in_array($action, ['create-category', 'add-position', 'add-role'], true)) {
            $this->saveCategoryConfiguration($model, $config, $action);
            return;
        }
        if ($action === 'toggle') {
            $model->toggle($this->id($_POST['id'] ?? null), $userId);
            $this->redirect('Cadastro atualizado.');
        }
        if ($action !== 'save') {
            http_response_code(400);
            exit('Ação inválida.');
        }
        $data = $this->validate($_POST);
        $errors = $data['_errors'];
        unset($data['_errors']);
        $id = (int) ($data['id'] ?? 0);
        $removePhoto = ($_POST['remover_foto'] ?? '') === '1';
        $photo = null;
        if ($removePhoto && isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Escolha remover a foto atual ou enviar uma nova, não as duas ações.';
        } elseif (!$removePhoto) {
            try {
                $photo = $this->processPhoto($_FILES['foto'] ?? null);
            } catch (\InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        if ($errors !== []) {
            $athlete = $id > 0 ? (new AthleteModel())->find($id) : null;
            $this->form($config, $athlete, $data, $errors);
            return;
        }
        try {
            $assignments = $data['categorias'];
            unset($data['categorias']);
            $model->save($data, $userId, $photo, $removePhoto, $assignments);
        } catch (PDOException $exception) {
            $state = (string) ($exception->errorInfo[0] ?? '');
            if ($state === '23505') {
                $athlete = $id > 0 ? $model->find($id) : null;
                $constraint = (string) ($exception->errorInfo[2] ?? '');
                $message = match (true) {
                    str_contains($constraint, 'atletas_cpf_unique') => 'Este CPF já está cadastrado para outro atleta.',
                    str_contains($constraint, 'atletas_email_unique') => 'Este e-mail já está cadastrado para outro atleta.',
                    str_contains($constraint, 'atleta_categoria_numero_unique') => 'Este número de camisa já está ocupado nessa categoria. Escolha outro número disponível.',
                    default => 'Um dado que precisa ser único já está cadastrado. Confira o CPF, e-mail e números de camisa.',
                };
                $this->form($config, $athlete, $data + ['categorias' => $assignments], [$message]);
                return;
            }
            error_log('Falha ao salvar atleta: ' . $state);
            $athlete = $id > 0 ? $model->find($id) : null;
            $this->form($config, $athlete, $data + ['categorias' => $assignments], ['Não foi possível salvar os dados. Verifique o banco e tente novamente.']);
            return;
        } catch (RuntimeException $exception) {
            $athlete = $id > 0 ? $model->find($id) : null;
            $this->form($config, $athlete, $data + ['categorias' => $assignments], [$exception->getMessage()]);
            return;
        }
        $this->redirect('Cadastro salvo.');
    }

    private function validate(array $input): array
    {
        $errors = [];
        $text = static function (string $key, int $max, bool $required = false) use ($input, &$errors): ?string {
            $rawValue = $input[$key] ?? '';
            if (!is_string($rawValue) && !is_numeric($rawValue)) {
                $errors[] = 'Um dos campos contém um valor inválido.';
                return null;
            }
            $value = trim((string) $rawValue);
            if ($value === '') {
                if ($required) $errors[] = 'Nome é obrigatório.';
                return null;
            }
            if (mb_strlen($value, 'UTF-8') > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
                $errors[] = 'Um dos campos de texto ultrapassou o limite ou contém caracteres inválidos.';
                return null;
            }
            return $value;
        };
        $idValue = $input['id'] ?? '';
        if (!is_string($idValue) || ($idValue !== '' && $idValue !== '0' && preg_match('/\A[1-9]\d{0,17}\z/', $idValue) !== 1)) {
            $errors[] = 'Identificador inválido.';
            $id = 0;
        } else {
            $id = $idValue === '' || $idValue === '0' ? 0 : (int) $idValue;
        }
        $birthValue = $input['data_nascimento'] ?? '';
        $birth = is_string($birthValue) ? trim($birthValue) : '';
        if (!is_string($birthValue)) $errors[] = 'Data de nascimento inválida.';
        if ($birth !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $birth);
            if (!$date || $date->format('Y-m-d') !== $birth || $date > new \DateTimeImmutable('today')) $errors[] = 'Data de nascimento inválida.';
        } else $birth = null;
        $phone = static function (?string $value, bool $required = false) use (&$errors): ?string {
            if ($value === null) { if ($required) $errors[] = 'Informe o celular do contato de emergência.'; return null; }
            if (preg_match('/\A[0-9()+.\s-]{1,30}\z/', $value) !== 1) { $errors[] = 'Celular inválido.'; return null; }
            $digits = preg_replace('/\D+/', '', $value) ?? '';
            if (strlen($digits) < 10 || strlen($digits) > 13) { $errors[] = 'Celular deve conter entre 10 e 13 dígitos.'; return null; }
            return $digits;
        };
        $email = strtolower((string) ($text('email', 254) ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) $errors[] = 'E-mail inválido.';
        $cpfValue = $input['cpf'] ?? '';
        if (!is_string($cpfValue)) { $errors[] = 'CPF inválido.'; $cpfValue = ''; }
        $cpf = preg_replace('/\D+/', '', $cpfValue) ?? '';
        if ($cpf !== '' && !$this->validCpf($cpf)) $errors[] = 'CPF inválido.';
        $bloodValue = $input['tipo_sanguineo'] ?? '';
        $blood = is_string($bloodValue) ? $bloodValue : '';
        if (!in_array($blood, self::BLOOD_TYPES, true)) { $errors[] = 'Tipo sanguíneo inválido.'; $blood = ''; }
        $genderValue = $input['genero'] ?? '';
        $gender = is_string($genderValue) ? $genderValue : '';
        if (!in_array($gender, ['masculino', 'feminino'], true)) { $errors[] = 'Selecione o gênero esportivo do atleta.'; $gender = ''; }
        $assignments = $this->parseAssignments($input['categorias'] ?? [], $errors);
        $emergencyName = $text('contato_emergencia_nome', 120);
        $emergencyCell = $phone($text('contato_emergencia_celular', 30));
        $emergencyRelation = $text('contato_emergencia_parentesco', 60);
        if (($emergencyName === null) !== ($emergencyCell === null)
            || ($emergencyRelation !== null && $emergencyName === null)) {
            $errors[] = 'Preencha nome e celular do contato de emergência juntos; parentesco requer esses dados.';
        }
        return [
            'id' => $id, 'nome' => $text('nome', 120, true), 'apelido' => $text('apelido', 80),
            'genero' => $gender, 'data_nascimento' => $birth, 'celular' => $phone($text('celular', 30)),
            'rg' => $text('rg', 30), 'cpf' => $cpf === '' ? null : $cpf, 'email' => $email === '' ? null : $email,
            'contato_emergencia_nome' => $emergencyName, 'contato_emergencia_celular' => $emergencyCell,
            'contato_emergencia_parentesco' => $emergencyRelation, 'tipo_sanguineo' => $blood ?: null,
            'alergias' => $text('alergias', 2000), 'ativo' => ($_POST['ativo'] ?? '') === '1',
            'categorias' => $assignments, '_errors' => $errors,
        ];
    }

    private function parseAssignments(mixed $input, array &$errors): array
    {
        if (!is_array($input)) { $errors[] = 'Seleção de categorias inválida.'; return []; }
        $assignments = [];
        foreach ($input as $categoryId => $entry) {
            if (!is_string((string) $categoryId) || preg_match('/\A[1-9]\d{0,17}\z/', (string) $categoryId) !== 1 || !is_array($entry)) {
                $errors[] = 'Uma categoria selecionada é inválida.';
                continue;
            }
            $included = ($entry['participa'] ?? '') === '1';
            $positions = $entry['posicoes'] ?? [];
            $roles = $entry['cargos'] ?? [];
            if (!is_array($positions) || !is_array($roles)) { $errors[] = 'Seleção de cargos ou posições inválida.'; continue; }
            $ids = [];
            foreach ($positions as $positionId) {
                if (!is_string($positionId) || preg_match('/\A[1-9]\d{0,17}\z/', $positionId) !== 1) {
                    $errors[] = 'Uma posição selecionada é inválida.';
                    continue;
                }
                $ids[] = (int) $positionId;
            }
            $roleIds = [];
            foreach ($roles as $roleId) {
                if (!is_string($roleId) || preg_match('/\A[1-9]\d{0,17}\z/', $roleId) !== 1) {
                    $errors[] = 'Um cargo selecionado é inválido.';
                    continue;
                }
                $roleIds[] = (int) $roleId;
            }
            $jerseyValue = $entry['numero_camisa'] ?? '';
            if (!is_string($jerseyValue) || ($jerseyValue !== '' && preg_match('/\A(?:0|[1-9]\d?)\z/', $jerseyValue) !== 1)) {
                $errors[] = 'Número de camisa inválido.';
                $jersey = null;
            } else {
                $jersey = $jerseyValue === '' ? null : (int) $jerseyValue;
            }
            if ($included || $ids !== [] || $roleIds !== [] || $jersey !== null) {
                $assignments[(int) $categoryId] = [
                    'numero_camisa' => $jersey,
                    'posicoes' => array_values(array_unique($ids)),
                    'cargos' => array_values(array_unique($roleIds)),
                ];
            }
        }
        return $assignments;
    }

    private function saveCategoryConfiguration(AthleteModel $model, array $config, string $action): void
    {
        try {
            if ($action === 'create-category') {
                $modality = $_POST['modalidade'] ?? '';
                $gender = $_POST['genero'] ?? '';
                if (!is_string($modality) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9 ._-]{0,39}\z/', trim($modality)) !== 1
                    || !is_string($gender) || !in_array($gender, ['masculino', 'feminino'], true)) {
                    throw new \InvalidArgumentException('Informe o nome da categoria e o gênero corretamente.');
                }
                $model->addCategory(trim($modality), $gender);
            } elseif ($action === 'add-position') {
                $categoryId = $this->id($_POST['categoria_id'] ?? null);
                $side = $_POST['side'] ?? '';
                $name = $_POST['nome_posicao'] ?? '';
                if (!is_string($side) || !in_array($side, ['ataque', 'defesa', 'time_especial'], true)
                    || !is_string($name) || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 60
                    || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
                    throw new \InvalidArgumentException('Informe um side e uma posição válidos (até 60 caracteres).');
                }
                $model->addPosition($categoryId, $side, trim($name));
            } else {
                $name = $_POST['nome_cargo'] ?? '';
                if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 60
                    || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
                    throw new \InvalidArgumentException('Informe um nome de cargo válido, com até 60 caracteres.');
                }
                $model->addRole(trim($name));
            }
            $this->redirectCategories('Configuração salva.');
        } catch (PDOException $exception) {
            $state = (string) ($exception->errorInfo[0] ?? '');
            error_log('Falha ao configurar categorias de atletas: ' . $state);
            $message = $state === '23505' && $action === 'add-position'
                ? 'Essa posição já está cadastrada nessa categoria.'
                : 'Não foi possível salvar a configuração.';
            $this->categoriesPage($model, $config, $message);
        } catch (\InvalidArgumentException | RuntimeException $exception) {
            $this->categoriesPage($model, $config, $exception->getMessage());
        }
    }

    private function validCpf(string $cpf): bool
    {
        if (preg_match('/\A\d{11}\z/', $cpf) !== 1 || preg_match('/\A(\d)\1{10}\z/', $cpf) === 1) return false;
        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;
            for ($index = 0; $index < $digit; $index++) $sum += (int) $cpf[$index] * (($digit + 1) - $index);
            $check = ($sum * 10) % 11;
            if ($check === 10) $check = 0;
            if ($check !== (int) $cpf[$digit]) return false;
        }
        return true;
    }

    private function processPhoto(mixed $file): ?string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (!is_int($file['error'] ?? null) || !is_string($file['tmp_name'] ?? null) || !is_numeric($file['size'] ?? null)
            || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])
            || (int) $file['size'] > self::MAX_UPLOAD) throw new \InvalidArgumentException('A foto deve ter no máximo 5 MB.');
        $bytes = file_get_contents($file['tmp_name']);
        $info = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !function_exists('imagecreatefromstring')) {
            throw new \InvalidArgumentException('Envie uma imagem JPEG, PNG ou WebP.');
        }
        $width = (int) $info[0]; $height = (int) $info[1];
        if ($width < 1 || $height < 1 || $width > 3000 || $height > 3000 || $width * $height > 6_000_000) {
            throw new \InvalidArgumentException('A imagem excede as dimensões permitidas (3.000 × 3.000, até 6 MP).');
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source) throw new \InvalidArgumentException('A imagem não pôde ser processada.');
        $scale = min(1, 1200 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale)); $newHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($target, 255, 255, 255); imagefill($target, 0, 0, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start(); imagejpeg($target, null, 82); $result = ob_get_clean();
        imagedestroy($source); imagedestroy($target);
        if (!is_string($result) || strlen($result) > 512_000) throw new \InvalidArgumentException('A foto otimizada deve ter no máximo 500 KB.');
        return base64_encode($result);
    }

    private function photo(AthleteModel $model, int $id): void
    {
        $bytes = $model->photo($id);
        if ($bytes === null) { http_response_code(404); exit; }
        header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($bytes)); echo $bytes;
    }

    private function form(array $config, ?array $athlete, array $values, ?array $errors): void
    {
        $escape = [View::class, 'escape']; $csrfToken = Csrf::token();
        $values = array_replace(is_array($athlete) ? $athlete : [], $values);
        $model = new AthleteModel();
        $categoryOptions = $this->groupCategories($model->categoryCatalog());
        $roles = $model->roleCatalog();
        $numberOptions = [];
        $athleteId = $athlete !== null ? (int) $athlete['id'] : null;
        foreach ($categoryOptions as $category) {
            $numberOptions[$category['id']] = $model->availableNumbers($category['id'], $athleteId);
        }
        if (is_array($values['categorias'] ?? null)) {
            $selectedCategories = $values['categorias'];
        } elseif ($athlete !== null) {
            $selectedCategories = $model->assignmentsForAthlete((int) $athlete['id']);
            foreach ($model->rolesForAthlete((int) $athlete['id']) as $categoryId => $roleIds) {
                $selectedCategories[$categoryId] ??= ['numero_camisa' => null, 'posicoes' => [], 'cargos' => []];
                $selectedCategories[$categoryId]['cargos'] = $roleIds;
            }
        } else {
            $selectedCategories = [];
        }
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'form.php';
    }

    private function listing(array $config, array $athletes, string $search, bool $activeOnly, array $positionSummary, array $categorySummary): void
    {
        $escape = [View::class, 'escape']; $csrfToken = Csrf::token();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'index.php';
    }

    private function categoriesPage(AthleteModel $model, array $config, ?string $error): void
    {
        $escape = [View::class, 'escape'];
        $csrfToken = Csrf::token();
        $categoryOptions = $this->groupCategories($model->categoryCatalog());
        $roles = $model->roleCatalog();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'categories.php';
    }

    private function groupCategories(array $rows): array
    {
        $categories = [];
        foreach ($rows as $row) {
            $id = (int) $row['categoria_id'];
            $categories[$id] ??= [
                'id' => $id, 'modalidade' => (string) $row['modalidade'], 'genero' => (string) $row['genero'],
                'sides' => [],
            ];
            $sideCode = (string) $row['side_codigo'];
            $categories[$id]['sides'][$sideCode] ??= [
                'codigo' => $sideCode, 'nome' => (string) $row['side_nome'], 'posicoes' => [],
            ];
            if ($row['posicao_id'] !== null) {
                $categories[$id]['sides'][$sideCode]['posicoes'][] = [
                    'id' => (int) $row['posicao_id'], 'nome' => (string) $row['posicao_nome'],
                ];
            }
        }
        return array_values($categories);
    }

    private function redirectCategories(string $message): never
    {
        header('Location: atletas.php?action=categories&notice=' . rawurlencode($message), true, 303);
        exit;
    }

    private function id(mixed $value): int
    {
        if (!is_scalar($value) || preg_match('/\A[1-9]\d{0,17}\z/', (string) $value) !== 1) {
            http_response_code(400); exit('Identificador inválido.');
        }
        return (int) $value;
    }

    private function redirect(string $message): never
    {
        header('Location: atletas.php?status=salvo&notice=' . rawurlencode($message), true, 303);
        exit;
    }
}
FLAG_SETUP_ATLETAS_CONTROLLER,
    'modules/atletas/Views/index.php' => <<<'FLAG_SETUP_ATLETAS_INDEX_VIEW'
<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Atletas | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.css', $config)) ?>">
</head>
<body class="admin-page">
<header class="admin-header"><a class="auth-brand" href="../admin/index.php"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout.php"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="button-secondary" type="submit">Sair</button></form></header>
<main class="admin-content athletes-page">
    <p class="eyebrow">GESTÃO ESPORTIVA</p><h1>Atletas</h1>
    <p class="athletes-lead">Cadastro administrativo da equipe.</p>
    <?php if (isset($_GET['notice'])): ?><p class="athlete-notice" role="status"><?= $escape($_GET['notice']) ?></p><?php endif; ?>
    <div class="athlete-toolbar"><form method="get" class="athlete-search"><label for="q">Buscar atleta</label><div><input id="q" name="q" type="search" value="<?= $escape($search) ?>" maxlength="100" placeholder="Digite o nome ou apelido" autocomplete="off"><select name="status" aria-label="Filtrar status"><option value="ativos" <?= $activeOnly ? 'selected' : '' ?>>Ativos</option><option value="todos" <?= !$activeOnly ? 'selected' : '' ?>>Todos</option></select><button class="athlete-button" type="submit">Buscar</button></div></form><div class="athlete-toolbar-actions"><a class="athlete-button" href="atletas.php?action=new">Novo atleta</a><a class="athlete-button button-outline" href="atletas.php?action=categories">Categorias, posições e cargos</a></div></div>
    <p class="athlete-privacy">A lista não exibe documentos, contato de emergência ou informações de saúde.</p>
    <?php if ($athletes !== []): ?><p class="athlete-result-count"><?= count($athletes) ?> <?= count($athletes) === 1 ? 'atleta encontrado' : 'atletas encontrados' ?><?= count($athletes) === 100 ? ' · exibindo os primeiros 100' : '' ?></p><?php endif; ?>
    <div class="athlete-list" aria-live="polite">
    <?php if ($athletes === []): ?>
        <section class="athlete-empty">
            <span class="athlete-empty-icon" aria-hidden="true">🏈</span>
            <h2><?= $search !== '' ? 'Nenhum atleta encontrado' : 'Ainda não há atletas cadastrados' ?></h2>
            <p><?= $search !== '' ? 'Não encontramos resultados para “' . $escape($search) . '”. Confira a escrita ou limpe a busca.' : 'Cadastre o primeiro atleta para começar a montar as equipes e atribuir categorias e posições.' ?></p>
            <div class="athlete-empty-actions">
                <?php if ($search !== ''): ?><a class="athlete-button button-outline" href="atletas.php<?= !$activeOnly ? '?status=todos' : '' ?>">Limpar busca</a><?php endif; ?>
                <a class="athlete-button" href="atletas.php?action=new">Cadastrar atleta</a>
            </div>
        </section>
    <?php endif; ?>
    <?php foreach ($athletes as $row):
        $birth = $row['data_nascimento'] ?? null;
        $age = is_string($birth) ? (new DateTimeImmutable($birth))->diff(new DateTimeImmutable('today'))->y : null;
        $isActive = in_array($row['ativo'] ?? false, [true, 1, '1', 't', 'true'], true);
    ?>
        <article class="athlete-card">
            <?php if (in_array($row['tem_foto'] ?? false, [true, 1, '1', 't', 'true'], true)): ?><img class="athlete-photo" src="atletas.php?action=photo&amp;id=<?= (int) $row['id'] ?>" alt="Foto de <?= $escape($row['nome']) ?>" width="72" height="72" loading="lazy"><?php else: ?><span class="athlete-photo athlete-photo-empty" aria-hidden="true">🏈</span><?php endif; ?>
            <div class="athlete-main"><h2><?= $escape($row['nome']) ?><?= !empty($row['apelido']) ? ' <span>“' . $escape($row['apelido']) . '”</span>' : '' ?></h2><p><?= $row['genero'] === 'masculino' ? 'Masculino' : ($row['genero'] === 'feminino' ? 'Feminino' : 'Gênero pendente') ?><?= $age !== null ? ' · ' . $age . ' anos' : '' ?></p>
                <?php foreach ($categorySummary[(int) $row['id']] ?? [] as $category): ?><p class="athlete-category-summary"><strong><?= $escape($category['modalidade']) ?> · <?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></strong><?= $category['numero_camisa'] !== null ? ' · Camisa ' . $escape($category['numero_camisa']) : '' ?><?= !empty($category['cargos']) ? ' · Cargos: ' . $escape($category['cargos']) : '' ?></p><?php endforeach; ?>
                <?php foreach ($positionSummary[(int) $row['id']] ?? [] as $assignment): ?><p class="athlete-assignment"><strong><?= $escape($assignment['modalidade']) ?> · <?= $assignment['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?> · <?= $escape($assignment['side_nome']) ?>:</strong> <?= $escape($assignment['posicoes'] ?? '') ?></p><?php endforeach; ?>
            </div>
            <span class="athlete-status <?= $isActive ? 'is-active' : 'is-inactive' ?>"><?= $isActive ? 'Ativo' : 'Inativo' ?></span><a class="athlete-link" href="atletas.php?action=edit&amp;id=<?= (int) $row['id'] ?>">Editar</a>
            <form method="post" action="atletas.php"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="athlete-text-button" type="submit"><?= $isActive ? 'Desativar' : 'Ativar' ?></button></form>
        </article>
    <?php endforeach; ?>
    </div>
</main>
</body>
</html>
FLAG_SETUP_ATLETAS_INDEX_VIEW,
    'modules/atletas/Views/form.php' => <<<'FLAG_SETUP_ATLETAS_FORM_VIEW'
<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
$value = static fn(string $key, mixed $default = ''): string => (string) ($values[$key] ?? $default);
$isEdit = (int) ($values['id'] ?? 0) > 0;
$isActive = in_array($values['ativo'] ?? true, [true, 1, '1', 't', 'true'], true);
$hasPhoto = in_array($values['tem_foto'] ?? false, [true, 1, '1', 't', 'true'], true);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title><?= $isEdit ? 'Editar atleta' : 'Novo atleta' ?> | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.css', $config)) ?>">
</head>
<body class="admin-page">
<header class="admin-header"><a class="auth-brand" href="../admin/index.php"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout.php"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="button-secondary" type="submit">Sair</button></form></header>
<main class="admin-content athletes-page">
    <a class="athlete-link" href="atletas.php">← Voltar à lista</a><p class="eyebrow">GESTÃO ESPORTIVA</p><h1><?= $isEdit ? 'Editar atleta' : 'Novo atleta' ?></h1>
    <?php if (is_array($errors) && $errors !== []): ?><div class="athlete-errors" role="alert"><p>Confira os dados:</p><ul><?php foreach ($errors as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="athlete-form" method="post" action="atletas.php" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $escape($value('id', 0)) ?>">
        <fieldset><legend>Identificação</legend><div class="athlete-fields">
            <label>Nome *<input name="nome" maxlength="120" required autocomplete="name" value="<?= $escape($value('nome')) ?>"></label>
            <label>Apelido<input name="apelido" maxlength="80" value="<?= $escape($value('apelido')) ?>"></label>
            <label>Gênero para elegibilidade nas categorias *<select id="athlete-gender" name="genero" required><option value="">Selecione</option><option value="masculino" <?= $value('genero') === 'masculino' ? 'selected' : '' ?>>Masculino</option><option value="feminino" <?= $value('genero') === 'feminino' ? 'selected' : '' ?>>Feminino</option></select><small>Define quais categorias podem ser vinculadas ao atleta.</small></label>
            <label>Data de nascimento<input name="data_nascimento" type="date" max="<?= date('Y-m-d') ?>" value="<?= $escape(substr($value('data_nascimento'), 0, 10)) ?>"><small>A idade é calculada a partir desta data.</small></label>
            <label>Status<select name="ativo"><option value="1" <?= $isActive ? 'selected' : '' ?>>Ativo</option><option value="0" <?= !$isActive ? 'selected' : '' ?>>Inativo</option></select></label>
        </div></fieldset>

        <fieldset><legend>Vínculos esportivos</legend>
            <p class="athlete-privacy">Cada categoria tem seu próprio número de camisa, cargos e posições. A mesma pessoa pode ter funções diferentes no X5 e no X8.</p>
            <p><a class="athlete-link" href="atletas.php?action=categories">Configurar categorias, posições e cargos</a></p>
            <?php if ($categoryOptions === []): ?><p class="athlete-empty">Não há categorias ativas. Configure uma categoria antes de salvar a participação.</p>
            <?php else: foreach ($categoryOptions as $category):
                $categoryId = $category['id'];
                $picked = $selectedCategories[$categoryId] ?? null;
                $participates = array_key_exists($categoryId, $selectedCategories);
                $pickedPositions = is_array($picked) ? ($picked['posicoes'] ?? $picked) : [];
                if (!is_array($pickedPositions)) $pickedPositions = [];
                $pickedRoles = is_array($picked) && is_array($picked['cargos'] ?? null) ? $picked['cargos'] : [];
                $pickedNumber = is_array($picked) ? (string) ($picked['numero_camisa'] ?? '') : '';
                $availableNumbers = $numberOptions[$categoryId] ?? [];
                $pickedNumberAvailable = $pickedNumber !== '' && in_array((int) $pickedNumber, $availableNumbers, true);
                $compatible = $category['genero'] === $value('genero');
            ?>
                <section class="athlete-category-choice" data-category-gender="<?= $escape($category['genero']) ?>">
                    <label class="athlete-category-toggle"><input class="category-link-toggle" type="checkbox" name="categorias[<?= $categoryId ?>][participa]" value="1" <?= $participates ? 'checked' : '' ?>> Vincular a <?= $escape($category['modalidade']) ?> · <?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></label>
                    <?php if (!$compatible): ?><p class="athlete-role-hint">Para este gênero, a pessoa pode receber cargos da equipe. Camisa, posições e o cargo Atleta exigem gênero esportivo compatível.</p><?php endif; ?>
                    <div class="athlete-category-fields">
                        <label class="athlete-jersey-field athlete-player-field">Número da camisa
                            <select name="categorias[<?= $categoryId ?>][numero_camisa]" class="category-assignment-control athlete-player-control" <?= $compatible ? '' : 'disabled' ?>>
                                <option value="">Sem número</option>
                                <?php if ($pickedNumber !== '' && !$pickedNumberAvailable): ?><option value="<?= $escape($pickedNumber) ?>" selected disabled>Nº <?= $escape($pickedNumber) ?> · já ocupado</option><?php endif; ?>
                                <?php foreach ($availableNumbers as $availableNumber): ?><option value="<?= $availableNumber ?>" <?= (string) $availableNumber === $pickedNumber ? 'selected' : '' ?>>Nº <?= $availableNumber ?></option><?php endforeach; ?>
                            </select>
                            <small>Os números já usados nesta categoria não aparecem.</small>
                        </label>
                        <fieldset class="athlete-role-group"><legend>Cargos nesta categoria</legend>
                            <?php if ($roles === []): ?><small>Nenhum cargo cadastrado. <a href="atletas.php?action=categories">Cadastrar cargos</a></small>
                            <?php else: ?><div class="athlete-role-options"><?php foreach ($roles as $role): $playerRole = in_array(mb_strtolower((string) $role['nome'], 'UTF-8'), ['atleta', 'jogador', 'jogadora'], true); ?><label class="athlete-role-option"><input class="category-assignment-control" type="checkbox" name="categorias[<?= $categoryId ?>][cargos][]" value="<?= $role['id'] ?>" <?= in_array((int) $role['id'], array_map('intval', $pickedRoles), true) ? 'checked' : '' ?> <?= $playerRole && !$compatible ? 'disabled' : '' ?> <?= $playerRole ? 'data-player-role="true"' : '' ?>> <?= $escape($role['nome']) ?></label><?php endforeach; ?></div><?php endif; ?>
                        </fieldset>
                    </div>
                    <div class="athlete-side-groups athlete-player-fields">
                    <?php foreach ($category['sides'] as $side): ?>
                        <fieldset class="athlete-side"><legend><?= $escape($side['nome']) ?></legend>
                            <?php if ($side['posicoes'] === []): ?><small>Nenhuma posição configurada. <a href="atletas.php?action=categories">Adicionar posições</a></small>
                            <?php else: foreach ($side['posicoes'] as $position): ?><label class="athlete-position"><input class="category-assignment-control athlete-player-control" type="checkbox" name="categorias[<?= $categoryId ?>][posicoes][]" value="<?= $position['id'] ?>" <?= in_array($position['id'], $pickedPositions, false) ? 'checked' : '' ?> <?= $compatible ? '' : 'disabled' ?>> <?= $escape($position['nome']) ?></label><?php endforeach; endif; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; endif; ?>
        </fieldset>

        <fieldset><legend>Contato</legend><div class="athlete-fields">
            <label>Celular do atleta<input name="celular" type="tel" inputmode="tel" maxlength="30" autocomplete="tel" value="<?= $escape($value('celular')) ?>"></label>
            <label>E-mail<input name="email" type="email" maxlength="254" autocomplete="email" value="<?= $escape($value('email')) ?>"></label>
        </div></fieldset>
        <fieldset><legend>Contato de emergência</legend><div class="athlete-fields">
            <label>Nome<input name="contato_emergencia_nome" maxlength="120" value="<?= $escape($value('contato_emergencia_nome')) ?>"></label>
            <label>Celular<input name="contato_emergencia_celular" type="tel" inputmode="tel" maxlength="30" value="<?= $escape($value('contato_emergencia_celular')) ?>"></label>
            <label>Parentesco<input name="contato_emergencia_parentesco" maxlength="60" value="<?= $escape($value('contato_emergencia_parentesco')) ?>"></label>
        </div></fieldset>
        <fieldset><legend>Documentos e saúde <span class="athlete-private-label">Dados restritos</span></legend><p class="athlete-privacy">Acesso restrito a administradores. Informe somente o necessário para a gestão da equipe.</p><div class="athlete-fields">
            <label>RG<input name="rg" maxlength="30" value="<?= $escape($value('rg')) ?>"></label>
            <label>CPF<input name="cpf" inputmode="numeric" maxlength="14" autocomplete="off" value="<?= $escape($value('cpf')) ?>"><small>O CPF não pode estar associado a outro cadastro.</small></label>
            <label>Tipo sanguíneo<select name="tipo_sanguineo"><option value="">Não informado</option><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $blood): ?><option value="<?= $blood ?>" <?= $value('tipo_sanguineo') === $blood ? 'selected' : '' ?>><?= $blood ?></option><?php endforeach; ?></select></label>
            <label class="athlete-wide">Alergias / observações de saúde<textarea name="alergias" maxlength="2000" rows="4"><?= $escape($value('alergias')) ?></textarea></label>
        </div></fieldset>
        <fieldset><legend>Foto</legend><div class="athlete-fields">
            <?php if ($hasPhoto): ?><div class="athlete-photo-preview"><img src="atletas.php?action=photo&amp;id=<?= (int) $values['id'] ?>" alt="Foto atual do atleta" width="120" height="120"><label><input type="checkbox" name="remover_foto" value="1"> Remover foto atual</label></div><?php endif; ?>
            <label class="athlete-wide">Enviar ou substituir foto<input type="file" name="foto" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG ou WebP. Máximo 5 MB e 6 megapixels. A imagem será redimensionada e convertida para JPEG.</small></label>
        </div></fieldset>
        <div class="athlete-actions"><button class="athlete-button" type="submit">Salvar cadastro</button><a class="athlete-link" href="atletas.php">Cancelar</a></div>
    </form>
</main>
<script src="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.js', $config)) ?>" defer></script>
</body>
</html>
FLAG_SETUP_ATLETAS_FORM_VIEW,
    'modules/atletas/Assets/atletas.css' => <<<'FLAG_SETUP_ATLETAS_CSS'
.athletes-page{max-width:1040px}
.athletes-page h1{margin:0 0 8px}
.athletes-page>.eyebrow{margin-bottom:12px}
.athletes-lead{margin:0 0 28px;color:var(--muted);font-size:16px;line-height:1.65}
.athlete-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:28px 0 14px;padding:20px;border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 8px 24px rgba(29,41,80,.05)}
.athlete-search{display:grid;flex:1;gap:8px;max-width:620px}
.athlete-search>label,.athlete-form>label,.athlete-fields>label{display:grid;gap:7px;color:var(--ink);font-size:13px;font-weight:750}
.athlete-search>div{display:flex;gap:9px}
.athlete-search input,.athlete-search select,.athlete-form input:not([type=checkbox]):not([type=file]),.athlete-form select,.athlete-form textarea{width:100%;min-height:46px;padding:10px 12px;border:1px solid #cbd2df;border-radius:5px;background:#fff;color:var(--ink);font:inherit}
.athlete-search input{flex:1;min-width:120px}
.athlete-search select{width:auto;min-width:130px}
.athlete-search input:focus,.athlete-search select:focus,.athlete-form input:focus,.athlete-form select:focus,.athlete-form textarea:focus{outline:3px solid rgba(40,121,182,.16);border-color:var(--blue)}
.athlete-button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;padding:0 16px;border:1px solid var(--blue);border-radius:5px;background:var(--blue);color:#fff;font:inherit;font-size:13px;font-weight:750;text-decoration:none;cursor:pointer;transition:background .15s ease,border-color .15s ease}
.athlete-button:hover{border-color:var(--navy);background:var(--navy);color:#fff}
.athlete-button.button-outline{border:1px solid var(--blue);background:#fff;color:var(--blue)}
.athlete-button.button-outline:hover{background:#f1f7fc;color:var(--navy)}
.athlete-toolbar-actions,.athlete-empty-actions{display:flex;flex-wrap:wrap;gap:9px}
.athlete-privacy{margin:12px 0 18px;padding:11px 14px;border-left:3px solid var(--blue);background:#f1f7fc;color:#44536b;font-size:12px;line-height:1.55}
.athlete-result-count{margin:18px 0 10px;color:var(--muted);font-size:13px;font-weight:700}
.athlete-list{display:grid;gap:12px}
.athlete-card{display:grid;grid-template-columns:72px minmax(0,1fr) auto;align-items:center;gap:16px;padding:18px;border:1px solid var(--line);border-radius:7px;background:#fff}
.athlete-photo{width:72px;height:72px;object-fit:cover;border-radius:7px;background:#f1f3f6}
.athlete-photo-empty{display:grid;place-items:center;font-size:27px}
.athlete-main{min-width:0}
.athlete-main h2{margin:0 0 5px;font-size:18px;line-height:1.35}
.athlete-main h2 span{color:var(--muted);font-size:15px;font-weight:500}
.athlete-main p{margin:0;color:var(--muted);font-size:13px;line-height:1.55}
.athlete-main .athlete-assignment{margin-top:5px;font-size:12px}
.athlete-status{justify-self:end;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800}
.athlete-status.is-active{background:#e8f5ee;color:#216b42}
.athlete-status.is-inactive{background:#f1f2f4;color:#596273}
.athlete-card>.athlete-link,.athlete-link{color:var(--blue);font-size:13px;font-weight:750;text-decoration:none}
.athlete-link:hover{text-decoration:underline}
.athlete-card form{margin:0}
.athlete-text-button{padding:4px;border:0;background:transparent;color:#596273;font:inherit;font-size:12px;text-decoration:underline;cursor:pointer}
.athlete-empty{display:grid;justify-items:center;gap:12px;padding:42px 22px;border:1px dashed #b9c6d8;border-radius:8px;background:#f8fafc;text-align:center}
.athlete-empty-icon{display:grid;width:54px;height:54px;place-items:center;border-radius:50%;background:#e9f3fb;font-size:25px}
.athlete-empty h2{margin:0;font-size:20px;letter-spacing:-.02em}
.athlete-empty p{max-width:520px;margin:0;color:var(--muted);font-size:14px;line-height:1.65}
.athlete-empty-actions{justify-content:center;margin-top:5px}
.athlete-form{display:grid;gap:18px;margin:24px 0;padding:24px;border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 8px 24px rgba(29,41,80,.05)}
.athlete-form fieldset{min-width:0;margin:0;padding:20px;border:1px solid #e1e5ec;border-radius:7px;background:#fff}
.athlete-form legend{padding:0 8px;color:var(--navy);font-size:16px;font-weight:800}
.athlete-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.athlete-form small{color:var(--muted);font-size:12px;font-weight:450;line-height:1.5}
.athlete-form input[type=checkbox]{width:18px;height:18px;accent-color:var(--blue)}
.athlete-form input[type=file]{width:100%;padding:12px;border:1px dashed #b9c6d8;border-radius:5px;background:#f8fafc;color:var(--ink);font:inherit}
.athlete-form textarea{min-height:110px;resize:vertical}
.athlete-wide{grid-column:1/-1}
.athlete-errors{margin:18px 0;padding:14px 18px;border:1px solid #e7b8bb;border-radius:6px;background:#fff3f3;color:#801b22;font-size:14px;line-height:1.6}
.athlete-errors p{margin:0 0 5px;font-weight:800}
.athlete-errors ul{margin:5px 0 0;padding-left:20px}
.athlete-actions{display:flex;align-items:center;gap:14px;padding-top:4px}
.athlete-photo-preview{display:flex;align-items:center;gap:14px}
.athlete-photo-preview img{width:100px;height:100px;object-fit:cover;border-radius:8px}
.athlete-photo-preview label{display:flex;align-items:center;gap:8px;font-size:13px}
.athlete-category-choice{margin-top:16px;padding:16px;border:1px solid var(--line);border-radius:6px;background:#f8fafc}
.athlete-category-toggle{display:flex!important;align-items:center;gap:9px;font-size:14px!important}
.athlete-category-toggle input,.athlete-position input{width:18px;height:18px;accent-color:var(--blue)}
.athlete-side-groups{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px}
.athlete-side{min-width:0;padding:12px;border:1px solid var(--line);border-radius:5px;background:#fff}
.athlete-side legend{font-size:13px}
.athlete-position{display:flex!important;align-items:center;gap:8px;margin:8px 0}
.athlete-assignment{margin-top:5px!important;font-size:12px!important}
.category-config-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:24px 0 40px}
.category-config-card{align-content:start;margin:0;padding:22px}
.category-form-heading{display:flex;align-items:flex-start;gap:12px;margin-bottom:2px}
.category-form-heading>span{display:grid;width:34px;height:34px;flex:none;place-items:center;border-radius:50%;background:#e9f3fb;color:var(--blue);font-size:12px;font-weight:850}
.category-form-heading h2{margin:0;font-size:19px}
.category-form-heading p{margin:5px 0 0;color:var(--muted);font-size:13px;line-height:1.5}
.category-form-help{margin:0;padding:10px 12px;border-radius:5px;background:#f1f7fc;color:#44536b;font-size:12px;line-height:1.55}
.category-config-card>.athlete-button{justify-self:start;margin-top:0}
.category-catalog{display:grid;gap:12px}
.category-catalog>h2{margin:0}
.category-catalog-card{padding:18px;border:1px solid var(--line);border-radius:7px;background:#fff}
.category-catalog-card h3{margin:0 0 14px}
.category-catalog-card h3 span{margin-left:6px;color:var(--muted);font-size:13px;font-weight:500}
.category-catalog-sides{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.category-catalog-sides section{padding:12px;border-radius:5px;background:#f7f8fa}
.category-catalog-sides h4{margin:0 0 7px;font-size:13px}
.category-catalog-sides ul,.category-catalog-sides p{margin:0;padding-left:18px;color:var(--muted);font-size:13px;line-height:1.6}
.category-catalog-sides p{padding:0}
.athlete-category-fields{display:grid;grid-template-columns:minmax(180px,.8fr) minmax(0,1.5fr);gap:14px;margin-top:16px}
.athlete-role-hint{margin:12px 0 0;padding:9px 11px;border-left:3px solid var(--blue);background:#f1f7fc;color:#44536b;font-size:12px;line-height:1.55}
.athlete-player-control:disabled{background:#f1f2f4;color:#737b89;cursor:not-allowed}
.athlete-jersey-field{display:grid;align-content:start;gap:7px;color:var(--ink);font-size:13px;font-weight:750}
.athlete-jersey-field select{width:100%;min-height:42px;padding:8px 10px;border:1px solid #cbd2df;border-radius:5px;background:#fff;color:var(--ink);font:inherit}
.athlete-role-group{min-width:0;margin:0;padding:10px 12px;border:1px solid #e1e5ec;border-radius:5px;background:#fff}
.athlete-role-group legend{font-size:12px}
.athlete-role-options{display:flex;flex-wrap:wrap;gap:8px}
.athlete-role-option{display:flex!important;align-items:center;gap:6px;padding:6px 9px;border:1px solid #e1e5ec;border-radius:999px;background:#f8fafc;font-size:12px!important;font-weight:600!important}
.athlete-role-option input{width:16px!important;height:16px!important}
.athlete-category-summary{margin-top:7px!important;padding-top:6px;border-top:1px solid #edf0f4}
.athlete-category-summary strong{color:var(--navy)}
.cargo-catalog{display:grid;gap:12px;margin-top:32px}
.cargo-catalog>h2{margin:0}
.cargo-catalog-card{padding:16px;border:1px solid var(--line);border-radius:7px;background:#fff}
.cargo-chip-list{display:flex;flex-wrap:wrap;gap:8px}
.cargo-chip{padding:7px 11px;border-radius:999px;background:#f1f7fc;color:var(--navy);font-size:12px;font-weight:700}
@media(max-width:900px){.category-config-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.category-config-grid>:last-child{grid-column:1/-1}}
@media(max-width:760px){.athlete-toolbar{align-items:stretch;flex-direction:column}.athlete-toolbar-actions{align-items:stretch}.athlete-toolbar-actions .athlete-button{align-self:flex-start}.category-config-grid,.athlete-side-groups,.category-catalog-sides{grid-template-columns:1fr}.category-config-grid>:last-child{grid-column:auto}.athlete-category-fields{grid-template-columns:1fr}}
@media(max-width:560px){.athletes-page{width:min(100% - 32px,540px);margin:34px auto}.athlete-toolbar,.athlete-form{padding:16px}.athlete-search>div{display:grid;grid-template-columns:1fr 1fr}.athlete-search input{grid-column:1/-1}.athlete-search select,.athlete-search button{width:100%}.athlete-card{grid-template-columns:56px minmax(0,1fr);gap:12px;padding:14px}.athlete-photo{width:56px;height:56px}.athlete-status{grid-column:2;justify-self:start}.athlete-card>.athlete-link,.athlete-card form{grid-column:2}.athlete-fields{grid-template-columns:1fr}.athlete-wide{grid-column:auto}.athlete-form fieldset{padding:16px}.athlete-actions{align-items:stretch;flex-direction:column}.athlete-actions .athlete-button{width:100%}}
FLAG_SETUP_ATLETAS_CSS,
    'modules/atletas/Database/migrations/001_atletas.sql' => <<<'FLAG_SETUP_ATLETAS_MIGRATION'
CREATE TABLE IF NOT EXISTS atletas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    apelido VARCHAR(80),
    numero SMALLINT CHECK (numero BETWEEN 0 AND 99),
    genero VARCHAR(12),
    cargo VARCHAR(80),
    data_nascimento DATE,
    celular VARCHAR(20),
    rg VARCHAR(30),
    cpf VARCHAR(11),
    email VARCHAR(254),
    contato_emergencia_nome VARCHAR(120),
    contato_emergencia_celular VARCHAR(20),
    contato_emergencia_parentesco VARCHAR(60),
    tipo_sanguineo VARCHAR(3) CHECK (tipo_sanguineo IN ('A+','A-','B+','B-','AB+','AB-','O+','O-')),
    alergias TEXT,
    foto BYTEA,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    atualizado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK ((contato_emergencia_nome IS NULL) = (contato_emergencia_celular IS NULL))
);

ALTER TABLE atletas ADD COLUMN IF NOT EXISTS genero VARCHAR(12);
CREATE UNIQUE INDEX IF NOT EXISTS atletas_id_genero_unique ON atletas (id, genero);
CREATE UNIQUE INDEX IF NOT EXISTS atletas_cpf_unique ON atletas (cpf) WHERE cpf IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS atletas_email_unique ON atletas (lower(email)) WHERE email IS NOT NULL;
CREATE INDEX IF NOT EXISTS atletas_nome_idx ON atletas (lower(nome), lower(apelido));
CREATE INDEX IF NOT EXISTS atletas_ativo_idx ON atletas (ativo, lower(nome));

CREATE TABLE IF NOT EXISTS categorias (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    modalidade VARCHAR(40) NOT NULL,
    genero VARCHAR(12) NOT NULL CHECK (genero IN ('masculino', 'feminino')),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id, genero)
);
CREATE UNIQUE INDEX IF NOT EXISTS categorias_modalidade_genero_unique ON categorias (lower(modalidade), genero);

CREATE TABLE IF NOT EXISTS categoria_sides (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    categoria_id BIGINT NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    codigo VARCHAR(24) NOT NULL CHECK (codigo IN ('ataque', 'defesa', 'time_especial')),
    nome VARCHAR(40) NOT NULL,
    UNIQUE (categoria_id, codigo),
    UNIQUE (categoria_id, id)
);

CREATE TABLE IF NOT EXISTS categoria_posicoes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    categoria_id BIGINT NOT NULL,
    side_id BIGINT NOT NULL,
    nome VARCHAR(60) NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id, side_id) REFERENCES categoria_sides(categoria_id, id) ON DELETE CASCADE,
    UNIQUE (categoria_id, id)
);
CREATE UNIQUE INDEX IF NOT EXISTS categoria_posicoes_nome_unique
    ON categoria_posicoes (categoria_id, side_id, lower(nome));
CREATE UNIQUE INDEX IF NOT EXISTS categoria_posicoes_nome_categoria_unique
    ON categoria_posicoes (categoria_id, lower(nome));

CREATE TABLE IF NOT EXISTS atleta_categorias (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    atleta_id BIGINT NOT NULL,
    categoria_id BIGINT NOT NULL,
    genero VARCHAR(12) NOT NULL,
    numero_camisa SMALLINT CHECK (numero_camisa BETWEEN 0 AND 99),
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (atleta_id, genero) REFERENCES atletas(id, genero) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id, genero) REFERENCES categorias(id, genero) ON DELETE RESTRICT,
    UNIQUE (atleta_id, categoria_id),
    UNIQUE (id, categoria_id)
);
ALTER TABLE atleta_categorias ADD COLUMN IF NOT EXISTS numero_camisa SMALLINT CHECK (numero_camisa BETWEEN 0 AND 99);
CREATE UNIQUE INDEX IF NOT EXISTS atleta_categoria_numero_unique
    ON atleta_categorias (categoria_id, numero_camisa) WHERE numero_camisa IS NOT NULL;

CREATE TABLE IF NOT EXISTS cargos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(60) NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS cargos_nome_unique ON cargos (lower(nome));
INSERT INTO cargos (nome) VALUES
    ('Staff'), ('Capitão'), ('Treinador'), ('Atleta'), ('Head Coach'), ('Manager')
ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS atleta_cargos_por_categoria (
    atleta_id BIGINT NOT NULL REFERENCES atletas(id) ON DELETE CASCADE,
    categoria_id BIGINT NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    cargo_id BIGINT NOT NULL REFERENCES cargos(id) ON DELETE RESTRICT,
    PRIMARY KEY (atleta_id, categoria_id, cargo_id)
);
CREATE INDEX IF NOT EXISTS atleta_cargos_por_categoria_categoria_idx ON atleta_cargos_por_categoria (categoria_id, atleta_id);

CREATE TABLE IF NOT EXISTS atleta_categoria_posicoes (
    atleta_categoria_id BIGINT NOT NULL,
    categoria_id BIGINT NOT NULL,
    posicao_id BIGINT NOT NULL,
    PRIMARY KEY (atleta_categoria_id, posicao_id),
    FOREIGN KEY (atleta_categoria_id, categoria_id)
        REFERENCES atleta_categorias(id, categoria_id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id, posicao_id)
        REFERENCES categoria_posicoes(categoria_id, id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS atleta_categorias_categoria_idx ON atleta_categorias (categoria_id, atleta_id);
CREATE INDEX IF NOT EXISTS atleta_categoria_posicoes_posicao_idx ON atleta_categoria_posicoes (posicao_id);

INSERT INTO categorias (modalidade, genero) VALUES
    ('X5', 'masculino'), ('X5', 'feminino'), ('X8', 'masculino'), ('X8', 'feminino')
ON CONFLICT DO NOTHING;

INSERT INTO categoria_sides (categoria_id, codigo, nome)
SELECT categorias.id, sides.codigo, sides.nome
FROM categorias
CROSS JOIN (VALUES ('ataque', 'Ataque'), ('defesa', 'Defesa'), ('time_especial', 'Time especial')) AS sides(codigo, nome)
ON CONFLICT (categoria_id, codigo) DO NOTHING;

INSERT INTO categoria_posicoes (categoria_id, side_id, nome)
SELECT categorias.id, categoria_sides.id, seed.nome
FROM (VALUES
    ('X5', 'masculino', 'defesa', 'LB'),
    ('X5', 'masculino', 'ataque', 'QB'),
    ('X5', 'masculino', 'ataque', 'WR'),
    ('X8', 'masculino', 'defesa', 'CB'),
    ('X8', 'masculino', 'defesa', 'DL'),
    ('X8', 'masculino', 'ataque', 'WR'),
    ('X8', 'masculino', 'time_especial', 'Kicker')
) AS seed(modalidade, genero, side_codigo, nome)
JOIN categorias ON lower(categorias.modalidade) = lower(seed.modalidade) AND categorias.genero = seed.genero
JOIN categoria_sides ON categoria_sides.categoria_id = categorias.id AND categoria_sides.codigo = seed.side_codigo
ON CONFLICT DO NOTHING;
FLAG_SETUP_ATLETAS_MIGRATION,
    'modules/atletas/Views/categories.php' => <<<'FLAG_SETUP_ATLETAS_CATEGORIES_VIEW'
<?php

declare(strict_types=1);
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Categorias e posições | <?= $escape($appName) ?></title>
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../assets/css/app.css', $config)) ?>">
    <link rel="stylesheet" href="<?= $escape(\App\Core\View::assetUrl('../modules/atletas/Assets/atletas.css', $config)) ?>">
</head>
<body class="admin-page">
<header class="admin-header"><a class="auth-brand" href="../admin/index.php"><?= $escape($appName) ?> · Painel</a><form method="post" action="../logout.php"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="button-secondary" type="submit">Sair</button></form></header>
<main class="admin-content athletes-page">
    <a class="athlete-link" href="atletas.php">← Voltar a atletas</a><p class="eyebrow">CONFIGURAÇÃO ESPORTIVA</p><h1>Categorias, posições e cargos</h1>
    <p class="athletes-lead">Cadastre as categorias, posições e funções da equipe. As posições e os números de camisa são independentes em cada categoria.</p>
    <?php if (isset($_GET['notice'])): ?><p class="athlete-notice" role="status"><?= $escape($_GET['notice']) ?></p><?php endif; ?>
    <?php if (is_string($error) && $error !== ''): ?><p class="athlete-errors" role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <div class="category-config-grid">
        <form class="category-config-card athlete-form" method="post" action="atletas.php?action=categories">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="create-category">
            <div class="category-form-heading"><span>01</span><div><h2>Nova categoria</h2><p>Crie uma modalidade para um gênero específico.</p></div></div>
            <label>Nome da modalidade<input name="modalidade" maxlength="40" placeholder="Ex.: X12" required></label>
            <label>Gênero da categoria<select name="genero" required><option value="">Selecione o gênero</option><option value="masculino">Masculino</option><option value="feminino">Feminino</option></select></label>
            <p class="category-form-help">Ataque, defesa e time especial serão criados automaticamente para esta categoria.</p><button class="athlete-button" type="submit">Criar categoria</button>
        </form>
        <form class="category-config-card athlete-form" method="post" action="atletas.php?action=categories">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="add-position">
            <div class="category-form-heading"><span>02</span><div><h2>Nova posição</h2><p>Associe cada posição a uma categoria e a um side.</p></div></div>
            <label>Categoria<select name="categoria_id" required><option value="">Selecione uma categoria</option><?php foreach ($categoryOptions as $category): ?><option value="<?= $category['id'] ?>"><?= $escape($category['modalidade']) ?> · <?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></option><?php endforeach; ?></select></label>
            <label>Side<select name="side" required><option value="">Selecione um side</option><option value="ataque">Ataque</option><option value="defesa">Defesa</option><option value="time_especial">Time especial</option></select></label>
            <label>Nome da posição<input name="nome_posicao" maxlength="60" placeholder="Ex.: QB, LB ou kicker" required></label>
            <p class="category-form-help">O nome da posição deve ser único na categoria, mesmo em sides diferentes.</p><button class="athlete-button" type="submit">Adicionar posição</button>
        </form>
        <form class="category-config-card athlete-form" method="post" action="atletas.php?action=categories">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="add-role">
            <div class="category-form-heading"><span>03</span><div><h2>Novo cargo</h2><p>Cadastre uma função para vincular a pessoas em cada categoria.</p></div></div>
            <label>Nome do cargo<input name="nome_cargo" maxlength="60" placeholder="Ex.: Preparador físico" required></label>
            <p class="category-form-help">Um atleta pode ter mais de um cargo, e os cargos podem mudar de uma categoria para outra.</p><button class="athlete-button" type="submit">Cadastrar cargo</button>
        </form>
    </div>
    <section class="category-catalog"><h2>Catálogo atual</h2>
    <?php foreach ($categoryOptions as $category): ?>
        <article class="category-catalog-card"><h3><?= $escape($category['modalidade']) ?> <span><?= $category['genero'] === 'masculino' ? 'Masculino' : 'Feminino' ?></span></h3>
            <div class="category-catalog-sides"><?php foreach ($category['sides'] as $side): ?><section><h4><?= $escape($side['nome']) ?></h4><?php if ($side['posicoes'] === []): ?><p>Nenhuma posição cadastrada</p><?php else: ?><ul><?php foreach ($side['posicoes'] as $position): ?><li><?= $escape($position['nome']) ?></li><?php endforeach; ?></ul><?php endif; ?></section><?php endforeach; ?></div>
        </article>
    <?php endforeach; ?>
    </section>
    <section class="cargo-catalog"><h2>Cargos cadastrados</h2>
        <article class="cargo-catalog-card"><div class="cargo-chip-list">
            <?php foreach ($roles as $role): ?><span class="cargo-chip"><?= $escape($role['nome']) ?></span><?php endforeach; ?>
        </div></article>
    </section>
</main>
</body>
</html>
FLAG_SETUP_ATLETAS_CATEGORIES_VIEW,
    'modules/atletas/Assets/atletas.js' => <<<'FLAG_SETUP_ATLETAS_JS'
(() => {
    'use strict';

    const genderField = document.getElementById('athlete-gender');
    if (!genderField) return;

    const categorySections = document.querySelectorAll('[data-category-gender]');
    const updateCategories = () => {
        const selectedGender = genderField.value;
        categorySections.forEach((section) => {
            const compatible = section.dataset.categoryGender === selectedGender;
            section.querySelectorAll('.athlete-player-control').forEach((control) => {
                control.disabled = !compatible;
            });
            section.querySelectorAll('[data-player-role="true"]').forEach((control) => {
                control.disabled = !compatible;
            });
        });
    };

    categorySections.forEach((section) => {
        section.addEventListener('change', (event) => {
            const control = event.target;
            const linkToggle = section.querySelector('.category-link-toggle');
            if (!linkToggle || !(control instanceof HTMLElement)) return;

            if (control === linkToggle && !linkToggle.checked) {
                section.querySelectorAll('.category-assignment-control').forEach((assignment) => {
                    if (assignment instanceof HTMLInputElement && assignment.type === 'checkbox') {
                        assignment.checked = false;
                    } else if (assignment instanceof HTMLSelectElement) {
                        assignment.value = '';
                    }
                });
                return;
            }

            if (control.matches('.category-assignment-control')
                && ((control instanceof HTMLInputElement && control.checked)
                    || (control instanceof HTMLSelectElement && control.value !== ''))) {
                linkToggle.checked = true;
            }
        });
    });

    genderField.addEventListener('change', updateCategories);
    updateCategories();
})();
FLAG_SETUP_ATLETAS_JS,
];

$templates['assets/css/app.css'] = str_replace(
    '.admin-content>p:last-child{color:var(--muted);line-height:1.7}',
    '.admin-content>p:last-child{color:var(--muted);line-height:1.7}.main-nav .nav-login,.main-nav .nav-login:hover{color:#fff}@media(max-width:700px){.main-nav{gap:12px}.main-nav a:first-child{display:none}.nav-login{min-height:42px;padding:0 14px}}',
    $templates['assets/css/app.css']
);
$templates['assets/css/app.css'] .= '.admin-card{max-width:560px;margin-top:32px;padding:24px;border:1px solid var(--line);border-radius:6px;background:#fff}.admin-card h2{margin:0 0 8px;font-size:22px}.admin-card p{margin:0 0 20px;color:var(--muted);line-height:1.6}.admin-card .button{min-height:44px}.hero h1 span{color:#7fc5f2}.button,.main-nav .nav-login{color:#fff}.button:hover,.main-nav .nav-login:hover,.hero-ball{color:var(--navy)}';

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
        'assets' => array_replace(['version' => '1.0.4'], is_array($legacyConfig['assets'] ?? null) ? $legacyConfig['assets'] : []),
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
if (in_array('--migrate-athletes', $arguments, true)) {
    require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
    $migrationPath = $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'atletas'
        . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '001_atletas.sql';
    $resolvedMigration = realpath($migrationPath);
    $migrationPrefix = $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'atletas' . DIRECTORY_SEPARATOR;
    if ($resolvedMigration === false || is_link($migrationPath)
        || !str_starts_with($resolvedMigration, $migrationPrefix) || !is_file($resolvedMigration)) {
        fwrite(STDERR, '[ERRO] Migration de atletas não encontrada dentro do projeto.' . PHP_EOL);
        exit(1);
    }
    $sql = file_get_contents($resolvedMigration);
    if (!is_string($sql) || trim($sql) === '') {
        fwrite(STDERR, '[ERRO] Migration de atletas vazia ou ilegível.' . PHP_EOL);
        exit(1);
    }
    $statementNumber = 0;
    try {
        $db = \App\Core\Database::connection();
        $db->beginTransaction();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $statementNumber++;
            $db->exec($statement);
        }
        $db->commit();
        echo '[OK] Estrutura do módulo de atletas criada/validada no banco selecionado.' . PHP_EOL;
    } catch (Throwable $exception) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $errorInfo = $exception instanceof PDOException ? ($exception->errorInfo ?? []) : [];
        $sqlState = (string) ($errorInfo[0] ?? $exception->getCode());
        fwrite(STDERR, '[ERRO] Migration revertida na instrução ' . $statementNumber
            . ' (SQLSTATE ' . $sqlState . '). Nenhuma alteração foi mantida.' . PHP_EOL);
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
echo 'Opções adicionais: --migrate-local-config, --migrate-auth, --migrate-athletes, --create-admin e --check-db.' . PHP_EOL;
