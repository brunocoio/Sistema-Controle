<?php

declare(strict_types=1);

namespace Modules\Autenticacao\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;

final class LogoutController
{
    public function handle(): void
    {
        header('Cache-Control: no-store, private');
        Session::start();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método não permitido.');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('Solicitação inválida.');
        }

        Auth::logout();
        header('Location: login', true, 303);
        exit;
    }
}
