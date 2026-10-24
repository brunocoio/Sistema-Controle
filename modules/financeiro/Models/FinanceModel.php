<?php

declare(strict_types=1);

namespace Modules\Financeiro\Models;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class FinanceModel
{
    public function schemaAvailable(): bool
    {
        try {
            $statement = Database::connection()->query(
                "SELECT to_regclass('financeiro_config') IS NOT NULL
                    AND to_regclass('financeiro_categorias') IS NOT NULL
                    AND to_regclass('financeiro_lancamentos') IS NOT NULL
                    AND to_regclass('financeiro_regras') IS NOT NULL
                    AND to_regclass('financeiro_isencoes') IS NOT NULL
                    AND to_regclass('financeiro_obrigacoes') IS NOT NULL
                    AND to_regclass('financeiro_pagamentos') IS NOT NULL
                    AND to_regclass('financeiro_negociacoes') IS NOT NULL
                    AND to_regclass('financeiro_historico') IS NOT NULL"
            );
            return in_array($statement->fetchColumn(), [true, 1, '1', 't', 'true'], true);
        } catch (Throwable) {
            return false;
        }
    }

    public function managerData(): array
    {
        $db = Database::connection();
        $totals = $db->query(
            "SELECT COALESCE(SUM(valor) FILTER (WHERE tipo='entrada' AND date_trunc('month', data_movimento) = date_trunc('month', CURRENT_DATE)), 0) AS entradas_mes,
                    COALESCE(SUM(valor) FILTER (WHERE tipo='saida' AND date_trunc('month', data_movimento) = date_trunc('month', CURRENT_DATE)), 0) AS saidas_mes,
                    COALESCE(SUM(CASE WHEN tipo='entrada' THEN valor ELSE -valor END), 0) AS saldo
             FROM financeiro_lancamentos"
        )->fetch() ?: [];
        $categories = $db->query('SELECT id, nome, tipo, ativa FROM financeiro_categorias ORDER BY tipo, lower(nome)')->fetchAll();
        $rules = $db->query('SELECT id, nome, modalidade, valor, dia_vencimento, vigencia_inicio, vigencia_fim, ativa FROM financeiro_regras ORDER BY vigencia_inicio DESC, id DESC')->fetchAll();
        $users = $db->query(
            "SELECT u.id, u.nome, u.email, a.id AS atleta_id
             FROM usuarios u JOIN atletas a ON a.usuario_id=u.id
             WHERE u.ativo=TRUE AND a.ativo=TRUE ORDER BY lower(u.nome),u.id"
        )->fetchAll();
        $events = $db->query(
            "SELECT id, titulo, tipo, inicio FROM eventos
             WHERE status <> 'cancelado' AND tipo IN ('jogo','amistoso') AND inicio >= CURRENT_TIMESTAMP
             ORDER BY inicio, id LIMIT 200"
        )->fetchAll();
        $obligations = $db->query(
            "SELECT o.id, o.usuario_id, o.atleta_nome, o.evento_titulo, o.referencia,
                    o.valor_original, o.valor_negociado, o.vencimento, o.status, o.motivo_status,
                    u.nome AS usuario_nome,
                    COALESCE((SELECT SUM(p.valor) FROM financeiro_pagamentos p WHERE p.obrigacao_id=o.id AND p.status='confirmado'),0) AS pago,
                    COALESCE((SELECT SUM(p.valor) FROM financeiro_pagamentos p WHERE p.obrigacao_id=o.id AND p.status='aguardando'),0) AS aguardando
             FROM financeiro_obrigacoes o JOIN usuarios u ON u.id=o.usuario_id
             WHERE o.status NOT IN ('isento','cancelado')
             ORDER BY o.vencimento, lower(o.atleta_nome), o.id DESC LIMIT 500"
        )->fetchAll();
        $paymentRequests = $db->query(
            "SELECT p.id, p.obrigacao_id, p.valor, p.data_pagamento, p.descricao, p.criado_em,
                    o.atleta_nome, o.referencia, u.nome AS usuario_nome
             FROM financeiro_pagamentos p JOIN financeiro_obrigacoes o ON o.id=p.obrigacao_id
             JOIN usuarios u ON u.id=p.registrado_por
             WHERE p.status='aguardando' ORDER BY p.criado_em, p.id LIMIT 200"
        )->fetchAll();
        $negotiationRequests = $db->query(
            "SELECT n.id,n.obrigacao_id,n.valor_proposto,n.mensagem,n.criado_em,o.atleta_nome,
                    o.evento_titulo,o.referencia,o.valor_original,o.valor_negociado,u.nome AS usuario_nome
             FROM financeiro_negociacoes n JOIN financeiro_obrigacoes o ON o.id=n.obrigacao_id
             JOIN usuarios u ON u.id=n.solicitante_id
             WHERE n.status='aguardando' ORDER BY n.criado_em,n.id LIMIT 200"
        )->fetchAll();
        $exemptions = $db->query(
            "SELECT i.id, i.usuario_id, i.vigencia_inicio, i.vigencia_fim, i.motivo, i.ativa, u.nome
             FROM financeiro_isencoes i JOIN usuarios u ON u.id=i.usuario_id
             ORDER BY i.ativa DESC, i.vigencia_inicio DESC, lower(u.nome) LIMIT 200"
        )->fetchAll();
        $movements = $db->query(
            "SELECT l.id, l.tipo, l.valor, l.descricao, l.data_movimento, l.origem, c.nome AS categoria
             FROM financeiro_lancamentos l JOIN financeiro_categorias c ON c.id=l.categoria_id
             ORDER BY l.data_movimento DESC, l.id DESC LIMIT 100"
        )->fetchAll();
        $config = $db->query('SELECT pix_chave, pix_nome FROM financeiro_config WHERE id=1')->fetch() ?: [];
        return compact('totals', 'categories', 'rules', 'users', 'events', 'obligations', 'paymentRequests', 'negotiationRequests', 'exemptions', 'movements', 'config');
    }

    public function teamStatuses(): array
    {
        return Database::connection()->query(
            "SELECT a.id, a.nome, a.apelido, u.id AS usuario_id,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM financeiro_obrigacoes o
                        WHERE o.usuario_id=u.id AND o.vencimento < CURRENT_DATE
                          AND o.status IN ('pendente','parcial','postergado','negociado')
                          AND COALESCE(o.valor_negociado,o.valor_original) > COALESCE((
                              SELECT SUM(p.valor) FROM financeiro_pagamentos p
                              WHERE p.obrigacao_id=o.id AND p.status='confirmado'
                          ),0)
                    ) THEN 'irregular' ELSE 'regular' END AS situacao
             FROM atletas a JOIN usuarios u ON u.id=a.usuario_id
             WHERE a.ativo=TRUE AND u.ativo=TRUE
             ORDER BY lower(a.nome), a.id"
        )->fetchAll();
    }

    public function account(int $userId): array
    {
        $db = Database::connection();
        $statement = $db->prepare(
            "SELECT a.id AS atleta_id, a.nome AS atleta_nome, a.ativo AS atleta_ativo
             FROM usuarios u LEFT JOIN atletas a ON a.usuario_id=u.id WHERE u.id=:id LIMIT 1"
        );
        $statement->execute(['id' => $userId]);
        $athlete = $statement->fetch() ?: null;
        $pix = $db->query('SELECT pix_chave, pix_nome FROM financeiro_config WHERE id=1')->fetch() ?: [];
        $statement = $db->prepare(
            "SELECT o.id, o.atleta_nome, o.evento_titulo, o.referencia, o.valor_original,
                    o.valor_negociado, o.vencimento, o.status, o.motivo_status, o.criado_em,
                    COALESCE((SELECT SUM(p.valor) FROM financeiro_pagamentos p WHERE p.obrigacao_id=o.id AND p.status='confirmado'),0) AS pago,
                    EXISTS(SELECT 1 FROM financeiro_pagamentos p WHERE p.obrigacao_id=o.id AND p.status='aguardando') AS pagamento_aguardando
             FROM financeiro_obrigacoes o WHERE o.usuario_id=:id
             ORDER BY o.vencimento DESC, o.id DESC LIMIT 300"
        );
        $statement->execute(['id' => $userId]);
        $obligations = $statement->fetchAll();
        $statement = $db->prepare(
            "SELECT n.id,n.obrigacao_id,n.valor_proposto,n.mensagem,n.status,n.resposta,n.criado_em,
                    o.referencia,o.evento_titulo
             FROM financeiro_negociacoes n JOIN financeiro_obrigacoes o ON o.id=n.obrigacao_id
             WHERE n.solicitante_id=:id ORDER BY n.criado_em DESC,n.id DESC LIMIT 100"
        );
        $statement->execute(['id' => $userId]);
        $negotiations = $statement->fetchAll();
        $statement = $db->prepare(
            "SELECT p.id,p.obrigacao_id,p.valor,p.data_pagamento,p.descricao,p.status,p.criado_em,
                    o.referencia,o.evento_titulo
             FROM financeiro_pagamentos p JOIN financeiro_obrigacoes o ON o.id=p.obrigacao_id
             WHERE o.usuario_id=:id ORDER BY p.criado_em DESC,p.id DESC LIMIT 100"
        );
        $statement->execute(['id' => $userId]);
        return ['athlete' => $athlete, 'pix' => $pix, 'obligations' => $obligations, 'payments' => $statement->fetchAll(), 'negotiations' => $negotiations];
    }

    public function requestNegotiation(int $userId, int $obligationId, string $amount, string $message): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare(
                "SELECT o.id,o.status,o.valor_original,o.valor_negociado,
                    COALESCE((SELECT SUM(p.valor) FROM financeiro_pagamentos p WHERE p.obrigacao_id=o.id AND p.status='confirmado'),0) AS pago
                 FROM financeiro_obrigacoes o WHERE o.id=:id AND o.usuario_id=:user FOR UPDATE"
            );
            $query->execute(['id' => $obligationId, 'user' => $userId]);
            $obligation = $query->fetch();
            if (!is_array($obligation) || !in_array($obligation['status'], ['pendente','parcial','postergado','negociado'], true)) {
                throw new RuntimeException('Esta cobrança não aceita uma solicitação de negociação.');
            }
            $current = (float) ($obligation['valor_negociado'] ?? $obligation['valor_original']);
            if ((float) $amount < (float) $obligation['pago'] || (float) $amount >= $current) {
                throw new RuntimeException('O valor proposto precisa ser menor que o total atual e cobrir o que já foi pago.');
            }
            $insert = $db->prepare(
                "INSERT INTO financeiro_negociacoes (obrigacao_id,solicitante_id,valor_proposto,mensagem)
                 VALUES (:obligation,:user,:amount,:message)"
            );
            $insert->execute(['obligation' => $obligationId, 'user' => $userId, 'amount' => $amount, 'message' => $message ?: null]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            if ($exception instanceof \PDOException && ($exception->errorInfo[0] ?? '') === '23505') {
                throw new RuntimeException('Já existe uma solicitação aguardando análise para esta cobrança.');
            }
            throw $exception;
        }
    }

    public function resolveNegotiation(int $requestId, int $actor, bool $accept, string $response): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare(
                "SELECT n.id,n.obrigacao_id,n.valor_proposto,n.status,o.valor_original,o.valor_negociado,o.status AS obrigacao_status,
                    COALESCE((SELECT SUM(p.valor) FROM financeiro_pagamentos p WHERE p.obrigacao_id=o.id AND p.status='confirmado'),0) AS pago
                 FROM financeiro_negociacoes n JOIN financeiro_obrigacoes o ON o.id=n.obrigacao_id
                 WHERE n.id=:id FOR UPDATE OF n,o"
            );
            $query->execute(['id' => $requestId]);
            $request = $query->fetch();
            if (!is_array($request) || $request['status'] !== 'aguardando') throw new RuntimeException('Esta solicitação já foi analisada ou não existe.');
            if ($accept) {
                if (!in_array($request['obrigacao_status'], ['pendente','parcial','postergado','negociado'], true)
                    || (float) $request['valor_proposto'] < (float) $request['pago']) {
                    throw new RuntimeException('A cobrança mudou e não permite aceitar esta proposta. Revise-a antes.');
                }
                $update = $db->prepare("UPDATE financeiro_obrigacoes SET valor_negociado=:amount,status='negociado',motivo_status=:note,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
                $update->execute(['amount' => $request['valor_proposto'], 'note' => $response ?: 'Proposta do atleta aceita.', 'id' => $request['obrigacao_id']]);
                $this->history($db, (int) $request['obrigacao_id'], 'negociada', $response ?: 'Proposta do atleta aceita.', (string) ($request['valor_negociado'] ?? $request['valor_original']), (string) $request['valor_proposto'], null, null, $actor);
            }
            $update = $db->prepare('UPDATE financeiro_negociacoes SET status=:status,resposta=:response,analisado_por=:actor,analisado_em=CURRENT_TIMESTAMP WHERE id=:id');
            $update->execute(['status' => $accept ? 'aceita' : 'recusada', 'response' => $response ?: null, 'actor' => $actor, 'id' => $requestId]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function createCategory(string $name, string $type, int $actor): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO financeiro_categorias (nome,tipo,criado_por) VALUES (:name,:type,:actor)'
        );
        $statement->execute(['name' => $name, 'type' => $type, 'actor' => $actor]);
    }

    public function updateCategory(int $id, string $name, bool $active): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE financeiro_categorias SET nome=:name, ativa=:active, atualizado_em=CURRENT_TIMESTAMP WHERE id=:id'
        );
        $statement->execute(['name' => $name, 'active' => $active ? 'true' : 'false', 'id' => $id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('A categoria não foi encontrada.');
    }

    public function createMovement(int $categoryId, string $amount, string $description, string $date, int $actor): void
    {
        $statement = Database::connection()->prepare('SELECT tipo, ativa FROM financeiro_categorias WHERE id=:id');
        $statement->execute(['id' => $categoryId]);
        $category = $statement->fetch();
        if (!is_array($category) || !self::truthy($category['ativa'] ?? false)) throw new RuntimeException('Selecione uma categoria ativa.');
        $statement = Database::connection()->prepare(
            'INSERT INTO financeiro_lancamentos (categoria_id,tipo,valor,descricao,data_movimento,criado_por)
             VALUES (:category,:type,:amount,:description,:date,:actor)'
        );
        $statement->execute(['category' => $categoryId, 'type' => $category['tipo'], 'amount' => $amount, 'description' => $description, 'date' => $date, 'actor' => $actor]);
    }

    public function createRule(array $data, int $actor): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO financeiro_regras (nome,modalidade,valor,dia_vencimento,vigencia_inicio,vigencia_fim,criado_por)
             VALUES (:name,:mode,:amount,:due_day,:start,:end,:actor)'
        );
        $statement->execute([
            'name' => $data['nome'], 'mode' => $data['modalidade'], 'amount' => $data['valor'],
            'due_day' => $data['dia_vencimento'], 'start' => $data['vigencia_inicio'],
            'end' => $data['vigencia_fim'] ?: null, 'actor' => $actor,
        ]);
    }

    public function toggleRule(int $id, bool $active): void
    {
        $statement = Database::connection()->prepare('UPDATE financeiro_regras SET ativa=:active WHERE id=:id');
        $statement->execute(['active' => $active ? 'true' : 'false', 'id' => $id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('A regra não foi encontrada.');
    }

    public function generateMonthly(int $ruleId, string $month, int $actor): int
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        if ($date === false || $date->format('Y-m') !== $month) throw new RuntimeException('Informe um mês válido.');
        $db = Database::connection();
        $rule = $this->activeRule($ruleId, 'mensal', $date->format('Y-m-d'));
        $day = min((int) $rule['dia_vencimento'], (int) $date->format('t'));
        $dueDate = $date->setDate((int) $date->format('Y'), (int) $date->format('m'), $day)->format('Y-m-d');
        $reference = $date->format('Y-m');
        return $this->generateForActiveAthletes($db, $rule, $reference, $dueDate, null, null, $actor);
    }

    public function generateGame(int $ruleId, int $eventId, int $actor): int
    {
        $db = Database::connection();
        $eventStatement = $db->prepare("SELECT id,tipo,titulo,inicio FROM eventos WHERE id=:id AND status<>'cancelado'");
        $eventStatement->execute(['id' => $eventId]);
        $event = $eventStatement->fetch();
        if (!is_array($event) || !in_array($event['tipo'], ['jogo', 'amistoso'], true)) throw new RuntimeException('Selecione um jogo ou amistoso ativo.');
        $starts = new \DateTimeImmutable((string) $event['inicio']);
        $eventDate = $starts->format('Y-m-d');
        if ($starts <= new \DateTimeImmutable('now')) throw new RuntimeException('Só é possível gerar cobranças para jogos futuros.');
        $rule = $this->activeRule($ruleId, 'jogo', $eventDate);
        $due = $starts->modify('-1 day')->format('Y-m-d');
        $reference = 'evento:' . (int) $event['id'];
        return $this->generateForActiveAthletes($db, $rule, $reference, $due, (int) $event['id'], (string) $event['titulo'], $actor, $eventDate);
    }

    public function addExemption(int $userId, string $start, ?string $end, string $reason, int $actor): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $user = $db->prepare('SELECT id FROM usuarios WHERE id=:id AND ativo=TRUE');
            $user->execute(['id' => $userId]);
            if ($user->fetchColumn() === false) throw new RuntimeException('Selecione uma conta ativa.');
            $insert = $db->prepare(
                'INSERT INTO financeiro_isencoes (usuario_id,vigencia_inicio,vigencia_fim,motivo,criado_por)
                 VALUES (:user,:start,:end,:reason,:actor)'
            );
            $insert->execute(['user' => $userId, 'start' => $start, 'end' => $end, 'reason' => $reason, 'actor' => $actor]);
            $update = $db->prepare(
                "UPDATE financeiro_obrigacoes o SET status='isento',motivo_status=:reason,atualizado_em=CURRENT_TIMESTAMP
                 WHERE o.usuario_id=:user
                   AND COALESCE((SELECT e.inicio::date FROM eventos e WHERE e.id=o.evento_id),o.vencimento)
                       BETWEEN :start_date AND COALESCE(CAST(:end_date AS date),'infinity'::date)
                   AND o.status IN ('pendente','parcial','postergado','negociado') RETURNING id,valor_original,vencimento"
            );
            $update->execute(['reason' => $reason, 'user' => $userId, 'start_date' => $start, 'end_date' => $end]);
            foreach ($update->fetchAll() as $obligation) {
                $this->history($db, (int) $obligation['id'], 'isenta', $reason, null, null, null, (string) $obligation['vencimento'], $actor);
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function toggleExemption(int $id, bool $active): void
    {
        $statement = Database::connection()->prepare('UPDATE financeiro_isencoes SET ativa=:active WHERE id=:id');
        $statement->execute(['active' => $active ? 'true' : 'false', 'id' => $id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('A isenção não foi encontrada.');
    }

    public function savePix(string $key, string $name, int $actor): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE financeiro_config SET pix_chave=:key,pix_nome=:name,atualizado_por=:actor,atualizado_em=CURRENT_TIMESTAMP WHERE id=1'
        );
        $statement->execute(['key' => $key === '' ? null : $key, 'name' => $name === '' ? null : $name, 'actor' => $actor]);
    }

    public function submitPayment(int $userId, int $obligationId, string $amount, string $date, string $description): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $statement = $db->prepare(
                "SELECT o.id,o.status,COALESCE(o.valor_negociado,o.valor_original)-COALESCE((
                    SELECT SUM(p.valor) FROM financeiro_pagamentos p WHERE p.obrigacao_id=o.id AND p.status='confirmado'
                 ),0) AS saldo
                 FROM financeiro_obrigacoes o WHERE o.id=:id AND o.usuario_id=:user FOR UPDATE"
            );
            $statement->execute(['id' => $obligationId, 'user' => $userId]);
            $obligation = $statement->fetch();
            if (!is_array($obligation) || !in_array($obligation['status'], ['pendente','parcial','postergado','negociado'], true)) throw new RuntimeException('Esta obrigação não pode receber um pagamento.');
            if ((float) $amount > (float) $obligation['saldo'] + 0.001) throw new RuntimeException('O valor informado ultrapassa o saldo pendente.');
            $payment = $db->prepare(
                "INSERT INTO financeiro_pagamentos (obrigacao_id,valor,data_pagamento,descricao,status,registrado_por)
                 VALUES (:obligation,:amount,:date,:description,'aguardando',:user)"
            );
            $payment->execute(['obligation' => $obligationId, 'amount' => $amount, 'date' => $date, 'description' => $description ?: null, 'user' => $userId]);
            $this->history($db, $obligationId, 'pagamento_enviado', 'Pagamento informado pelo atleta.', null, null, null, null, $userId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            if ($exception instanceof \PDOException && ($exception->errorInfo[0] ?? '') === '23505') throw new RuntimeException('Já existe um pagamento aguardando análise para esta obrigação.');
            throw $exception;
        }
    }

    public function resolvePayment(int $paymentId, int $actor, bool $approve, string $note): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare(
                "SELECT p.id,p.obrigacao_id,p.valor,p.data_pagamento,p.status,o.valor_original,o.valor_negociado,o.status AS obrigacao_status
                 FROM financeiro_pagamentos p JOIN financeiro_obrigacoes o ON o.id=p.obrigacao_id
                 WHERE p.id=:id FOR UPDATE OF p,o"
            );
            $query->execute(['id' => $paymentId]);
            $payment = $query->fetch();
            if (!is_array($payment) || $payment['status'] !== 'aguardando') throw new RuntimeException('Este pagamento já foi analisado ou não existe.');
            if ($approve) {
                if (!in_array($payment['obrigacao_status'], ['pendente','parcial','postergado','negociado'], true)) {
                    throw new RuntimeException('A cobrança foi encerrada. Recuse este pagamento pendente para concluir a análise.');
                }
                $confirmed = $db->prepare("SELECT COALESCE(SUM(valor),0) FROM financeiro_pagamentos WHERE obrigacao_id=:id AND status='confirmado'");
                $confirmed->execute(['id' => $payment['obrigacao_id']]);
                $total = (float) ($payment['valor_negociado'] ?? $payment['valor_original']);
                if ((float) $confirmed->fetchColumn() + (float) $payment['valor'] > $total + 0.001) {
                    throw new RuntimeException('A confirmação ultrapassaria o saldo da obrigação. Revise o valor antes de aprovar.');
                }
            }
            $status = $approve ? 'confirmado' : 'recusado';
            $update = $db->prepare('UPDATE financeiro_pagamentos SET status=:status,analisado_por=:actor,analisado_em=CURRENT_TIMESTAMP WHERE id=:id');
            $update->execute(['status' => $status, 'actor' => $actor, 'id' => $paymentId]);
            if ($approve) {
                $sum = $db->prepare("SELECT COALESCE(SUM(valor),0) FROM financeiro_pagamentos WHERE obrigacao_id=:id AND status='confirmado'");
                $sum->execute(['id' => $payment['obrigacao_id']]);
                $paid = (float) $sum->fetchColumn();
                $total = (float) ($payment['valor_negociado'] ?? $payment['valor_original']);
                $newStatus = $paid + 0.001 >= $total ? 'pago' : 'parcial';
                $up = $db->prepare('UPDATE financeiro_obrigacoes SET status=:status,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id');
                $up->execute(['status' => $newStatus, 'id' => $payment['obrigacao_id']]);
                $this->addContributionMovement($db, $payment, $actor);
                $this->history($db, (int) $payment['obrigacao_id'], 'pagamento_confirmado', $note ?: 'Pagamento confirmado.', null, null, null, null, $actor);
            } else {
                $this->history($db, (int) $payment['obrigacao_id'], 'pagamento_recusado', $note ?: 'Pagamento recusado.', null, null, null, null, $actor);
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function registerManualPayment(int $obligationId, string $amount, string $date, string $note, int $actor): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $statement = $db->prepare(
                "SELECT o.id,o.status,o.valor_original,o.valor_negociado,
                    COALESCE((SELECT SUM(p.valor) FROM financeiro_pagamentos p WHERE p.obrigacao_id=o.id AND p.status='confirmado'),0) AS pago
                 FROM financeiro_obrigacoes o WHERE o.id=:id FOR UPDATE"
            );
            $statement->execute(['id' => $obligationId]);
            $obligation = $statement->fetch();
            if (!is_array($obligation) || !in_array($obligation['status'], ['pendente','parcial','postergado','negociado'], true)) throw new RuntimeException('A obrigação não aceita pagamento.');
            $pending = $db->prepare("SELECT 1 FROM financeiro_pagamentos WHERE obrigacao_id=:id AND status='aguardando' LIMIT 1");
            $pending->execute(['id' => $obligationId]);
            if ($pending->fetchColumn() !== false) throw new RuntimeException('Analise o pagamento já enviado antes de registrar outro.');
            $remaining = (float) ($obligation['valor_negociado'] ?? $obligation['valor_original']) - (float) $obligation['pago'];
            if ((float) $amount > $remaining + 0.001) throw new RuntimeException('O pagamento ultrapassa o saldo da obrigação.');
            $insert = $db->prepare("INSERT INTO financeiro_pagamentos (obrigacao_id,valor,data_pagamento,descricao,status,registrado_por,analisado_por,analisado_em) VALUES (:id,:amount,:date,:note,'confirmado',:actor,:actor,CURRENT_TIMESTAMP) RETURNING id");
            $insert->execute(['id' => $obligationId, 'amount' => $amount, 'date' => $date, 'note' => $note ?: null, 'actor' => $actor]);
            $payment = ['id' => (int) $insert->fetchColumn(), 'obrigacao_id' => $obligationId, 'valor' => $amount, 'data_pagamento' => $date, 'valor_original' => $obligation['valor_original'], 'valor_negociado' => $obligation['valor_negociado']];
            $paid = (float) $obligation['pago'] + (float) $amount;
            $total = (float) ($obligation['valor_negociado'] ?? $obligation['valor_original']);
            $update = $db->prepare('UPDATE financeiro_obrigacoes SET status=:status,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id');
            $update->execute(['status' => $paid + 0.001 >= $total ? 'pago' : 'parcial', 'id' => $obligationId]);
            $this->addContributionMovement($db, $payment, $actor);
            $this->history($db, $obligationId, 'pagamento_confirmado', $note ?: 'Pagamento registrado pelo financeiro.', null, null, null, null, $actor);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function changeObligation(int $id, string $action, string $value, string $note, int $actor): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare(
                "SELECT o.*,COALESCE((SELECT SUM(p.valor) FROM financeiro_pagamentos p WHERE p.obrigacao_id=o.id AND p.status='confirmado'),0) AS total_pago
                 FROM financeiro_obrigacoes o WHERE o.id=:id FOR UPDATE"
            );
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            if (!is_array($row) || in_array($row['status'], ['pago','isento','cancelado'], true)) throw new RuntimeException('Esta obrigação não pode mais ser alterada.');
            $oldAmount = $row['valor_negociado'] ?? $row['valor_original'];
            $oldDue = $row['vencimento'];
            if ($action === 'defer') {
                $update = $db->prepare("UPDATE financeiro_obrigacoes SET vencimento=:value,status='postergado',motivo_status=:note,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
                $update->execute(['value' => $value, 'note' => $note, 'id' => $id]);
                $this->history($db, $id, 'postergada', $note, null, null, (string) $oldDue, $value, $actor);
            } elseif ($action === 'negotiate') {
                if ((float) $value <= 0 || (float) $value > (float) $oldAmount || (float) $value + 0.001 < (float) $row['total_pago']) throw new RuntimeException('O novo valor deve cobrir o que já foi pago e não pode ultrapassar o valor original.');
                $update = $db->prepare("UPDATE financeiro_obrigacoes SET valor_negociado=:value,status='negociado',motivo_status=:note,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
                $update->execute(['value' => $value, 'note' => $note, 'id' => $id]);
                $this->history($db, $id, 'negociada', $note, (string) $oldAmount, $value, null, null, $actor);
            } elseif ($action === 'exempt' || $action === 'cancel') {
                $status = $action === 'exempt' ? 'isento' : 'cancelado';
                $event = $action === 'exempt' ? 'isenta' : 'cancelada';
                $update = $db->prepare('UPDATE financeiro_obrigacoes SET status=:status,motivo_status=:note,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id');
                $update->execute(['status' => $status, 'note' => $note, 'id' => $id]);
                $this->history($db, $id, $event, $note, null, null, null, null, $actor);
            } else {
                throw new RuntimeException('Ação financeira inválida.');
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function exemptionStatus(int $id, bool $active): void
    {
        $this->toggleExemption($id, $active);
    }

    private function activeRule(int $ruleId, string $mode, string $date): array
    {
        $statement = Database::connection()->prepare(
            "SELECT * FROM financeiro_regras WHERE id=:id AND ativa=TRUE AND modalidade=:mode
             AND vigencia_inicio<=:start_date AND (vigencia_fim IS NULL OR vigencia_fim>=:end_date)"
        );
        $statement->execute(['id' => $ruleId, 'mode' => $mode, 'start_date' => $date, 'end_date' => $date]);
        $rule = $statement->fetch();
        if (!is_array($rule)) throw new RuntimeException('A regra está inativa ou fora da vigência selecionada.');
        return $rule;
    }

    private function generateForActiveAthletes(PDO $db, array $rule, string $reference, string $dueDate, ?int $eventId, ?string $eventTitle, int $actor, ?string $exemptionDate = null): int
    {
        $db->beginTransaction();
        try {
            $athletes = $db->query(
                'SELECT u.id AS usuario_id,a.id AS atleta_id,a.nome AS atleta_nome
                 FROM atletas a JOIN usuarios u ON u.id=a.usuario_id
                 WHERE a.ativo=TRUE AND u.ativo=TRUE ORDER BY a.id'
            )->fetchAll();
            $exempt = $db->prepare(
                "SELECT 1 FROM financeiro_isencoes WHERE usuario_id=:user AND ativa=TRUE
                 AND vigencia_inicio<=:date AND (vigencia_fim IS NULL OR vigencia_fim>=:date) LIMIT 1"
            );
            $insert = $db->prepare(
                "INSERT INTO financeiro_obrigacoes
                    (regra_id,usuario_id,atleta_id,evento_id,evento_titulo,atleta_nome,referencia,valor_original,vencimento,criado_por)
                 VALUES (:rule,:user,:athlete,:event_id,:event_title,:athlete_name,:reference,:amount,:due,:actor)
                 ON CONFLICT (regra_id,usuario_id,referencia) DO NOTHING RETURNING id"
            );
            $created = [];
            foreach ($athletes as $athlete) {
                $exempt->execute(['user' => $athlete['usuario_id'], 'date' => $exemptionDate ?? $dueDate]);
                if ($exempt->fetchColumn() !== false) continue;
                $insert->execute([
                    'rule' => $rule['id'], 'user' => $athlete['usuario_id'], 'athlete' => $athlete['atleta_id'],
                    'event_id' => $eventId, 'event_title' => $eventTitle, 'athlete_name' => $athlete['atleta_nome'],
                    'reference' => $reference, 'amount' => $rule['valor'], 'due' => $dueDate, 'actor' => $actor,
                ]);
                $id = $insert->fetchColumn();
                if ($id !== false) $created[] = (int) $id;
            }
            foreach ($created as $id) $this->history($db, $id, 'gerada', 'Cobrança gerada pela regra ' . $rule['nome'] . '.', null, (string) $rule['valor'], null, $dueDate, $actor);
            $db->commit();
            return count($created);
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    private function addContributionMovement(PDO $db, array $payment, int $actor): void
    {
        $category = $db->query("SELECT id FROM financeiro_categorias WHERE tipo='entrada' AND lower(nome)='contribuições de atletas' ORDER BY ativa DESC,id LIMIT 1")->fetchColumn();
        if ($category === false) throw new RuntimeException('A categoria de contribuições de atletas não está disponível.');
        $movement = $db->prepare(
            "INSERT INTO financeiro_lancamentos (categoria_id,tipo,valor,descricao,data_movimento,origem,pagamento_id,criado_por)
             VALUES (:category,'entrada',:amount,:description,:date,'contribuicao_atleta',:payment,:actor)
             ON CONFLICT (pagamento_id) DO NOTHING"
        );
        $movement->execute([
            'category' => $category, 'amount' => $payment['valor'],
            'description' => 'Contribuição de atleta · obrigação ' . (int) $payment['obrigacao_id'],
            'date' => $payment['data_pagamento'], 'payment' => $payment['id'], 'actor' => $actor,
        ]);
    }

    private function history(PDO $db, int $obligationId, string $action, ?string $note, ?string $oldAmount, ?string $newAmount, ?string $oldDue, ?string $newDue, int $actor): void
    {
        $statement = $db->prepare(
            'INSERT INTO financeiro_historico
                (obrigacao_id,acao,observacao,valor_anterior,valor_novo,vencimento_anterior,vencimento_novo,realizado_por)
             VALUES (:obligation,:action,:note,:old_amount,:new_amount,:old_due,:new_due,:actor)'
        );
        $statement->execute([
            'obligation' => $obligationId, 'action' => $action, 'note' => $note,
            'old_amount' => $oldAmount, 'new_amount' => $newAmount, 'old_due' => $oldDue,
            'new_due' => $newDue, 'actor' => $actor,
        ]);
    }

    public static function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }
}
