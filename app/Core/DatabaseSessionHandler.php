<?php

declare(strict_types=1);

namespace App\Core;

use PDOException;
use RuntimeException;
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
            error_log('Falha ao ler sessão no banco: ' . self::safeDiagnostic($exception));
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
            error_log('Falha ao gravar sessão no banco: ' . self::safeDiagnostic($exception));
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            return Database::connection()->prepare('DELETE FROM app_sessions WHERE session_id = :id')
                ->execute(['id' => $id]);
        } catch (Throwable $exception) {
            error_log('Falha ao remover sessão do banco: ' . self::safeDiagnostic($exception));
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
            error_log('Falha ao limpar sessões do banco: ' . self::safeDiagnostic($exception));
            return false;
        }
    }

    /** Loga mensagens sanitizadas do Core ou códigos SQLSTATE, nunca dados de conexão. */
    private static function safeDiagnostic(Throwable $exception): string
    {
        if ($exception instanceof RuntimeException) {
            $safeMessages = [
                'A configuração privada do banco é inválida.',
                'A configuração do banco não está disponível.',
                'O driver PDO PostgreSQL não está habilitado.',
                'Formato de conexão PostgreSQL inválido.',
                'Parâmetros de conexão PostgreSQL inválidos.',
                'Hostname PostgreSQL inválido.',
                'Identificador do endpoint PostgreSQL inválido.',
                'Conexão recusada pelo PostgreSQL: autenticação inválida. Confira a configuração privada.',
                'A tentativa de conexão ao PostgreSQL falhou antes da consulta. Confira a disponibilidade do banco e da rede.',
            ];
            $message = $exception->getMessage();
            if (in_array($message, $safeMessages, true)
                || preg_match('/\AFalha do driver PostgreSQL \(SQLSTATE [A-Z0-9]{5}\)\.\z/', $message) === 1
            ) {
                return $message;
            }
        }

        if ($exception instanceof PDOException) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            if (preg_match('/\A[A-Z0-9]{5}\z/', $sqlState) === 1) {
                return 'PDOException (SQLSTATE ' . $sqlState . ').';
            }
        }

        return get_class($exception);
    }
}
