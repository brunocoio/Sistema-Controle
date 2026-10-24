<?php

declare(strict_types=1);

namespace Modules\Autenticacao\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use Throwable;

final class AdminController
{
    public function handle(array $config): void
    {
        try {
            Session::start();
            header('Cache-Control: no-store, private');
            $user = Auth::requireRole('admin', 'staff', 'atleta');
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if (!Csrf::verify($_POST['_csrf'] ?? null) || ($_POST['action'] ?? null) !== 'dismiss-first-access') {
                    http_response_code(400);
                    exit('Solicitação inválida. Atualize a página e tente novamente.');
                }
                $statement = Database::connection()->prepare(
                    'UPDATE usuarios SET senha_inicial_pendente = FALSE WHERE id = :id'
                );
                $statement->execute(['id' => (int) $user['id']]);
                $_SESSION['user']['first_access'] = false;
                header('Location: index', true, 303);
                exit;
            }
            $csrfToken = Csrf::token();
        } catch (Throwable $exception) {
            error_log('Sessão administrativa indisponível: ' . get_class($exception));
            http_response_code(503);
            header('Cache-Control: no-store, private');
            exit('Área administrativa temporariamente indisponível.');
        }

        $escape = [View::class, 'escape'];
        $appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
        $adminActiveModule = 'home';
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin.php';
    }
}
