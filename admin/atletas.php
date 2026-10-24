<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (!\App\Core\Modules::available('atletas')) {
    header('Location: index', true, 302);
    exit;
}
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'atletas'
    . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'AthleteModel.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'atletas'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'AthleteController.php';
(new \Modules\Atletas\Controllers\AthleteController())->handle($config);
