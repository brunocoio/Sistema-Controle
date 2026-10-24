<?php

declare(strict_types=1);

namespace Modules\Financeiro\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use Modules\Financeiro\Models\FinanceModel;
use PDOException;
use RuntimeException;
use Throwable;

final class FinanceController
{
    public function handle(array $config): void
    {
        header('Cache-Control: no-store, private');
        try {
            Session::start();
            $loggedUser = Auth::requireRole('admin', 'staff', 'atleta');
            $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            $screen = match (is_string($path) ? rtrim($path, '/') : '') {
                '/admin/financeiro-equipe' => 'team',
                '/admin/meu-financeiro' => 'account',
                default => (is_string($_GET['screen'] ?? null) && in_array($_GET['screen'], ['team', 'account'], true) ? $_GET['screen'] : 'manager'),
            };
            $isAdmin = ($loggedUser['role'] ?? '') === 'admin';
            if ($screen === 'manager' && !$isAdmin) {
                header('Location: meu-financeiro', true, 303);
                exit;
            }

            $model = new FinanceModel();
            if (!$model->schemaAvailable()) {
                $this->renderUnavailable($config, $screen, $isAdmin);
                return;
            }
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                    http_response_code(400);
                    exit('A sessão expirou. Atualize a página e tente novamente.');
                }
                $this->handlePost($model, $screen, $isAdmin, (int) $loggedUser['id']);
            }

            $financeNotice = $_SESSION['finance_notice'] ?? null;
            $financeError = $_SESSION['finance_error'] ?? null;
            unset($_SESSION['finance_notice'], $_SESSION['finance_error']);
            $escape = [View::class, 'escape'];
            $csrfToken = Csrf::token();
            $appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
            $adminActiveModule = match ($screen) {
                'team' => 'financeiro-equipe',
                'account' => 'meu-financeiro',
                default => 'financeiro',
            };
            if ($screen === 'team') {
                $athletes = $model->teamStatuses();
                require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'team.php';
                return;
            }
            if ($screen === 'account') {
                $account = $model->account((int) $loggedUser['id']);
                require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'account.php';
                return;
            }
            $finance = $model->managerData();
            require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'manager.php';
        } catch (PDOException $exception) {
            error_log('Operação financeira indisponível: banco recusou operação (' . (string) ($exception->errorInfo[0] ?? 'UNKNOWN') . ').');
            $_SESSION['finance_error'] = 'Não foi possível concluir a operação financeira. Revise os dados e tente novamente.';
            $this->redirectToScreen($screen ?? 'manager');
        } catch (RuntimeException $exception) {
            $_SESSION['finance_error'] = $exception->getMessage();
            $this->redirectToScreen($screen ?? 'manager');
        } catch (Throwable $exception) {
            error_log('Módulo financeiro indisponível: ' . get_class($exception));
            http_response_code(503);
            header('Cache-Control: no-store, private');
            exit('O módulo financeiro está temporariamente indisponível.');
        }
    }

    private function handlePost(FinanceModel $model, string $screen, bool $isAdmin, int $actor): never
    {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        if ($action === 'submit-payment' && $screen === 'account') {
            $obligationId = $this->id($_POST['obligation_id'] ?? null);
            $amount = $this->money($_POST['amount'] ?? null);
            $date = $this->date($_POST['payment_date'] ?? null);
            if ($date > date('Y-m-d')) throw new RuntimeException('A data do pagamento não pode estar no futuro.');
            $description = $this->text($_POST['description'] ?? '', 240, false);
            $model->submitPayment($actor, $obligationId, $amount, $date, $description);
            $this->redirectSuccess('Pagamento informado. O financeiro analisará o registro.', 'account');
        }
        if ($action === 'request-negotiation' && $screen === 'account') {
            $obligationId = $this->id($_POST['obligation_id'] ?? null);
            $amount = $this->money($_POST['proposed_amount'] ?? null);
            $message = $this->text($_POST['message'] ?? '', 240, false);
            $model->requestNegotiation($actor, $obligationId, $amount, $message);
            $this->redirectSuccess('Proposta enviada ao financeiro para análise.', 'account');
        }
        if (!$isAdmin || $screen !== 'manager') {
            http_response_code(403);
            exit('Ação não autorizada.');
        }

        if ($action === 'create-category') {
            $type = $this->choice($_POST['type'] ?? null, ['entrada', 'saida']);
            $model->createCategory($this->text($_POST['name'] ?? null, 80), $type, $actor);
            $this->redirectSuccess('Categoria cadastrada.', 'manager');
        }
        if ($action === 'update-category') {
            $model->updateCategory($this->id($_POST['id'] ?? null), $this->text($_POST['name'] ?? null, 80), ($_POST['active'] ?? '') === '1');
            $this->redirectSuccess('Categoria atualizada.', 'manager');
        }
        if ($action === 'create-movement') {
            $model->createMovement(
                $this->id($_POST['category_id'] ?? null), $this->money($_POST['amount'] ?? null),
                $this->text($_POST['description'] ?? null, 240), $this->date($_POST['movement_date'] ?? null), $actor
            );
            $this->redirectSuccess('Lançamento registrado.', 'manager');
        }
        if ($action === 'create-rule') {
            $mode = $this->choice($_POST['mode'] ?? null, ['mensal', 'jogo']);
            $start = $this->date($_POST['start_date'] ?? null);
            $endRaw = $_POST['end_date'] ?? '';
            $end = $endRaw === '' ? null : $this->date($endRaw);
            if ($end !== null && $end < $start) throw new RuntimeException('O fim da vigência não pode ser anterior ao início.');
            $rule = [
                'nome' => $this->text($_POST['name'] ?? null, 100), 'modalidade' => $mode,
                'valor' => $this->money($_POST['amount'] ?? null),
                'dia_vencimento' => $this->integer($_POST['due_day'] ?? '1', 1, 28),
                'vigencia_inicio' => $start, 'vigencia_fim' => $end,
            ];
            $model->createRule($rule, $actor);
            $this->redirectSuccess('Regra de cobrança cadastrada. As cobranças só são criadas quando você solicitar a geração.', 'manager');
        }
        if ($action === 'toggle-rule') {
            $model->toggleRule($this->id($_POST['id'] ?? null), ($_POST['active'] ?? '') === '1');
            $this->redirectSuccess('Regra atualizada.', 'manager');
        }
        if ($action === 'generate-monthly') {
            $month = $this->month($_POST['month'] ?? null);
            $created = $model->generateMonthly($this->id($_POST['rule_id'] ?? null), $month, $actor);
            $this->redirectSuccess($created . ' cobrança(s) mensal(is) criada(s). Cobranças existentes e pessoas isentas foram preservadas.', 'manager');
        }
        if ($action === 'generate-game') {
            $created = $model->generateGame($this->id($_POST['rule_id'] ?? null), $this->id($_POST['event_id'] ?? null), $actor);
            $this->redirectSuccess($created . ' cobrança(s) por jogo criada(s). Cobranças existentes e pessoas isentas foram preservadas.', 'manager');
        }
        if ($action === 'create-exemption') {
            $userId = $this->id($_POST['user_id'] ?? null);
            $start = $this->date($_POST['start_date'] ?? null);
            $endRaw = $_POST['end_date'] ?? '';
            $end = $endRaw === '' ? null : $this->date($endRaw);
            if ($end !== null && $end < $start) throw new RuntimeException('O fim da isenção não pode ser anterior ao início.');
            $model->addExemption($userId, $start, $end, $this->text($_POST['reason'] ?? null, 240), $actor);
            $this->redirectSuccess('Isenção cadastrada. Cobranças em aberto no período foram marcadas como isentas.', 'manager');
        }
        if ($action === 'toggle-exemption') {
            $model->exemptionStatus($this->id($_POST['id'] ?? null), ($_POST['active'] ?? '') === '1');
            $this->redirectSuccess('Situação da isenção atualizada. O histórico de cobranças permanece preservado.', 'manager');
        }
        if ($action === 'save-pix') {
            $key = $this->text($_POST['pix_key'] ?? '', 180, false);
            $name = $this->text($_POST['pix_name'] ?? '', 120, false);
            $model->savePix($key, $name, $actor);
            $this->redirectSuccess('Dados de recebimento atualizados.', 'manager');
        }
        if ($action === 'resolve-payment') {
            $model->resolvePayment($this->id($_POST['payment_id'] ?? null), $actor, ($_POST['decision'] ?? '') === 'confirm', $this->text($_POST['note'] ?? '', 240, false));
            $this->redirectSuccess(($_POST['decision'] ?? '') === 'confirm' ? 'Pagamento confirmado e lançado nas entradas.' : 'Pagamento recusado; o saldo permanece em aberto.', 'manager');
        }
        if ($action === 'resolve-negotiation') {
            $accept = ($_POST['decision'] ?? '') === 'accept';
            $model->resolveNegotiation($this->id($_POST['request_id'] ?? null), $actor, $accept, $this->text($_POST['response'] ?? '', 240, false));
            $this->redirectSuccess($accept ? 'Proposta aceita e obrigação atualizada.' : 'Proposta recusada.', 'manager');
        }
        if ($action === 'manual-payment') {
            $model->registerManualPayment(
                $this->id($_POST['obligation_id'] ?? null), $this->money($_POST['amount'] ?? null),
                $this->date($_POST['payment_date'] ?? null), $this->text($_POST['note'] ?? '', 240, false), $actor
            );
            $this->redirectSuccess('Pagamento registrado e lançado nas entradas.', 'manager');
        }
        if ($action === 'obligation-change') {
            $kind = $this->choice($_POST['change'] ?? null, ['defer', 'negotiate', 'exempt', 'cancel']);
            $value = $kind === 'defer' ? $this->date($_POST['new_due_date'] ?? null) : ($kind === 'negotiate' ? $this->money($_POST['new_amount'] ?? null) : '');
            $note = $this->text($_POST['note'] ?? null, 240);
            $model->changeObligation($this->id($_POST['obligation_id'] ?? null), $kind, $value, $note, $actor);
            $message = ['defer' => 'Vencimento postergado.', 'negotiate' => 'Valor renegociado.', 'exempt' => 'Obrigação marcada como isenta.', 'cancel' => 'Obrigação cancelada.'][$kind];
            $this->redirectSuccess($message, 'manager');
        }
        throw new RuntimeException('Ação financeira inválida.');
    }

    private function renderUnavailable(array $config, string $screen, bool $isAdmin): void
    {
        http_response_code(503);
        $escape = [View::class, 'escape'];
        $csrfToken = Csrf::token();
        $appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
        $adminActiveModule = 'financeiro';
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'unavailable.php';
    }

    private function redirectSuccess(string $message, string $screen): never
    {
        $_SESSION['finance_notice'] = $message;
        $this->redirectToScreen($screen);
    }

    private function redirectToScreen(string $screen): never
    {
        $path = match ($screen) {
            'team' => 'financeiro-equipe',
            'account' => 'meu-financeiro',
            default => 'financeiro',
        };
        header('Location: ' . $path, true, 303);
        exit;
    }

    private function text(mixed $raw, int $max, bool $required = true): string
    {
        if (!is_string($raw)) throw new RuntimeException('Informe um texto válido.');
        $value = trim($raw);
        if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
            throw new RuntimeException('Revise o texto informado.');
        }
        return $value;
    }

    private function money(mixed $raw): string
    {
        if (!is_string($raw) || preg_match('/\A(?:0|[1-9]\d{0,9})(?:[,.]\d{1,2})?\z/', trim($raw)) !== 1) {
            throw new RuntimeException('Informe um valor monetário válido, com até duas casas decimais.');
        }
        $normalized = str_replace(',', '.', trim($raw));
        if ((float) $normalized <= 0) throw new RuntimeException('O valor deve ser maior que zero.');
        return number_format((float) $normalized, 2, '.', '');
    }

    private function date(mixed $raw): string
    {
        if (!is_string($raw) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $raw) !== 1) throw new RuntimeException('Informe uma data válida.');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $issues = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($issues !== false && ($issues['warning_count'] > 0 || $issues['error_count'] > 0)) || $date->format('Y-m-d') !== $raw) {
            throw new RuntimeException('Informe uma data válida.');
        }
        return $raw;
    }

    private function month(mixed $raw): string
    {
        if (!is_string($raw) || preg_match('/\A\d{4}-(?:0[1-9]|1[0-2])\z/', $raw) !== 1) throw new RuntimeException('Selecione um mês válido.');
        return $raw;
    }

    private function choice(mixed $raw, array $allowed): string
    {
        if (!is_string($raw) || !in_array($raw, $allowed, true)) throw new RuntimeException('Selecione uma opção válida.');
        return $raw;
    }

    private function integer(mixed $raw, int $min, int $max): int
    {
        if (!is_string($raw) && !is_int($raw)) throw new RuntimeException('Informe um número inteiro válido.');
        $value = (string) $raw;
        if (preg_match('/\A(?:0|[1-9]\d{0,8})\z/', $value) !== 1 || (int) $value < $min || (int) $value > $max) throw new RuntimeException('O número informado está fora do intervalo permitido.');
        return (int) $value;
    }

    private function id(mixed $raw): int
    {
        if ((!is_string($raw) && !is_int($raw)) || preg_match('/\A[1-9]\d{0,17}\z/', (string) $raw) !== 1) throw new RuntimeException('Identificador inválido. Atualize a tela e tente novamente.');
        return (int) $raw;
    }
}
