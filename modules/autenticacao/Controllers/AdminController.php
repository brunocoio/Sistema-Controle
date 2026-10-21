<?php

declare(strict_types=1);

namespace Modules\Autenticacao\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
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
            $user = Auth::requireRole('admin');
            $csrfToken = Csrf::token();
        } catch (Throwable $exception) {
            error_log('Sessão administrativa indisponível: ' . get_class($exception));
            http_response_code(503);
            header('Cache-Control: no-store, private');
            exit('Área administrativa temporariamente indisponível.');
        }

        $escape = [View::class, 'escape'];
        $appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin.php';
    }
}