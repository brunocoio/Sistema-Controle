<?php

declare(strict_types=1);

namespace Modules\Autenticacao\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use PDO;
use Throwable;

final class LoginController
{
    public function handle(array $config): void
    {
        try {
            Session::start();
            header('Cache-Control: no-store, private');
            if (Auth::user() !== null) {
                header('Location: admin/index', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Sessão de autenticação indisponível: ' . get_class($exception));
            http_response_code(503);
            header('Cache-Control: no-store, private');
            exit('Autenticação temporariamente indisponível. A configuração do banco precisa ser concluída.');
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD, POST');
            exit('Método não permitido.');
        }

        $message = '';
        if ($method === 'POST') {
            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'check-user') {
                header('Content-Type: application/json; charset=utf-8');
                if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'message' => 'Sessão expirada. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    http_response_code(422);
                    echo json_encode(['ok' => false, 'message' => 'Informe um e-mail válido.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                try {
                    $db = Database::connection();
                    $stmt = $db->prepare('SELECT id, nome, email, perfil FROM usuarios WHERE email_normalizado = :email AND ativo = TRUE LIMIT 1');
                    $stmt->execute(['email' => $email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!is_array($user)) {
                        http_response_code(404);
                        echo json_encode(['ok' => false, 'message' => 'Usuário não encontrado ou inativo.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }

                    $photoData = null;
                    try {
                        $photoStmt = $db->prepare("SELECT encode(foto, 'base64') AS foto_b64 FROM atletas WHERE lower(email) = :email AND foto IS NOT NULL LIMIT 1");
                        $photoStmt->execute(['email' => $email]);
                        $athleteRow = $photoStmt->fetch(PDO::FETCH_ASSOC);
                        if (is_array($athleteRow) && !empty($athleteRow['foto_b64'])) {
                            $photoData = 'data:image/jpeg;base64,' . $athleteRow['foto_b64'];
                        }
                    } catch (Throwable) {
                        $photoData = null;
                    }

                    echo json_encode([
                        'ok' => true,
                        'name' => (string) $user['nome'],
                        'email' => (string) $user['email'],
                        'has_photo' => $photoData !== null,
                        'photo' => $photoData,
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                } catch (Throwable $e) {
                    error_log('Erro ao verificar usuário no login: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['ok' => false, 'message' => 'Não foi possível verificar o usuário. Tente novamente.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                http_response_code(400);
                $message = 'Sua sessão expirou. Atualize a página e tente novamente.';
            } else {
                try {
                    $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
                    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                    if (Auth::attempt($email, $password)) {
                        header('Location: admin/index', true, 303);
                        exit;
                    }
                    $message = 'E-mail ou senha inválidos, ou acesso temporariamente bloqueado.';
                } catch (Throwable $exception) {
                    error_log('Falha no fluxo de autenticação: ' . get_class($exception));
                    http_response_code(503);
                    $message = 'Não foi possível entrar agora. Tente novamente mais tarde.';
                }
            }
        }

        $csrfToken = Csrf::token();
        $appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
        $escape = [View::class, 'escape'];
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'login.php';
    }
}
