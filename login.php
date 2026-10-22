<?php

declare(strict_types=1);

$config = require __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (!\App\Core\Modules::available('autenticacao')) {
    header('Location: ./', true, 302);
    exit;
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'autenticacao'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'LoginController.php';

(new \Modules\Autenticacao\Controllers\LoginController())->handle($config);
