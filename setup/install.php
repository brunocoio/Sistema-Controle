<?php

declare(strict_types=1);

/**
 * Sistema FLAG — setup e migrations
 *
 * Este script configura diretórios de runtime e aplica os esquemas declarados
 * em cada módulo. O código da aplicação é mantido em app/ e modules/; este
 * arquivo não gera nem sobrescreve Controllers, Views, CSS ou JavaScript.
 * Cada conjunto de migrations roda em uma transação e pode ser reaplicado.
 *
 * Uso: php setup/install.php --help
 */

if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80000) {
    fwrite(STDERR, "[ERRO] Execute pela linha de comando com PHP 8.0 ou superior.\n");
    exit(1);
}

$root = realpath(dirname(__DIR__));
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "[ERRO] Não foi possível localizar a raiz do projeto.\n");
    exit(1);
}

$options = array_slice($argv, 1);
$knownOptions = [
    '--help', '--check-db', '--migrate-local-config', '--migrate-auth',
    '--migrate-athletes', '--migrate-events', '--migrate-users', '--migrate-system',
    '--migrate-all', '--create-admin',
];
foreach ($options as $option) {
    if (!in_array($option, $knownOptions, true)) {
        fwrite(STDERR, "[ERRO] Opção desconhecida. Consulte --help.\n");
        exit(2);
    }
}

$migrationSets = [
    '--migrate-system' => ['Sistema padrão', 'app/Database/migrations'],
    '--migrate-auth' => ['Autenticação', 'modules/autenticacao/Database/migrations'],
    '--migrate-athletes' => ['Atletas', 'modules/atletas/Database/migrations'],
    '--migrate-events' => ['Eventos', 'modules/eventos/Database/migrations'],
    '--migrate-users' => ['Usuários', 'modules/usuarios/Database/migrations'],
];

if (in_array('--help', $options, true)) {
    echo "Setup do Sistema FLAG\n\n";
    echo "php setup/install.php                       Prepara diretórios de runtime\n";
    echo "php setup/install.php --check-db           Valida a conexão PostgreSQL\n";
    echo "php setup/install.php --migrate-system     Aplica o esquema padrão\n";
    echo "php setup/install.php --migrate-auth       Aplica o esquema de autenticação\n";
    echo "php setup/install.php --migrate-athletes   Aplica o esquema de atletas\n";
    echo "php setup/install.php --migrate-events     Aplica o esquema de eventos\n";
    echo "php setup/install.php --migrate-users      Aplica o esquema de usuários\n";
    echo "php setup/install.php --migrate-all        Aplica todos os esquemas\n";
    echo "php setup/install.php --migrate-local-config Migra configuração local antiga\n";
    echo "php setup/install.php --create-admin      Cria o administrador inicial\n\n";
    echo "Padrões: app/Core e assets globais são compartilhados. Cada módulo mantém seu código e banco próprios; nenhum módulo depende de outro.\n";
    exit(0);
}

$rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$ensureRuntimeDirectory = static function (string $relativePath) use ($root, $rootPrefix): void {
    if ($relativePath === '' || str_contains($relativePath, '..') || str_contains($relativePath, ':')
        || str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\')) {
        throw new RuntimeException('Caminho de diretório inválido.');
    }
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_link($path)) throw new RuntimeException('Link simbólico detectado no diretório de runtime.');
    if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
        throw new RuntimeException('Não foi possível preparar um diretório de runtime.');
    }
    $resolved = realpath($path);
    if ($resolved === false || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $rootPrefix)) {
        throw new RuntimeException('Diretório de runtime fora do projeto.');
    }
};

try {
    foreach (['storage', 'storage/logs', 'storage/uploads', 'storage/cache'] as $directory) {
        $ensureRuntimeDirectory($directory);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERRO] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

foreach (['app/bootstrap.php', 'app/Core/Database.php', 'config/conf_sample.php'] as $requiredFile) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $requiredFile);
    if (!is_file($path) || is_link($path)) {
        fwrite(STDERR, '[ERRO] Arquivo obrigatório ausente ou inválido: ' . $requiredFile . PHP_EOL);
        exit(1);
    }
}

if (in_array('--migrate-local-config', $options, true)) {
    $privatePath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'conf.php';
    if (file_exists($privatePath) || is_link($privatePath)) {
        fwrite(STDERR, "[ERRO] config/conf.php já existe; nenhum arquivo foi alterado.\n");
        exit(1);
    }
    $legacyPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    $legacy = [];
    if (is_file($legacyPath) && !is_link($legacyPath)) {
        $loaded = require $legacyPath;
        if (!is_array($loaded)) {
            fwrite(STDERR, "[ERRO] A configuração antiga é inválida.\n");
            exit(1);
        }
        $legacy = $loaded;
    }
    $url = is_string($legacy['database']['url'] ?? null) ? $legacy['database']['url'] : null;
    $legacyEnv = $root . DIRECTORY_SEPARATOR . '.env.local';
    if (($url === null || $url === '') && is_file($legacyEnv) && !is_link($legacyEnv)) {
        $contents = file_get_contents($legacyEnv);
        if (is_string($contents) && preg_match('/(?m)^\s*DATABASE_URL\s*=\s*(.*?)\s*$/', $contents, $matches) === 1) {
            $url = trim($matches[1], " \t\n\r\0\x0B\"'");
        }
    }
    if ($url === null || $url === '') $url = getenv('DATABASE_URL') ?: null;
    if (is_string($url) && $url !== '') {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)
            || empty($parts['host']) || empty($parts['user']) || !array_key_exists('pass', $parts)
            || preg_match('/[\r\n\x00]/', $url) === 1) {
            fwrite(STDERR, "[ERRO] A URL PostgreSQL antiga não passou na validação.\n");
            exit(1);
        }
    }
    $sample = require $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'conf_sample.php';
    $config = array_replace_recursive($sample, $legacy);
    if (is_string($url) && $url !== '') $config['database']['url'] = $url;
    $handle = @fopen($privatePath, 'x');
    if ($handle === false) {
        fwrite(STDERR, "[ERRO] Não foi possível criar config/conf.php.\n");
        exit(1);
    }
    $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    $written = fwrite($handle, $php);
    $flushed = fflush($handle);
    fclose($handle);
    if ($written !== strlen($php) || !$flushed) {
        @unlink($privatePath);
        fwrite(STDERR, "[ERRO] Não foi possível validar a configuração migrada.\n");
        exit(1);
    }
    echo "[OK] Configuração migrada para config/conf.php. Os arquivos antigos foram preservados.\n";
}

$requestedSets = [];
if (in_array('--migrate-all', $options, true)) {
    $requestedSets = array_values($migrationSets);
} else {
    foreach ($migrationSets as $option => $set) {
        if (in_array($option, $options, true)) $requestedSets[] = $set;
    }
}

if ($requestedSets !== []) {
    require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
    foreach ($requestedSets as [$label, $relativeDirectory]) {
        $directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        $resolvedDirectory = realpath($directory);
        $directoryPrefix = $resolvedDirectory === false ? '' : rtrim($resolvedDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $files = glob($directory . DIRECTORY_SEPARATOR . '[0-9][0-9][0-9]_*.sql') ?: [];
        sort($files, SORT_STRING);
        if ($directoryPrefix === '' || $files === []) {
            fwrite(STDERR, '[ERRO] Migration indisponível para ' . $label . '.' . PHP_EOL);
            exit(1);
        }
        $scripts = [];
        foreach ($files as $file) {
            $resolvedFile = realpath($file);
            if ($resolvedFile === false || is_link($file) || !is_file($resolvedFile)
                || !str_starts_with($resolvedFile, $directoryPrefix)) {
                fwrite(STDERR, '[ERRO] Arquivo de migration inválido para ' . $label . '.' . PHP_EOL);
                exit(1);
            }
            $sql = file_get_contents($resolvedFile);
            if (!is_string($sql) || trim($sql) === '') {
                fwrite(STDERR, '[ERRO] Migration vazia ou ilegível para ' . $label . '.' . PHP_EOL);
                exit(1);
            }
            $scripts[] = $sql;
        }
        $statementNumber = 0;
        try {
            $db = \App\Core\Database::connection();
            $db->beginTransaction();
            foreach ($scripts as $sql) {
                foreach (array_filter(array_map('trim', explode(';', $sql)), static fn(string $statement): bool => $statement !== '') as $statement) {
                    $statementNumber++;
                    $db->exec($statement);
                }
            }
            $db->commit();
            echo '[OK] Esquema consolidado aplicado: ' . $label . '.' . PHP_EOL;
        } catch (Throwable $exception) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $errorInfo = $exception instanceof PDOException ? ($exception->errorInfo ?? []) : [];
            $sqlState = (string) ($errorInfo[0] ?? $exception->getCode());
            if (preg_match('/\A[A-Z0-9]{5}\z/', $sqlState) !== 1) $sqlState = 'UNKNOWN';
            fwrite(STDERR, '[ERRO] Migration de ' . $label . ' revertida na instrução ' . $statementNumber
                . ' (SQLSTATE ' . $sqlState . '). Nenhuma alteração desse módulo foi mantida.' . PHP_EOL);
            exit(1);
        }
    }
}

if (in_array('--check-db', $options, true)) {
    try {
        require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
        $result = \App\Core\Database::connection()->query('SELECT 1')->fetchColumn();
        if ((string) $result !== '1') throw new RuntimeException('Resultado inesperado.');
        echo "[OK] Conexão PostgreSQL validada.\n";
    } catch (Throwable) {
        fwrite(STDERR, "[ERRO] Não foi possível validar a conexão PostgreSQL. Confira configuração e disponibilidade do banco.\n");
        exit(1);
    }
}

if (in_array('--create-admin', $options, true)) {
    require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
    $isProduction = strtolower((string) (getenv('APP_ENV') ?: '')) === 'production' || getenv('VERCEL') === '1';
    $password = getenv('FLAG_ADMIN_PASSWORD');
    $usingDefault = !is_string($password) || $password === '';
    if ($usingDefault && $isProduction) {
        fwrite(STDERR, "[ERRO] Defina FLAG_ADMIN_PASSWORD para criar uma conta em produção.\n");
        exit(1);
    }
    if ($usingDefault) {
        $password = 'RootTest2026';
        $name = 'Root Teste';
        $email = 'root@localhost.test';
        fwrite(STDOUT, "[INFO] Criando conta local padrão root@localhost.test.\n");
    } else {
        if (strlen($password) < 8 || strlen($password) > 4096) {
            putenv('FLAG_ADMIN_PASSWORD');
            fwrite(STDERR, "[ERRO] FLAG_ADMIN_PASSWORD deve ter entre 8 e 4096 caracteres.\n");
            exit(1);
        }
        fwrite(STDOUT, 'Nome do administrador: ');
        $name = trim((string) fgets(STDIN));
        fwrite(STDOUT, 'E-mail do administrador: ');
        $email = strtolower(trim((string) fgets(STDIN)));
    }
    if ($name === '' || strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
        putenv('FLAG_ADMIN_PASSWORD');
        fwrite(STDERR, "[ERRO] Nome ou e-mail inválido.\n");
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
        echo $statement->rowCount() === 0
            ? "[INFO] A conta já existe; nenhum dado foi alterado.\n"
            : "[OK] Administrador criado.\n";
    } catch (Throwable) {
        unset($password);
        putenv('FLAG_ADMIN_PASSWORD');
        fwrite(STDERR, "[ERRO] Não foi possível criar o administrador. Confira a migration de autenticação e o e-mail.\n");
        exit(1);
    }
}

echo "[OK] Setup concluído. Código existente preservado.\n";
