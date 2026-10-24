<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'usuarios'
    . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'UserModel.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'usuarios'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'UserController.php';

(new \Modules\Usuarios\Controllers\UserController())->handle($config);
