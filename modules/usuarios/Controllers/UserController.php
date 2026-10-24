<?php

declare(strict_types=1);

namespace Modules\Usuarios\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use Modules\Usuarios\Models\UserModel;
use PDOException;
use RuntimeException;
use Throwable;

final class UserController
{
    public function handle(array $config): void
    {
        header('Cache-Control: no-store, private');
        try {
            Session::start();
            Auth::requireRole('admin');
            $model = new UserModel();
            $escape = [View::class, 'escape'];
            $csrfToken = Csrf::token();
            if (!$model->schemaAvailable()) {
                require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'unavailable.php';
                return;
            }
            $error = null;
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                    http_response_code(400);
                    exit('A sessão expirou. Atualize a página e tente novamente.');
                }
                $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
                if ($action === 'toggle') {
                    $model->toggle($this->id($_POST['id'] ?? null));
                    $_SESSION['user_admin_notice'] = 'Status do usuário atualizado.';
                    header('Location: usuarios', true, 303);
                    exit;
                }
                if ($action !== 'save') {
                    http_response_code(400);
                    exit('Ação inválida.');
                }
                $data = $this->validate($_POST);
                $id = $data['id'];
                $athleteId = $data['atleta_id'] > 0 ? $data['atleta_id'] : null;
                $passwordValue = $_POST['password'] ?? '';
                $password = is_string($passwordValue) ? $passwordValue : '';
                $errors = $data['_errors'];
                if ($id === 0 && strlen($password) < 12) $errors[] = 'A senha inicial deve ter pelo menos 12 caracteres.';
                if ($password !== '' && strlen($password) < 12) $errors[] = 'A senha deve ter pelo menos 12 caracteres.';
                if ($password !== '' && strlen($password) > 4096) $errors[] = 'A senha ultrapassou o limite permitido.';
                unset($data['_errors'], $data['id'], $data['atleta_id']);
                if ($errors !== []) {
                    $error = implode(' ', $errors);
                    $values = $data + ['id' => $id, 'atleta_id' => $athleteId];
                    $user = $id > 0 ? $model->find($id) : null;
                    $athletes = $model->availableAthletes($id > 0 ? $id : null);
                    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'form.php';
                    return;
                }
                try {
                    if ($id > 0) {
                        $model->update($id, $data, $password === '' ? null : $password, $athleteId);
                        $_SESSION['user_admin_notice'] = 'Usuário atualizado.';
                    } else {
                        $model->create($data, $password, $athleteId);
                        $_SESSION['user_admin_notice'] = 'Usuário cadastrado.';
                    }
                    header('Location: usuarios', true, 303);
                    exit;
                } catch (PDOException $exception) {
                    $state = (string) ($exception->errorInfo[0] ?? '');
                    $constraint = (string) ($exception->errorInfo[2] ?? '');
                    $error = $state === '23505'
                        ? (str_contains($constraint, 'usuarios_email_normalizado_unique') ? 'Este e-mail já está cadastrado.' : (str_contains($constraint, 'usuarios_cpf_unique') ? 'Este CPF já está cadastrado.' : 'Um dos dados informados já está cadastrado.'))
                        : 'Não foi possível salvar o usuário. Verifique os dados e tente novamente.';
                } catch (RuntimeException $exception) {
                    $error = $exception->getMessage();
                }
                $values = $data + ['id' => $id, 'atleta_id' => $athleteId];
                $user = $id > 0 ? $model->find($id) : null;
                $athletes = $model->availableAthletes($id > 0 ? $id : null);
                require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'form.php';
                return;
            }
            $idValue = $_GET['id'] ?? null;
            $athleteValue = $_GET['atleta_id'] ?? null;
            if ($idValue !== null || $athleteValue !== null || ($_GET['novo'] ?? null) === '1') {
                $id = $idValue === null ? 0 : $this->id($idValue);
                $athleteId = $athleteValue === null ? 0 : $this->id($athleteValue);
                $user = $id > 0 ? $model->find($id) : null;
                if ($id > 0 && $user === null) { http_response_code(404); exit('Usuário não encontrado.'); }
                $athlete = $athleteId > 0 ? $model->athlete($athleteId) : null;
                if ($athleteId > 0 && $athlete === null) { http_response_code(404); exit('Atleta não encontrado.'); }
                if (is_array($athlete) && $athlete['usuario_id'] !== null && ($user === null || (int) $athlete['usuario_id'] !== $id)) {
                    http_response_code(409); exit('Este atleta já possui um usuário vinculado.');
                }
                $values = is_array($user) ? $user : [
                    'nome' => is_array($athlete) ? (string) $athlete['nome'] : '',
                    'email' => is_array($athlete) ? (string) $athlete['email'] : '',
                    'cpf' => is_array($athlete) ? (string) $athlete['cpf'] : '',
                    'perfil' => 'staff', 'ativo' => true,
                    'atleta_id' => $athleteId > 0 ? $athleteId : null,
                ];
                $values += ['id' => $id, 'atleta_id' => $athleteId > 0 ? $athleteId : null];
                $athletes = $model->availableAthletes($id > 0 ? $id : null);
                require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'form.php';
                return;
            }
            $users = $model->all();
            $notice = $_SESSION['user_admin_notice'] ?? null;
            unset($_SESSION['user_admin_notice']);
            require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'index.php';
        } catch (Throwable $exception) {
            error_log('Falha no cadastro administrativo de usuários: ' . get_class($exception));
            http_response_code(503);
            exit('Cadastro de usuários temporariamente indisponível. Confirme se a migration de usuários foi aplicada.');
        }
    }

    private function validate(array $input): array
    {
        $errors = [];
        $id = $this->optionalId($input['id'] ?? '');
        $athleteId = $this->optionalId($input['atleta_id'] ?? '');
        $name = is_string($input['nome'] ?? null) ? trim($input['nome']) : '';
        $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
        $cpfRaw = is_string($input['cpf'] ?? null) ? $input['cpf'] : '';
        $cpf = preg_replace('/\D+/', '', $cpfRaw) ?? '';
        $role = is_string($input['perfil'] ?? null) ? $input['perfil'] : '';
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120 || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) $errors[] = 'Informe um nome válido (até 120 caracteres).';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) $errors[] = 'Informe um e-mail válido.';
        if (!$this->validCpf($cpf)) $errors[] = 'Informe um CPF válido.';
        if (!in_array($role, ['admin', 'staff', 'atleta'], true)) $errors[] = 'Selecione um perfil válido.';
        return ['id' => $id, 'atleta_id' => $athleteId, 'nome' => $name, 'email' => $email, 'cpf' => $cpf, 'perfil' => $role, 'ativo' => ($input['ativo'] ?? '') === '1', '_errors' => $errors];
    }

    private function validCpf(string $cpf): bool
    {
        if (preg_match('/\A\d{11}\z/', $cpf) !== 1 || preg_match('/\A(\d)\1{10}\z/', $cpf) === 1) return false;
        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;
            for ($index = 0; $index < $digit; $index++) $sum += (int) $cpf[$index] * (($digit + 1) - $index);
            $check = ($sum * 10) % 11;
            if ($check === 10) $check = 0;
            if ($check !== (int) $cpf[$digit]) return false;
        }
        return true;
    }

    private function optionalId(mixed $value): int
    {
        if ($value === '' || $value === null || $value === '0') return 0;
        return $this->id($value);
    }

    private function id(mixed $value): int
    {
        if (!is_scalar($value) || preg_match('/\A[1-9]\d{0,17}\z/', (string) $value) !== 1) {
            http_response_code(400); exit('Identificador inválido.');
        }
        return (int) $value;
    }
}
