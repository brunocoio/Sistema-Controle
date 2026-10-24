<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (!\App\Core\Modules::available('financeiro')) {
    header('Location: index', true, 302);
    exit;
}
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'financeiro'
    . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'FinanceModel.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'financeiro'
    . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'FinanceController.php';

(new \Modules\Financeiro\Controllers\FinanceController())->handle($config);
