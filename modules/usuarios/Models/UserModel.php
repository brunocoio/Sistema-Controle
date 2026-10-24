<?php

declare(strict_types=1);

namespace Modules\Usuarios\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class UserModel
{
    public function schemaAvailable(): bool
    {
        try {
            $statement = Database::connection()->query(
                "SELECT (SELECT count(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name='usuarios' AND column_name='cpf') = 1
                    AND (SELECT count(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name='usuarios' AND column_name='funcoes') = 1
                    AND (SELECT count(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name='usuarios' AND column_name='senha_inicial_pendente') = 1
                    AND (SELECT count(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name='atletas' AND column_name='usuario_id') = 1"
            );
            return in_array($statement->fetchColumn(), [true, 1, '1', 't', 'true'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function all(): array
    {
        $users = Database::connection()->query(
            'SELECT u.id, u.nome, u.email, u.cpf, u.perfil, u.funcoes, u.ativo, u.criado_em,
                    a.id AS atleta_id, a.nome AS atleta_nome, a.ativo AS atleta_ativo
             FROM usuarios u LEFT JOIN atletas a ON a.usuario_id = u.id
             ORDER BY lower(u.nome), u.id'
        )->fetchAll();
        foreach ($users as &$user) $user['funcoes'] = $this->decodeFunctions($user['funcoes'] ?? null);
        unset($user);
        return $users;
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT u.id, u.nome, u.email, u.cpf, u.perfil, u.funcoes, u.ativo,
                    a.id AS atleta_id, a.nome AS atleta_nome
             FROM usuarios u LEFT JOIN atletas a ON a.usuario_id = u.id WHERE u.id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) return null;
        $row['funcoes'] = $this->decodeFunctions($row['funcoes'] ?? null);
        return $row;
    }

    public function availableAthletes(?int $userId = null): array
    {
        $statement = Database::connection()->prepare(
            'SELECT a.id, a.nome, a.email, a.cpf FROM atletas a
             WHERE a.usuario_id IS NULL OR a.usuario_id = :user_id
             ORDER BY lower(a.nome), a.id'
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function athlete(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT id, nome, email, cpf, usuario_id FROM atletas WHERE id=:id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $data, ?int $athleteId): int
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $statement = $db->prepare(
                'INSERT INTO usuarios (nome, email, cpf, password_hash, perfil, funcoes, senha_inicial_pendente, ativo)
                 VALUES (:name, :email, :cpf, :password, :role, CAST(:functions AS JSONB), TRUE, :active) RETURNING id'
            );
            $functions = array_values(array_intersect(['staff', 'atleta'], $data['funcoes']));
            $statement->execute([
                'name' => $data['nome'], 'email' => $data['email'], 'cpf' => $data['cpf'],
                'password' => password_hash($data['cpf'], PASSWORD_DEFAULT), 'role' => $this->legacyProfile($functions),
                'functions' => json_encode($functions, JSON_THROW_ON_ERROR),
                'active' => $data['ativo'] ? 'true' : 'false',
            ]);
            $id = (int) $statement->fetchColumn();
            if ($athleteId !== null) $this->linkAthlete($db, $id, $athleteId, $data);
            $db->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function update(int $id, array $data, ?string $password, ?int $athleteId): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $current = $this->find($id);
            if ($current === null) throw new RuntimeException('Usuário não encontrado.');
            $currentAthleteId = $current['atleta_id'] === null ? null : (int) $current['atleta_id'];
            if ($currentAthleteId !== null && $athleteId !== $currentAthleteId) {
                throw new RuntimeException('O vínculo do atleta não pode ser removido. Desative a carreira do atleta para preservar o histórico.');
            }
            if ($currentAthleteId === null && $athleteId !== null) $this->linkAthlete($db, $id, $athleteId, $data);
            $functions = array_values(array_intersect(['staff', 'atleta'], $data['funcoes'] ?? []));
            $role = (string) $current['perfil'] === 'admin' ? 'admin' : $this->legacyProfile($functions);
            $sql = 'UPDATE usuarios SET nome=:name, email=:email, cpf=:cpf, perfil=:role, funcoes=CAST(:functions AS JSONB), ativo=:active, atualizado_em=CURRENT_TIMESTAMP';
            if ($password !== null) $sql .= ', password_hash=:password, senha_inicial_pendente=FALSE';
            $sql .= ' WHERE id=:id';
            $statement = $db->prepare($sql);
            $params = [
                'id' => $id, 'name' => $data['nome'], 'email' => $data['email'], 'cpf' => $data['cpf'],
                'role' => $role, 'functions' => json_encode($functions, JSON_THROW_ON_ERROR),
                'active' => $data['ativo'] ? 'true' : 'false',
            ];
            if ($password !== null) $params['password'] = password_hash($password, PASSWORD_DEFAULT);
            $statement->execute($params);
            if ($currentAthleteId !== null) {
                $sync = $db->prepare('UPDATE atletas SET email=:email, cpf=:cpf, atualizado_em=CURRENT_TIMESTAMP WHERE id=:id');
                $sync->execute(['email' => $data['email'], 'cpf' => $data['cpf'], 'id' => $currentAthleteId]);
            }
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function toggle(int $id): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $current = $db->prepare('SELECT id, perfil, ativo FROM usuarios WHERE id=:id FOR UPDATE');
            $current->execute(['id' => $id]);
            $user = $current->fetch();
            if (!is_array($user)) throw new RuntimeException('Usuário não encontrado.');
            if ($user['perfil'] === 'admin' && in_array($user['ativo'], [true, 1, '1', 't', 'true'], true)) {
                $count = (int) $db->query("SELECT count(*) FROM usuarios WHERE perfil='admin' AND ativo=TRUE")->fetchColumn();
                if ($count <= 1) throw new RuntimeException('Não é possível desativar o último administrador do sistema.');
            }
            $statement = $db->prepare('UPDATE usuarios SET ativo = NOT ativo, atualizado_em = CURRENT_TIMESTAMP WHERE id = :id');
            $statement->execute(['id' => $id]);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    private function decodeFunctions(mixed $value): array
    {
        if (is_array($value)) return array_values(array_intersect(['staff', 'atleta'], $value));
        if (!is_string($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values(array_intersect(['staff', 'atleta'], $decoded)) : [];
    }

    private function legacyProfile(array $functions): string
    {
        return in_array('staff', $functions, true) ? 'staff' : 'atleta';
    }

    private function linkAthlete(PDO $db, int $userId, int $athleteId, array $data): void
    {
        $statement = $db->prepare('SELECT id, email, cpf, usuario_id FROM atletas WHERE id=:id FOR UPDATE');
        $statement->execute(['id' => $athleteId]);
        $athlete = $statement->fetch();
        if (!is_array($athlete)) throw new RuntimeException('Atleta não encontrado.');
        if ($athlete['usuario_id'] !== null && (int) $athlete['usuario_id'] !== $userId) {
            throw new RuntimeException('Este atleta já está vinculado a outro usuário.');
        }
        if ((is_string($athlete['email']) && $athlete['email'] !== '' && strtolower($athlete['email']) !== strtolower($data['email']))
            || (is_string($athlete['cpf']) && $athlete['cpf'] !== '' && $athlete['cpf'] !== $data['cpf'])) {
            throw new RuntimeException('Para vincular, o e-mail e o CPF do usuário devem ser iguais aos dados do atleta.');
        }
        $link = $db->prepare('UPDATE atletas SET usuario_id=:user_id, email=:email, cpf=:cpf WHERE id=:athlete_id AND (usuario_id IS NULL OR usuario_id=:same_user)');
        $link->execute(['user_id' => $userId, 'email' => $data['email'], 'cpf' => $data['cpf'], 'athlete_id' => $athleteId, 'same_user' => $userId]);
        if ($link->rowCount() !== 1) throw new RuntimeException('Não foi possível criar o vínculo com o atleta.');
    }
}
