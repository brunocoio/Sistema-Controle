<?php

declare(strict_types=1);

namespace Modules\Almoxarifado\Models;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class InventoryModel
{
    public function schemaAvailable(): bool
    {
        try {
            $statement = Database::connection()->query(
                "SELECT to_regclass('almoxarifado_categorias') IS NOT NULL
                    AND to_regclass('almoxarifado_colecoes') IS NOT NULL
                    AND to_regclass('almoxarifado_itens') IS NOT NULL
                    AND to_regclass('almoxarifado_movimentos') IS NOT NULL
                    AND to_regclass('almoxarifado_emprestimos') IS NOT NULL
                    AND to_regclass('almoxarifado_checklists') IS NOT NULL
                    AND to_regclass('almoxarifado_checklist_itens') IS NOT NULL"
            );
            return in_array($statement->fetchColumn(), [true, 1, '1', 't', 'true'], true);
        } catch (Throwable) {
            return false;
        }
    }

    public function managerData(): array
    {
        $db = Database::connection();
        $categories = $db->query('SELECT * FROM almoxarifado_categorias ORDER BY ativa DESC, grupo, lower(nome)')->fetchAll();
        $collections = $db->query('SELECT * FROM almoxarifado_colecoes ORDER BY ano_inicio DESC, id DESC')->fetchAll();
        $items = $db->query(
            "SELECT i.*,c.nome AS categoria,c.grupo,co.nome AS colecao,co.ano_inicio,co.ano_fim,
                    CASE WHEN i.ativo=FALSE OR i.propriedade<>'equipe' OR i.situacao<>'disponivel' THEN 0 ELSE GREATEST(0,
                        i.quantidade-i.quantidade_manutencao
                        - COALESCE((SELECT SUM(l.quantidade) FROM almoxarifado_emprestimos l WHERE l.item_id=i.id AND l.status='aberto'),0)
                        - COALESCE((SELECT SUM(ci.quantidade_levar) FROM almoxarifado_checklist_itens ci
                                    JOIN almoxarifado_checklists ch ON ch.id=ci.checklist_id
                                    WHERE ci.item_id=i.id AND ch.status='aberto'),0)
                    ) END AS disponivel
             FROM almoxarifado_itens i JOIN almoxarifado_categorias c ON c.id=i.categoria_id
             LEFT JOIN almoxarifado_colecoes co ON co.id=i.colecao_id
             ORDER BY i.ativo DESC, i.situacao, lower(i.nome), lower(COALESCE(i.codigo,'')), i.id DESC LIMIT 500"
        )->fetchAll();
        $users = $db->query("SELECT id,nome,perfil FROM usuarios WHERE ativo=TRUE ORDER BY lower(nome),id")->fetchAll();
        $events = $db->query(
            "SELECT id,titulo,tipo,inicio,status FROM eventos
             WHERE status<>'cancelado'
             ORDER BY CASE WHEN inicio>=CURRENT_TIMESTAMP THEN 0 ELSE 1 END,
                      CASE WHEN inicio>=CURRENT_TIMESTAMP THEN inicio END ASC,
                      CASE WHEN inicio<CURRENT_TIMESTAMP THEN inicio END DESC LIMIT 120"
        )->fetchAll();
        $loans = $db->query(
            "SELECT l.*,i.nome AS item_nome,i.codigo,i.cor,i.tamanho,i.numero,i.nome_estampado,
                    c.nome AS categoria,u.nome AS responsavel,entregador.nome AS entregue_por_nome,recebedor.nome AS recebido_por_nome
             FROM almoxarifado_emprestimos l JOIN almoxarifado_itens i ON i.id=l.item_id
             JOIN almoxarifado_categorias c ON c.id=i.categoria_id JOIN usuarios u ON u.id=l.usuario_id
             LEFT JOIN usuarios entregador ON entregador.id=l.entregue_por
             LEFT JOIN usuarios recebedor ON recebedor.id=l.recebido_por
             ORDER BY CASE WHEN l.status='aberto' THEN 0 ELSE 1 END,l.retirado_em DESC,l.id DESC LIMIT 300"
        )->fetchAll();
        $checklists = $db->query(
            "SELECT ch.*,e.titulo AS evento,e.tipo AS evento_tipo,e.inicio,
                    u.nome AS responsavel,creator.nome AS criado_por_nome,
                    (SELECT count(*) FROM almoxarifado_checklist_itens ci WHERE ci.checklist_id=ch.id) AS total_itens
             FROM almoxarifado_checklists ch JOIN eventos e ON e.id=ch.evento_id
             JOIN usuarios u ON u.id=ch.responsavel_id LEFT JOIN usuarios creator ON creator.id=ch.criado_por
             ORDER BY CASE WHEN ch.status='aberto' THEN 0 ELSE 1 END,ch.aberto_em DESC,ch.id DESC LIMIT 200"
        )->fetchAll();
        $checklistItems = $db->query(
            "SELECT ci.*,i.nome AS item_nome,i.codigo,i.modo_controle
             FROM almoxarifado_checklist_itens ci JOIN almoxarifado_checklists ch ON ch.id=ci.checklist_id
             JOIN almoxarifado_itens i ON i.id=ci.item_id
             ORDER BY ci.checklist_id,lower(i.nome),i.id"
        )->fetchAll();
        $checklistLines = [];
        foreach ($checklistItems as $line) $checklistLines[(int)$line['checklist_id']][] = $line;
        $movements = $db->query(
            "SELECT m.*,i.nome AS item_nome,i.codigo,u.nome AS responsavel
             FROM almoxarifado_movimentos m JOIN almoxarifado_itens i ON i.id=m.item_id
             LEFT JOIN usuarios u ON u.id=m.realizado_por
             ORDER BY m.data_movimento DESC,m.id DESC LIMIT 150"
        )->fetchAll();
        return compact('categories','collections','items','users','events','loans','checklists','checklistLines','movements');
    }

    public function myLoans(int $userId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT l.id,l.quantidade,l.retirado_em,l.devolucao_prevista,l.condicao_saida,l.observacao,
                    i.nome AS item_nome,i.codigo,i.cor,i.tamanho,i.numero,i.nome_estampado,i.temporada,
                    i.uso,i.funcao_treino,c.nome AS categoria,co.nome AS colecao
             FROM almoxarifado_emprestimos l JOIN almoxarifado_itens i ON i.id=l.item_id
             JOIN almoxarifado_categorias c ON c.id=i.categoria_id LEFT JOIN almoxarifado_colecoes co ON co.id=i.colecao_id
             WHERE l.usuario_id=:user AND l.status='aberto'
             ORDER BY l.devolucao_prevista NULLS LAST,l.retirado_em,l.id"
        );
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }

    public function createCategory(string $name, string $group, string $tracking, int $actor): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO almoxarifado_categorias (nome,grupo,modo_controle,criado_por) VALUES (:name,:group,:tracking,:actor)'
        );
        $statement->execute(['name'=>$name,'group'=>$group,'tracking'=>$tracking,'actor'=>$actor]);
    }

    public function toggleCategory(int $id, bool $active): void
    {
        $statement = Database::connection()->prepare('UPDATE almoxarifado_categorias SET ativa=:active WHERE id=:id');
        $statement->execute(['active'=>$active ? 'true' : 'false','id'=>$id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('A categoria não foi encontrada.');
    }

    public function createCollection(array $data, int $actor): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO almoxarifado_colecoes
                (nome,ano_inicio,ano_fim,cor_primaria,cor_contraste,tem_logo,tem_nome,tem_numero,tem_patrocinio,observacao,criado_por)
             VALUES (:name,:start,:end,:primary_color,:contrast_color,:logo,:player_name,:number,:sponsor,:note,:actor)'
        );
        $statement->execute([
            'name'=>$data['nome'],'start'=>$data['inicio'],'end'=>$data['fim'],'primary_color'=>$data['cor_primaria'],
            'contrast_color'=>$data['cor_contraste'],'logo'=>$data['logo'] ? 'true' : 'false','player_name'=>$data['nome_estampado'] ? 'true' : 'false',
            'number'=>$data['numero'] ? 'true' : 'false','sponsor'=>$data['patrocinio'] ? 'true' : 'false','note'=>$data['observacao'] ?: null,'actor'=>$actor,
        ]);
    }

    public function createItem(array $data, int $actor): void
    {
        $db = Database::connection();
        $categoryQuery = $db->prepare('SELECT grupo,modo_controle,ativa FROM almoxarifado_categorias WHERE id=:id');
        $categoryQuery->execute(['id'=>$data['categoria_id']]);
        $category = $categoryQuery->fetch();
        if (!is_array($category) || !self::truthy($category['ativa'])) throw new RuntimeException('Selecione uma categoria ativa.');
        if ($category['grupo'] === 'vestuario' && $data['colecao_id'] === null && $data['uso'] === 'campeonato') {
            throw new RuntimeException('Selecione uma coleção para o uniforme de campeonato.');
        }
        if ($data['propriedade'] === 'atleta' && $data['proprietario_id'] === null) throw new RuntimeException('Selecione o atleta proprietário.');
        if ($data['origem'] === 'atleta' && $data['propriedade'] !== 'atleta') throw new RuntimeException('Um item comprado pelo atleta deve ser registrado como propriedade dele.');
        if ($data['proprietario_id'] !== null) {
            $ownerQuery = $db->prepare('SELECT id FROM usuarios WHERE id=:id AND ativo=TRUE');
            $ownerQuery->execute(['id'=>$data['proprietario_id']]);
            if ($ownerQuery->fetchColumn() === false) throw new RuntimeException('O proprietário selecionado não tem uma conta ativa.');
        }
        if ($category['modo_controle'] === 'unidade' && $data['codigo'] === '') throw new RuntimeException('Itens controlados por unidade precisam de um código identificador.');
        if ($data['colecao_id'] !== null && ($category['grupo'] !== 'vestuario' || $data['uso'] !== 'campeonato')) {
            throw new RuntimeException('Coleção só pode ser associada a vestuário de campeonato.');
        }
        if ($data['colecao_id'] !== null && $data['cor'] === '') throw new RuntimeException('Informe a cor da peça para validar o par contrastante da coleção.');
        if ($data['colecao_id'] !== null) {
            if (preg_match('/\A(?:19|20|21)\d{2}\z/',$data['temporada']) !== 1) throw new RuntimeException('Informe o ano da peça para a coleção de campeonato.');
            $collectionQuery = $db->prepare('SELECT cor_primaria,cor_contraste,ano_inicio,ano_fim FROM almoxarifado_colecoes WHERE id=:id AND ativa=TRUE');
            $collectionQuery->execute(['id'=>$data['colecao_id']]);
            $collection = $collectionQuery->fetch();
            if (!is_array($collection)) throw new RuntimeException('Selecione uma coleção ativa.');
            if ((int)$data['temporada'] < (int)$collection['ano_inicio'] || (int)$data['temporada'] > (int)$collection['ano_fim']) throw new RuntimeException('O ano da peça está fora do período desta coleção.');
            $itemColor = mb_strtolower($data['cor'],'UTF-8');
            if (!in_array($itemColor,[mb_strtolower((string)$collection['cor_primaria'],'UTF-8'),mb_strtolower((string)$collection['cor_contraste'],'UTF-8')],true)) {
                throw new RuntimeException('A cor da peça precisa corresponder a uma das cores cadastradas para a coleção.');
            }
        }
        if ($data['uso'] === 'treino' && $data['funcao_treino'] !== null) {
            $color = mb_strtolower($data['cor'],'UTF-8');
            $trainingColors = $data['funcao_treino'] === 'ataque' ? ['preto','preta','black','azul','blue'] : ['branco','branca','white','vermelho','vermelha','red'];
            if (!in_array($color,$trainingColors,true)) throw new RuntimeException('A cor não corresponde à combinação de ataque ou defesa usada nos treinos.');
        }
        $db->beginTransaction();
        try {
            $statement = $db->prepare(
                "INSERT INTO almoxarifado_itens
                    (categoria_id,colecao_id,nome,modo_controle,codigo,quantidade,unidade,temporada,cor,tamanho,numero,nome_estampado,uso,funcao_treino,
                     propriedade,proprietario_id,origem,doador,fornecedor,adquirido_em,condicao,situacao,quantidade_manutencao,localizacao,observacao,criado_por)
                 VALUES (:category,:collection,:name,:tracking,:code,:quantity,:unit,:season,:color,:size,:number,:printed_name,:use,:practice_role,
                         :property,:owner,:origin,:donor,:supplier,:acquired,:condition,:status,:maintenance,:location,:note,:actor)
                 RETURNING id"
            );
            $statement->execute([
                'category'=>$data['categoria_id'],'collection'=>$data['colecao_id'],'name'=>$data['nome'],'tracking'=>$category['modo_controle'],
                'code'=>$data['codigo'] ?: null,'quantity'=>$category['modo_controle'] === 'unidade' ? 1 : $data['quantidade'],'unit'=>$data['unidade'],
                'season'=>$data['temporada'] ?: null,'color'=>$data['cor'] ?: null,'size'=>$data['tamanho'] ?: null,'number'=>$data['numero'],
                'printed_name'=>$data['nome_estampado'] ?: null,'use'=>$data['uso'],'practice_role'=>$data['funcao_treino'],
                'property'=>$data['propriedade'],'owner'=>$data['proprietario_id'],'origin'=>$data['origem'],'donor'=>$data['doador'] ?: null,
                'supplier'=>$data['fornecedor'] ?: null,'acquired'=>$data['adquirido_em'],'condition'=>$data['condicao'],
                'status'=>$data['condicao'] === 'reparo' && $category['modo_controle'] === 'unidade' ? 'manutencao' : 'disponivel',
                'maintenance'=>$data['condicao'] === 'reparo' ? ($category['modo_controle'] === 'unidade' ? 1 : $data['quantidade']) : 0,
                'location'=>$data['localizacao'] ?: null,
                'note'=>$data['observacao'] ?: null,'actor'=>$actor,
            ]);
            $id = (int) $statement->fetchColumn();
            $movement = $db->prepare('INSERT INTO almoxarifado_movimentos (item_id,tipo,quantidade,motivo,origem,realizado_por) VALUES (:item,\'entrada\',:quantity,:reason,:origin,:actor)');
            $movement->execute(['item'=>$id,'quantity'=>$category['modo_controle'] === 'unidade' ? 1 : $data['quantidade'],'reason'=>'Cadastro inicial do item/lote.','origin'=>$data['origem'],'actor'=>$actor]);
            if ($data['condicao'] === 'reparo') $this->movement($db,$id,'manutencao',$category['modo_controle'] === 'unidade' ? 1 : $data['quantidade'],'Item cadastrado já necessitando de manutenção.',null,$actor);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function adjustInventory(int $itemId, string $action, int $quantity, string $reason, ?string $origin, int $actor): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT * FROM almoxarifado_itens WHERE id=:id FOR UPDATE');
            $query->execute(['id'=>$itemId]);
            $item = $query->fetch();
            if (!is_array($item) || !self::truthy($item['ativo'])) throw new RuntimeException('O item não está disponível para movimentação.');
            if ($item['modo_controle'] === 'unidade' && $quantity !== 1) throw new RuntimeException('Itens identificados são movimentados individualmente.');
            $available = $this->availableQuantity($db, $itemId, $item);
            if ($action === 'entrada') {
                if ($item['modo_controle'] !== 'quantidade') throw new RuntimeException('Para cadastrar outra unidade identificada, crie um novo item com outro código.');
                $update = $db->prepare('UPDATE almoxarifado_itens SET quantidade=quantidade+:quantity,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id');
                $update->execute(['quantity'=>$quantity,'id'=>$itemId]);
                $this->movement($db,$itemId,'entrada',$quantity,$reason,$origin,$actor);
            } elseif ($action === 'baixa') {
                if ($available < $quantity) throw new RuntimeException('A quantidade disponível não cobre esta baixa. Devolva ou confira empréstimos abertos primeiro.');
                if ($item['modo_controle'] === 'unidade') {
                    $update = $db->prepare("UPDATE almoxarifado_itens SET situacao='baixado',condicao='inutilizado',atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
                    $update->execute(['id'=>$itemId]);
                } else {
                    $update = $db->prepare('UPDATE almoxarifado_itens SET quantidade=quantidade-:quantity,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id');
                    $update->execute(['quantity'=>$quantity,'id'=>$itemId]);
                }
                $this->movement($db,$itemId,'baixa',$quantity,$reason,null,$actor);
            } elseif ($action === 'manutencao') {
                if ($available < $quantity) throw new RuntimeException('A quantidade disponível não cobre o envio para manutenção.');
                if ($item['modo_controle'] === 'unidade') {
                    $update = $db->prepare("UPDATE almoxarifado_itens SET situacao='manutencao',condicao='reparo',atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
                    $update->execute(['id'=>$itemId]);
                } else {
                    $update = $db->prepare("UPDATE almoxarifado_itens SET quantidade_manutencao=quantidade_manutencao+:quantity,condicao='reparo',atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
                    $update->execute(['quantity'=>$quantity,'id'=>$itemId]);
                }
                $this->movement($db,$itemId,'manutencao',$quantity,$reason,null,$actor);
            } elseif ($action === 'retorno_manutencao') {
                if ($item['modo_controle'] === 'unidade') {
                    if ($item['situacao'] !== 'manutencao') throw new RuntimeException('A unidade não está em manutenção.');
                    $update = $db->prepare("UPDATE almoxarifado_itens SET situacao='disponivel',condicao='bom',atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
                    $update->execute(['id'=>$itemId]);
                } else {
                    if ((int) $item['quantidade_manutencao'] < $quantity) throw new RuntimeException('A quantidade informada excede o estoque em manutenção.');
                    $update = $db->prepare("UPDATE almoxarifado_itens SET quantidade_manutencao=quantidade_manutencao-:quantity,condicao=CASE WHEN quantidade_manutencao-:remaining_amount=0 THEN 'bom' ELSE condicao END,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id");
                    $update->execute(['quantity'=>$quantity,'remaining_amount'=>$quantity,'id'=>$itemId]);
                }
                $this->movement($db,$itemId,'retorno_manutencao',$quantity,$reason,null,$actor);
            } else {
                throw new RuntimeException('Movimentação inválida.');
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function createLoan(int $itemId, int $userId, int $quantity, ?string $due, string $condition, string $note, int $actor): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT * FROM almoxarifado_itens WHERE id=:id FOR UPDATE');
            $query->execute(['id'=>$itemId]);
            $item = $query->fetch();
            if (!is_array($item) || !self::truthy($item['ativo']) || $item['situacao'] !== 'disponivel' || $item['propriedade'] !== 'equipe') throw new RuntimeException('Este item não pode ser emprestado.');
            $user = $db->prepare('SELECT id FROM usuarios WHERE id=:id AND ativo=TRUE');
            $user->execute(['id'=>$userId]);
            if ($user->fetchColumn() === false) throw new RuntimeException('Selecione uma pessoa com acesso ativo.');
            if ($item['modo_controle'] === 'unidade' && $quantity !== 1) throw new RuntimeException('Itens identificados são emprestados individualmente.');
            if ($this->availableQuantity($db,$itemId,$item) < $quantity) throw new RuntimeException('Não há quantidade disponível para este empréstimo.');
            $insert = $db->prepare(
                "INSERT INTO almoxarifado_emprestimos (item_id,usuario_id,quantidade,devolucao_prevista,entregue_por,condicao_saida,observacao)
                 VALUES (:item,:user,:quantity,:due,:actor,:condition,:note)"
            );
            $insert->execute(['item'=>$itemId,'user'=>$userId,'quantity'=>$quantity,'due'=>$due,'actor'=>$actor,'condition'=>$condition,'note'=>$note ?: null]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function returnLoan(int $loanId, int $returned, int $lost, int $damaged, string $note, int $actor): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT l.*,i.modo_controle,i.situacao AS item_situacao FROM almoxarifado_emprestimos l JOIN almoxarifado_itens i ON i.id=l.item_id WHERE l.id=:id FOR UPDATE OF l,i');
            $query->execute(['id'=>$loanId]);
            $loan = $query->fetch();
            if (!is_array($loan) || $loan['status'] !== 'aberto') throw new RuntimeException('Este empréstimo já foi encerrado ou não existe.');
            if (min($returned,$lost,$damaged)<0 || $returned+$lost+$damaged !== (int)$loan['quantidade']) throw new RuntimeException('A soma de devolvidos, perdidos e danificados precisa corresponder ao empréstimo.');
            if ($loan['modo_controle']==='unidade' && (int)$loan['quantidade']!==1) throw new RuntimeException('Revise a quantidade da unidade identificada.');
            $condition = $lost === (int)$loan['quantidade'] ? 'inutilizado' : ($damaged > 0 ? 'reparo' : 'bom');
            $update = $db->prepare("UPDATE almoxarifado_emprestimos SET status='devolvido',devolvido_em=CURRENT_TIMESTAMP,recebido_por=:actor,condicao_retorno=:condition,quantidade_devolvida=:returned,quantidade_perdida=:lost,quantidade_danificada=:damaged,observacao=COALESCE(:note,observacao) WHERE id=:id");
            $update->execute(['actor'=>$actor,'condition'=>$condition,'returned'=>$returned,'lost'=>$lost,'damaged'=>$damaged,'note'=>$note ?: null,'id'=>$loanId]);
            if ($lost > 0) {
                $this->setLoanItemCondition($db,$loan,'baixado','inutilizado',$lost);
                $this->movement($db,(int)$loan['item_id'],'baixa',$lost,$note ?: 'Item perdido no período do empréstimo.',null,$actor);
            }
            if ($damaged > 0) {
                $this->setLoanItemCondition($db,$loan,'manutencao','reparo',$damaged);
                $this->movement($db,(int)$loan['item_id'],'manutencao',$damaged,$note ?: 'Item devolvido danificado e enviado para manutenção.',null,$actor);
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function createChecklist(int $eventId, int $custodianId, array $lines, string $note, int $actor): void
    {
        if ($lines === []) throw new RuntimeException('Adicione pelo menos um item à conferência.');
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $event = $db->prepare('SELECT id FROM eventos WHERE id=:id AND status<>\'cancelado\' FOR UPDATE');
            $event->execute(['id'=>$eventId]);
            if ($event->fetchColumn() === false) throw new RuntimeException('Selecione um evento válido.');
            $user = $db->prepare('SELECT id FROM usuarios WHERE id=:id AND ativo=TRUE');
            $user->execute(['id'=>$custodianId]);
            if ($user->fetchColumn() === false) throw new RuntimeException('Selecione um responsável ativo.');
            $insert = $db->prepare("INSERT INTO almoxarifado_checklists (evento_id,responsavel_id,criado_por,observacao) VALUES (:event,:custodian,:actor,:note) RETURNING id");
            $insert->execute(['event'=>$eventId,'custodian'=>$custodianId,'actor'=>$actor,'note'=>$note ?: null]);
            $checklistId = (int)$insert->fetchColumn();
            $lineInsert = $db->prepare('INSERT INTO almoxarifado_checklist_itens (checklist_id,item_id,quantidade_levar) VALUES (:checklist,:item,:quantity)');
            foreach ($lines as $itemId => $quantity) {
                $itemId = (int)$itemId;
                $itemQuery = $db->prepare('SELECT * FROM almoxarifado_itens WHERE id=:id FOR UPDATE');
                $itemQuery->execute(['id'=>$itemId]);
                $item = $itemQuery->fetch();
                if (!is_array($item) || !self::truthy($item['ativo']) || $item['situacao'] !== 'disponivel' || $item['propriedade'] !== 'equipe') throw new RuntimeException('Um item selecionado não pode ser separado para evento.');
                if ($item['modo_controle'] === 'unidade' && $quantity !== 1) throw new RuntimeException('Unidades identificadas devem ser incluídas uma por vez.');
                if ($this->availableQuantity($db,$itemId,$item) < $quantity) throw new RuntimeException('A quantidade disponível não cobre a lista do evento.');
                $lineInsert->execute(['checklist'=>$checklistId,'item'=>$itemId,'quantity'=>$quantity]);
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function closeChecklist(int $checklistId, array $results, string $note, int $actor): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT * FROM almoxarifado_checklists WHERE id=:id FOR UPDATE');
            $query->execute(['id'=>$checklistId]);
            $checklist = $query->fetch();
            if (!is_array($checklist) || $checklist['status'] !== 'aberto') throw new RuntimeException('Esta conferência já foi fechada ou não existe.');
            $linesQuery = $db->prepare('SELECT ci.*,i.modo_controle,i.situacao,i.quantidade,i.quantidade_manutencao FROM almoxarifado_checklist_itens ci JOIN almoxarifado_itens i ON i.id=ci.item_id WHERE ci.checklist_id=:id ORDER BY ci.item_id FOR UPDATE OF ci,i');
            $linesQuery->execute(['id'=>$checklistId]);
            $lines = $linesQuery->fetchAll();
            foreach ($lines as $line) {
                $result = $results[(int)$line['id']] ?? null;
                if (!is_array($result)) throw new RuntimeException('Informe o retorno de todos os itens da lista.');
                $returned = (int)$result['returned']; $lost = (int)$result['lost']; $damaged = (int)$result['damaged'];
                if (min($returned,$lost,$damaged) < 0 || $returned + $lost + $damaged !== (int)$line['quantidade_levar']) throw new RuntimeException('A soma de devolvidos, perdidos e danificados precisa corresponder ao que foi levado.');
                if ($line['modo_controle'] === 'unidade' && ((int)$line['quantidade_levar'] !== 1 || ($lost > 0 && $damaged > 0))) throw new RuntimeException('Revise a conferência das unidades identificadas.');
                $itemUpdate = $db->prepare('UPDATE almoxarifado_checklist_itens SET quantidade_devolvida=:returned,quantidade_perdida=:lost,quantidade_danificada=:damaged,observacao=:note WHERE id=:id');
                $itemUpdate->execute(['returned'=>$returned,'lost'=>$lost,'damaged'=>$damaged,'note'=>$result['note'] ?: null,'id'=>$line['id']]);
                if ($lost > 0) {
                    if ($line['modo_controle'] === 'unidade') {
                        $db->prepare("UPDATE almoxarifado_itens SET situacao='baixado',condicao='inutilizado',atualizado_em=CURRENT_TIMESTAMP WHERE id=:id")->execute(['id'=>$line['item_id']]);
                    } else {
                        $db->prepare('UPDATE almoxarifado_itens SET quantidade=quantidade-:amount,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id')->execute(['amount'=>$lost,'id'=>$line['item_id']]);
                    }
                    $this->movement($db,(int)$line['item_id'],'baixa',$lost,$result['note'] ?: 'Item perdido durante evento.',null,$actor);
                }
                if ($damaged > 0) {
                    if ($line['modo_controle'] === 'unidade') {
                        $db->prepare("UPDATE almoxarifado_itens SET situacao='manutencao',condicao='reparo',atualizado_em=CURRENT_TIMESTAMP WHERE id=:id")->execute(['id'=>$line['item_id']]);
                    } else {
                        $db->prepare("UPDATE almoxarifado_itens SET quantidade_manutencao=quantidade_manutencao+:amount,condicao='reparo',atualizado_em=CURRENT_TIMESTAMP WHERE id=:id")->execute(['amount'=>$damaged,'id'=>$line['item_id']]);
                    }
                    $this->movement($db,(int)$line['item_id'],'manutencao',$damaged,$result['note'] ?: 'Item danificado durante evento.',null,$actor);
                }
            }
            $update = $db->prepare("UPDATE almoxarifado_checklists SET status='conferido',fechado_em=CURRENT_TIMESTAMP,conferido_por=:actor,observacao=COALESCE(:note,observacao) WHERE id=:id");
            $update->execute(['actor'=>$actor,'note'=>$note ?: null,'id'=>$checklistId]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public static function truthy(mixed $value): bool
    {
        return in_array($value,[true,1,'1','t','true'],true);
    }

    private function availableQuantity(PDO $db, int $itemId, array $item): int
    {
        if (!self::truthy($item['ativo']) || $item['situacao'] !== 'disponivel') return 0;
        $statement = $db->prepare(
            "SELECT GREATEST(0,:quantity-:maintenance
                - COALESCE((SELECT SUM(l.quantidade) FROM almoxarifado_emprestimos l WHERE l.item_id=:loan_item AND l.status='aberto'),0)
                - COALESCE((SELECT SUM(ci.quantidade_levar) FROM almoxarifado_checklist_itens ci JOIN almoxarifado_checklists ch ON ch.id=ci.checklist_id WHERE ci.item_id=:check_item AND ch.status='aberto'),0))"
        );
        $statement->execute(['quantity'=>$item['quantidade'],'maintenance'=>$item['quantidade_manutencao'],'loan_item'=>$itemId,'check_item'=>$itemId]);
        return (int)$statement->fetchColumn();
    }

    private function setLoanItemCondition(PDO $db, array $loan, string $status, string $condition, int $quantity): void
    {
        if ($loan['modo_controle'] === 'unidade') {
            $db->prepare('UPDATE almoxarifado_itens SET situacao=:status,condicao=:condition,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id')
                ->execute(['status'=>$status,'condition'=>$condition,'id'=>$loan['item_id']]);
        } elseif ($status === 'baixado') {
            $db->prepare('UPDATE almoxarifado_itens SET quantidade=quantidade-:quantity,atualizado_em=CURRENT_TIMESTAMP WHERE id=:id')
                ->execute(['quantity'=>$quantity,'id'=>$loan['item_id']]);
        } else {
            $db->prepare("UPDATE almoxarifado_itens SET quantidade_manutencao=quantidade_manutencao+:quantity,condicao='reparo',atualizado_em=CURRENT_TIMESTAMP WHERE id=:id")
                ->execute(['quantity'=>$quantity,'id'=>$loan['item_id']]);
        }
    }

    private function movement(PDO $db, int $itemId, string $type, int $quantity, string $reason, ?string $origin, int $actor): void
    {
        $statement = $db->prepare('INSERT INTO almoxarifado_movimentos (item_id,tipo,quantidade,motivo,origem,realizado_por) VALUES (:item,:type,:quantity,:reason,:origin,:actor)');
        $statement->execute(['item'=>$itemId,'type'=>$type,'quantity'=>$quantity,'reason'=>$reason,'origin'=>$origin,'actor'=>$actor]);
    }
}
