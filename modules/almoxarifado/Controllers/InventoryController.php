<?php

declare(strict_types=1);

namespace Modules\Almoxarifado\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use Modules\Almoxarifado\Models\InventoryModel;
use PDOException;
use RuntimeException;
use Throwable;

final class InventoryController
{
    public function handle(array $config): void
    {
        header('Cache-Control: no-store, private');
        try {
            Session::start();
            $user = Auth::requireRole('admin','staff','atleta');
            $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            $requestedScreen = $_GET['screen'] ?? null;
            $screen = $requestedScreen === 'personal'
                || (is_string($path) && rtrim($path,'/') === '/admin/meus-equipamentos')
                ? 'personal'
                : 'manager';
            $role = (string)($user['role'] ?? '');
            $isManager = in_array($role,['admin','staff'],true);
            if ($screen === 'manager' && !$isManager) {
                header('Location: meus-equipamentos',true,303);
                exit;
            }
            $model = new InventoryModel();
            if (!$model->schemaAvailable()) {
                $this->renderUnavailable($config,$screen,$role==='admin');
                return;
            }
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                    http_response_code(400);
                    exit('A sessão expirou. Atualize a página e tente novamente.');
                }
                $this->handlePost($model,$screen,$isManager,(int)$user['id']);
            }
            $inventoryNotice = $_SESSION['inventory_notice'] ?? null;
            $inventoryError = $_SESSION['inventory_error'] ?? null;
            unset($_SESSION['inventory_notice'],$_SESSION['inventory_error']);
            $escape = [View::class,'escape'];
            $csrfToken = Csrf::token();
            $appName = (string)($config['app']['name'] ?? 'Sistema FLAG');
            $adminActiveModule = $screen === 'personal' ? 'meus-equipamentos' : 'almoxarifado';
            if ($screen === 'personal') {
                $loans = $model->myLoans((int)$user['id']);
                require dirname(__DIR__).DIRECTORY_SEPARATOR.'Views'.DIRECTORY_SEPARATOR.'personal.php';
                return;
            }
            $inventory = $model->managerData();
            require dirname(__DIR__).DIRECTORY_SEPARATOR.'Views'.DIRECTORY_SEPARATOR.'manager.php';
        } catch (PDOException $exception) {
            error_log('Operação de almoxarifado indisponível: banco recusou operação ('.(string)($exception->errorInfo[0] ?? 'UNKNOWN').').');
            $_SESSION['inventory_error'] = 'Não foi possível concluir a operação. Revise os dados e tente novamente.';
            $this->redirect('manager');
        } catch (RuntimeException $exception) {
            $_SESSION['inventory_error'] = $exception->getMessage();
            $this->redirect(isset($screen) ? $screen : 'manager');
        } catch (Throwable $exception) {
            error_log('Módulo de almoxarifado indisponível: '.get_class($exception));
            http_response_code(503);
            exit('O almoxarifado está temporariamente indisponível.');
        }
    }

    private function handlePost(InventoryModel $model,string $screen,bool $isManager,int $actor): never
    {
        if ($screen !== 'manager' || !$isManager) {
            http_response_code(403);
            exit('Ação não autorizada.');
        }
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        if ($action === 'create-category') {
            $model->createCategory($this->text($_POST['name'] ?? null,80),$this->choice($_POST['group'] ?? null,['equipamento','vestuario','outro']),$this->choice($_POST['tracking'] ?? null,['unidade','quantidade']),$actor);
            $this->success('Categoria cadastrada.');
        }
        if ($action === 'toggle-category') {
            $model->toggleCategory($this->id($_POST['id'] ?? null),($_POST['active'] ?? '') === '1');
            $this->success('Situação da categoria atualizada.');
        }
        if ($action === 'create-collection') {
            $start = $this->integer($_POST['year_start'] ?? null,1900,2200);
            $end = $this->integer($_POST['year_end'] ?? null,1900,2200);
            if ($end < $start) throw new RuntimeException('O ano final não pode ser anterior ao inicial.');
            $collection = [
                'nome'=>$this->text($_POST['name'] ?? null,100),'inicio'=>$start,'fim'=>$end,
                'cor_primaria'=>$this->text($_POST['primary_color'] ?? null,40),'cor_contraste'=>$this->text($_POST['contrast_color'] ?? null,40),
                'logo'=>($_POST['has_logo'] ?? '') === '1','nome_estampado'=>($_POST['has_name'] ?? '') === '1',
                'numero'=>($_POST['has_number'] ?? '') === '1','patrocinio'=>($_POST['has_sponsor'] ?? '') === '1',
                'observacao'=>$this->text($_POST['note'] ?? '',240,false),
            ];
            if (mb_strtolower($collection['cor_primaria'],'UTF-8') === mb_strtolower($collection['cor_contraste'],'UTF-8')) throw new RuntimeException('As duas cores da coleção precisam ser diferentes e contrastantes.');
            $model->createCollection($collection,$actor);
            $this->success('Coleção de uniformes cadastrada.');
        }
        if ($action === 'create-item') {
            $collection = ($_POST['collection_id'] ?? '') === '' ? null : $this->id($_POST['collection_id']);
            $owner = ($_POST['owner_id'] ?? '') === '' ? null : $this->id($_POST['owner_id']);
            $number = ($_POST['jersey_number'] ?? '') === '' ? null : $this->integer($_POST['jersey_number'],0,99);
            $quantity = $this->integer($_POST['quantity'] ?? '1',1,100000);
            $data = [
                'categoria_id'=>$this->id($_POST['category_id'] ?? null),'colecao_id'=>$collection,
                'nome'=>$this->text($_POST['name'] ?? null,120),'codigo'=>$this->text($_POST['code'] ?? '',48,false),
                'quantidade'=>$quantity,'unidade'=>$this->text($_POST['unit'] ?? 'unidade',24),
                'temporada'=>$this->text($_POST['season'] ?? '',24,false),'cor'=>$this->text($_POST['color'] ?? '',40,false),
                'tamanho'=>$this->text($_POST['size'] ?? '',24,false),'numero'=>$number,
                'nome_estampado'=>$this->text($_POST['printed_name'] ?? '',80,false),
                'uso'=>$this->choice($_POST['use'] ?? 'outro',['treino','campeonato','outro']),
                'funcao_treino'=>($_POST['practice_role'] ?? '') === '' ? null : $this->choice($_POST['practice_role'],['ataque','defesa']),
                'propriedade'=>$this->choice($_POST['property'] ?? 'equipe',['equipe','atleta']),'proprietario_id'=>$owner,
                'origem'=>$this->choice($_POST['origin'] ?? 'legado',['compra','doacao','legado','atleta','outro']),
                'doador'=>$this->text($_POST['donor'] ?? '',120,false),'fornecedor'=>$this->text($_POST['supplier'] ?? '',120,false),
                'adquirido_em'=>($_POST['acquired_date'] ?? '') === '' ? null : $this->date($_POST['acquired_date']),
                'condicao'=>$this->choice($_POST['condition'] ?? 'bom',['novo','bom','reparo','inutilizado']),
                'localizacao'=>$this->text($_POST['location'] ?? '',100,false),'observacao'=>$this->text($_POST['note'] ?? '',240,false),
            ];
            $model->createItem($data,$actor);
            $this->success('Item ou lote registrado no inventário.');
        }
        if ($action === 'inventory-movement') {
            $qty = $this->integer($_POST['quantity'] ?? null,1,100000);
            $origin = ($_POST['origin'] ?? '') === '' ? null : $this->choice($_POST['origin'],['compra','doacao','legado','atleta','outro']);
            $model->adjustInventory($this->id($_POST['item_id'] ?? null),$this->choice($_POST['movement'] ?? null,['entrada','baixa','manutencao','retorno_manutencao']),$qty,$this->text($_POST['reason'] ?? null,240),$origin,$actor);
            $this->success('Movimentação registrada; o histórico do item foi preservado.');
        }
        if ($action === 'create-loan') {
            $due = ($_POST['due_date'] ?? '') === '' ? null : $this->date($_POST['due_date']);
            $model->createLoan($this->id($_POST['item_id'] ?? null),$this->id($_POST['user_id'] ?? null),$this->integer($_POST['quantity'] ?? '1',1,100000),$due,$this->choice($_POST['condition'] ?? 'bom',['novo','bom','reparo']),$this->text($_POST['note'] ?? '',240,false),$actor);
            $this->success('Empréstimo registrado sob responsabilidade da pessoa selecionada.');
        }
        if ($action === 'return-loan') {
            $model->returnLoan(
                $this->id($_POST['loan_id'] ?? null),
                $this->integer($_POST['returned'] ?? null,0,100000),
                $this->integer($_POST['lost'] ?? null,0,100000),
                $this->integer($_POST['damaged'] ?? null,0,100000),
                $this->text($_POST['note'] ?? '',240,false),$actor
            );
            $this->success('Devolução registrada. O histórico do empréstimo foi mantido.');
        }
        if ($action === 'create-checklist') {
            $ids = $_POST['item_id'] ?? []; $quantities = $_POST['item_quantity'] ?? [];
            if (!is_array($ids) || !is_array($quantities) || count($ids) > 12 || count($quantities) > 12) throw new RuntimeException('A lista do evento contém dados inválidos.');
            $lines = [];
            foreach ($ids as $index=>$rawId) {
                if ($rawId === '') continue;
                $itemId = $this->id($rawId);
                if (isset($lines[$itemId])) throw new RuntimeException('Cada item deve aparecer uma única vez na lista.');
                $lines[$itemId] = $this->integer($quantities[$index] ?? null,1,100000);
            }
            $model->createChecklist($this->id($_POST['event_id'] ?? null),$this->id($_POST['custodian_id'] ?? null),$lines,$this->text($_POST['note'] ?? '',240,false),$actor);
            $this->success('Lista de conferência criada e material reservado para o evento.');
        }
        if ($action === 'close-checklist') {
            $rawResults = $_POST['results'] ?? [];
            if (!is_array($rawResults) || count($rawResults) > 100) throw new RuntimeException('Conferência inválida.');
            $results = [];
            foreach ($rawResults as $lineId=>$result) {
                $line = $this->id((string)$lineId);
                if (!is_array($result)) throw new RuntimeException('Resultado de item inválido.');
                $results[$line] = [
                    'returned'=>$this->integer($result['returned'] ?? null,0,100000),
                    'lost'=>$this->integer($result['lost'] ?? null,0,100000),
                    'damaged'=>$this->integer($result['damaged'] ?? null,0,100000),
                    'note'=>$this->text($result['note'] ?? '',240,false),
                ];
            }
            $model->closeChecklist($this->id($_POST['checklist_id'] ?? null),$results,$this->text($_POST['note'] ?? '',240,false),$actor);
            $this->success('Conferência fechada. Perdas foram baixadas e itens danificados encaminhados para manutenção.');
        }
        throw new RuntimeException('Ação de almoxarifado inválida.');
    }

    private function renderUnavailable(array $config,string $screen,bool $isSystemRoot): void
    {
        http_response_code(503);
        $escape = [View::class,'escape']; $csrfToken = Csrf::token();
        $isManager = $isSystemRoot;
        $appName = (string)($config['app']['name'] ?? 'Sistema FLAG');
        $adminActiveModule = $screen === 'personal' ? 'meus-equipamentos' : 'almoxarifado';
        require dirname(__DIR__).DIRECTORY_SEPARATOR.'Views'.DIRECTORY_SEPARATOR.'unavailable.php';
    }

    private function success(string $message): never
    {
        $_SESSION['inventory_notice'] = $message;
        $this->redirect('manager');
    }

    private function redirect(string $screen): never
    {
        header('Location: '.($screen === 'personal' ? 'meus-equipamentos' : 'almoxarifado'),true,303);
        exit;
    }

    private function text(mixed $raw,int $max,bool $required=true): string
    {
        if (!is_string($raw)) throw new RuntimeException('Informe um texto válido.');
        $value = trim($raw);
        if (($required && $value==='') || mb_strlen($value,'UTF-8')>$max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)===1) throw new RuntimeException('Revise o texto informado.');
        return $value;
    }

    private function choice(mixed $raw,array $allowed): string
    {
        if (!is_string($raw) || !in_array($raw,$allowed,true)) throw new RuntimeException('Selecione uma opção válida.');
        return $raw;
    }

    private function integer(mixed $raw,int $min,int $max): int
    {
        if ((!is_string($raw) && !is_int($raw)) || preg_match('/\A(?:0|[1-9]\d{0,8})\z',(string)$raw)!==1 || (int)$raw<$min || (int)$raw>$max) throw new RuntimeException('Número inválido. Revise os dados e tente novamente.');
        return (int)$raw;
    }

    private function id(mixed $raw): int
    {
        if ((!is_string($raw) && !is_int($raw)) || preg_match('/\A[1-9]\d{0,17}\z',(string)$raw)!==1) throw new RuntimeException('Identificador inválido. Atualize a tela e tente novamente.');
        return (int)$raw;
    }

    private function date(mixed $raw): string
    {
        if (!is_string($raw) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$raw)!==1) throw new RuntimeException('Informe uma data válida.');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d',$raw); $issues = \DateTimeImmutable::getLastErrors();
        if ($date===false || ($issues!==false && ($issues['warning_count']>0 || $issues['error_count']>0)) || $date->format('Y-m-d')!==$raw) throw new RuntimeException('Informe uma data válida.');
        return $raw;
    }
}
