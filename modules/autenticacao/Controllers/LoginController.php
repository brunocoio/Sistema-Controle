<?php

declare(strict_types=1);

namespace Modules\Autenticacao\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use Throwable;

final class LoginController
{
    public function handle(array $config): void
    {
        try {
            Session::start();
            header('Cache-Control: no-store, private');
            if (Auth::user() !== null) {
                header('Location: admin/index.php', true, 303);
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
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                http_response_code(400);
                $message = 'Sua sessão expirou. Atualize a página e tente novamente.';
            } else {
                try {
                    $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
                    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                    if (Auth::attempt($email, $password)) {
                        header('Location: admin/index.php', true, 303);
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