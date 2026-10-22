<?php

declare(strict_types=1);

namespace Modules\Eventos\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class EventModel
{
    /** @var array<string, bool> */
    private array $tables = [];

    public function all(): array
    {
        $categoryJoin = $this->hasTable('categorias') ? 'LEFT JOIN categorias c ON c.id = e.categoria_id' : '';
        $categoryName = $categoryJoin !== '' ? 'c.modalidade' : 'NULL::varchar';
        $categoryGender = $categoryJoin !== '' ? 'c.genero' : 'NULL::varchar';
        $athleteJoin = $this->hasTable('atletas') ? 'LEFT JOIN atletas a ON a.id = e.atleta_destaque_id' : '';
        $athleteName = $athleteJoin !== '' ? 'a.nome' : 'NULL::varchar';
        $presenceJoin = $this->hasTable('evento_presencas')
            ? "LEFT JOIN (SELECT evento_id, count(*) AS confirmados FROM evento_presencas WHERE resposta = 'confirmado' GROUP BY evento_id) p ON p.evento_id = e.id"
            : '';
        $presenceCount = $presenceJoin !== '' ? 'COALESCE(p.confirmados, 0)::integer' : '0::integer';
        $sql = "SELECT e.*, $categoryName AS categoria_modalidade, $categoryGender AS categoria_genero,
                       $presenceCount AS confirmados, $athleteName AS atleta_destaque
                FROM eventos e $categoryJoin $presenceJoin $athleteJoin
                ORDER BY CASE WHEN e.inicio >= CURRENT_TIMESTAMP THEN 0 ELSE 1 END,
                         CASE WHEN e.inicio >= CURRENT_TIMESTAMP THEN e.inicio END ASC,
                         CASE WHEN e.inicio < CURRENT_TIMESTAMP THEN e.inicio END DESC";
        return Database::connection()->query($sql)->fetchAll();
    }

    public function publicCalendar(): array
    {
        $categoryJoin = $this->hasTable('categorias') ? 'LEFT JOIN categorias c ON c.id = e.categoria_id' : '';
        $categoryName = $categoryJoin !== '' ? 'c.modalidade' : 'NULL::varchar';
        $categoryGender = $categoryJoin !== '' ? 'c.genero' : 'NULL::varchar';
        $athleteJoin = $this->hasTable('atletas') ? 'LEFT JOIN atletas a ON a.id = e.atleta_destaque_id' : '';
        $athleteName = $athleteJoin !== '' ? 'a.nome' : 'NULL::varchar';
        $presenceJoin = $this->hasTable('evento_presencas')
            ? "LEFT JOIN (SELECT evento_id, count(*) AS confirmados FROM evento_presencas WHERE resposta = 'confirmado' GROUP BY evento_id) p ON p.evento_id = e.id"
            : '';
        $presenceCount = $presenceJoin !== '' ? 'COALESCE(p.confirmados, 0)::integer' : '0::integer';
        $statement = Database::connection()->query(
            "SELECT e.id, e.tipo, e.titulo, e.adversario, e.inicio, e.fim, e.local, e.status,
                    $categoryName AS categoria_modalidade, $categoryGender AS categoria_genero,
                    e.placar_nos, e.placar_adversario, e.atleta_destaque_id,
                    e.release_ativo, e.release_texto, (e.release_imagem IS NOT NULL) AS tem_release_imagem,
                    $athleteName AS atleta_destaque, $presenceCount AS confirmados
             FROM eventos e $categoryJoin $presenceJoin $athleteJoin
             ORDER BY CASE WHEN e.inicio >= CURRENT_TIMESTAMP THEN 0 ELSE 1 END,
                      CASE WHEN e.inicio >= CURRENT_TIMESTAMP THEN e.inicio END ASC,
                      CASE WHEN e.inicio < CURRENT_TIMESTAMP THEN e.inicio END DESC
             LIMIT 100"
        );
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT e.*, (e.release_imagem IS NOT NULL) AS tem_release_imagem FROM eventos e WHERE e.id = :id'
        );
        $statement->execute(['id' => $id]);
        $event = $statement->fetch();
        return is_array($event) ? $event : null;
    }

    public function athletes(): array
    {
        if (!$this->hasTable('atletas')) return [];
        return Database::connection()->query('SELECT id, nome FROM atletas WHERE ativo = TRUE ORDER BY lower(nome), id')->fetchAll();
    }

    public function categories(): array
    {
        if (!$this->hasTable('categorias')) return [];
        return Database::connection()->query(
            'SELECT id, modalidade, genero FROM categorias WHERE ativo = TRUE ORDER BY lower(modalidade), genero'
        )->fetchAll();
    }

    public function categoryExists(?int $id): bool
    {
        if ($id === null) return true;
        if (!$this->hasTable('categorias')) return false;
        $statement = Database::connection()->prepare('SELECT 1 FROM categorias WHERE id = :id AND ativo = TRUE');
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    public function athleteExists(?int $id): bool
    {
        if ($id === null) return true;
        if (!$this->hasTable('atletas')) return false;
        $statement = Database::connection()->prepare('SELECT 1 FROM atletas WHERE id = :id AND ativo = TRUE');
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    private function hasTable(string $table): bool
    {
        if (!in_array($table, ['atletas', 'categorias', 'evento_presencas'], true)) return false;
        if (!array_key_exists($table, $this->tables)) {
            $statement = Database::connection()->prepare('SELECT to_regclass(:table_name) IS NOT NULL');
            $statement->execute(['table_name' => $table]);
            $this->tables[$table] = in_array($statement->fetchColumn(), [true, 1, '1', 't', 'true'], true);
        }
        return $this->tables[$table];
    }

    public function create(array $event, int $userId, ?string $imageBase64): int
    {
        $db = Database::connection();
        $imageSql = $imageBase64 !== null ? "decode(:image, 'base64')" : 'NULL';
        $params = $this->eventParams($event, $userId);
        $params['criado_por'] = $userId;
        if ($imageBase64 !== null) $params['image'] = $imageBase64;
        $statement = $db->prepare("INSERT INTO eventos (tipo,categoria_id,titulo,adversario,inicio,fim,local,status,placar_nos,placar_adversario,
            atleta_destaque_id,release_ativo,release_texto,release_imagem,criado_por,atualizado_por)
            VALUES (:tipo,:categoria_id,:titulo,:adversario,:inicio,:fim,:local,:status,:placar_nos,:placar_adversario,
            :atleta_destaque_id,:release_ativo,:release_texto,$imageSql,:criado_por,:atualizado_por) RETURNING id");
        $statement->execute($params);
        $id = (int) $statement->fetchColumn();
        if ($id < 1) throw new RuntimeException('O banco não retornou um identificador válido para o evento.');
        return $id;
    }

    public function update(int $id, array $event, int $userId, ?string $imageBase64, bool $removeImage): void
    {
        if ($id < 1) throw new RuntimeException('O identificador do evento deve ser positivo.');
        $imageSql = $imageBase64 !== null
            ? "decode(:image, 'base64')"
            : ($removeImage ? 'NULL' : 'release_imagem');
        $params = $this->eventParams($event, $userId);
        $params['id'] = $id;
        if ($imageBase64 !== null) $params['image'] = $imageBase64;
        $statement = Database::connection()->prepare("UPDATE eventos SET tipo=:tipo,categoria_id=:categoria_id,titulo=:titulo,adversario=:adversario,inicio=:inicio,fim=:fim,
            local=:local,status=:status,placar_nos=:placar_nos,placar_adversario=:placar_adversario,
            atleta_destaque_id=:atleta_destaque_id,release_ativo=:release_ativo,release_texto=:release_texto,
            release_imagem=$imageSql,atualizado_por=:atualizado_por,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
        $statement->execute($params);
        if ($statement->rowCount() === 0 && $this->find($id) === null) throw new RuntimeException('Evento não encontrado.');
    }

    private function eventParams(array $event, int $userId): array
    {
        return [
            'tipo' => $event['tipo'], 'categoria_id' => $event['categoria_id'],
            'titulo' => $event['titulo'], 'adversario' => $event['adversario'],
            'inicio' => $event['inicio'], 'fim' => $event['fim'], 'local' => $event['local'],
            'status' => $event['status'], 'placar_nos' => $event['placar_nos'],
            'placar_adversario' => $event['placar_adversario'], 'atleta_destaque_id' => $event['atleta_destaque_id'],
            'release_ativo' => $event['release_ativo'] ? 'true' : 'false',
            'release_texto' => $event['release_texto'], 'atualizado_por' => $userId,
        ];
    }

    public function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM eventos WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Evento não encontrado.');
    }

    public function releaseImage(int $id): ?string
    {
        $statement = Database::connection()->prepare("SELECT encode(release_imagem, 'base64') FROM eventos WHERE id = :id AND release_ativo = TRUE AND release_imagem IS NOT NULL");
        $statement->execute(['id' => $id]);
        $image = $statement->fetchColumn();
        return is_string($image) ? base64_decode($image, true) ?: null : null;
    }
}
