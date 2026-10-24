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
            "SELECT u.id, u.nome, u.email, u.password_hash, u.perfil,
                    COALESCE((to_jsonb(u)->>'senha_inicial_pendente')::boolean, FALSE) AS senha_inicial_pendente
             FROM usuarios u
             WHERE u.email_normalizado = :email AND u.ativo = TRUE
             LIMIT 1"
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
            'first_access' => in_array($user['senha_inicial_pendente'] ?? false, [true, 1, '1', 't', 'true'], true),
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
            "SELECT u.id, u.nome, u.email, u.perfil,
                    COALESCE((to_jsonb(u)->>'senha_inicial_pendente')::boolean, FALSE) AS senha_inicial_pendente
             FROM usuarios u WHERE u.id = :id AND u.ativo = TRUE LIMIT 1"
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
            'first_access' => in_array($user['senha_inicial_pendente'] ?? false, [true, 1, '1', 't', 'true'], true),
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
            header('Location: ../login', true, 303);
            exit;
        }
        if (!in_array($user['role'] ?? null, $roles, true)) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        return $user;
    }
}
