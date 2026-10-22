<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (!\App\Core\Modules::available('eventos')) {
    header('Location: index', true, 302);
    exit;
}
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'eventos'
    . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'EventModel.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'eventos'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'EventController.php';

(new \Modules\Eventos\Controllers\EventController())->handle($config);
