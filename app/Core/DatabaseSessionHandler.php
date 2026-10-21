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