<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (!\App\Core\Modules::available('autenticacao')) {
    header('Location: ../', true, 302);
    exit;
}
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'autenticacao'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'AdminController.php';

(new \Modules\Autenticacao\Controllers\AdminController())->handle($config);
