<?php

declare(strict_types=1);

namespace Modules\Atletas\Models;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class AthleteModel
{
    public function count(string $search, bool $activeOnly): int
    {
        $conditions = [];
        if ($activeOnly) $conditions[] = 'ativo = TRUE';
        if ($search !== '') $conditions[] = '(nome ILIKE :pattern_name OR apelido ILIKE :pattern_alias)';
        $sql = 'SELECT count(*) FROM atletas' . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions));
        $statement = Database::connection()->prepare($sql);
        if ($search !== '') {
            $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $statement->bindValue('pattern_name', $pattern, PDO::PARAM_STR);
            $statement->bindValue('pattern_alias', $pattern, PDO::PARAM_STR);
        }
        $statement->execute();
        return (int) $statement->fetchColumn();
    }

    public function list(string $search, bool $activeOnly, int $limit, int $offset): array
    {
        $conditions = [];
        if ($activeOnly) $conditions[] = 'ativo = TRUE';
        if ($search !== '') $conditions[] = '(nome ILIKE :pattern_name OR apelido ILIKE :pattern_alias)';
        $sql = "SELECT id, nome, apelido, genero, data_nascimento, ativo, (foto IS NOT NULL) AS tem_foto,
                       CASE WHEN foto IS NOT NULL THEN md5(encode(foto, 'base64')) END AS foto_version
                FROM atletas";
        if ($conditions !== []) $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' ORDER BY lower(nome), id LIMIT :limit OFFSET :offset';
        $statement = Database::connection()->prepare($sql);
        if ($search !== '') {
            $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $statement->bindValue('pattern_name', $pattern, PDO::PARAM_STR);
            $statement->bindValue('pattern_alias', $pattern, PDO::PARAM_STR);
        }
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
        $statement = Database::connection()->prepare("SELECT *, (foto IS NOT NULL) AS tem_foto,
            CASE WHEN foto IS NOT NULL THEN md5(encode(foto, 'base64')) END AS foto_version
            FROM atletas WHERE id = :id");
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

    public function updateCategory(int $id, string $modality, string $gender): void
    {
        $statement = Database::connection()->prepare('UPDATE categorias SET modalidade = :name, genero = :gender WHERE id = :id AND ativo = TRUE RETURNING id');
        $statement->execute(['id' => $id, 'name' => $modality, 'gender' => $gender]);
        if ($statement->fetchColumn() === false) throw new RuntimeException('Categoria não encontrada.');
    }

    public function updatePosition(int $id, int $categoryId, string $sideCode, string $name): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE categoria_posicoes p SET side_id = s.id, nome = :name FROM categoria_sides s
             WHERE p.id = :id AND p.categoria_id = :category AND p.ativo = TRUE
               AND s.categoria_id = p.categoria_id AND s.codigo = :side RETURNING p.id'
        );
        $statement->execute(['id' => $id, 'category' => $categoryId, 'side' => $sideCode, 'name' => $name]);
        if ($statement->fetchColumn() === false) throw new RuntimeException('Posição não encontrada ou categoria/side inválido.');
    }

    public function updateRole(int $id, string $name): void
    {
        $statement = Database::connection()->prepare('UPDATE cargos SET nome = :name WHERE id = :id AND ativo = TRUE RETURNING id');
        $statement->execute(['id' => $id, 'name' => $name]);
        if ($statement->fetchColumn() === false) throw new RuntimeException('Cargo não encontrado.');
    }

    public function deleteCategory(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM categorias WHERE id = :id AND ativo = TRUE');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Categoria não encontrada.');
    }

    public function deletePosition(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM categoria_posicoes WHERE id = :id AND ativo = TRUE');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Posição não encontrada.');
    }

    public function deleteRole(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM cargos WHERE id = :id AND ativo = TRUE');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Cargo não encontrado.');
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
