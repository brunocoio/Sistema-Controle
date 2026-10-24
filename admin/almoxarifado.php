<?php

declare(strict_types=1);

$config = require dirname(__DIR__).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'bootstrap.php';
if (!\App\Core\Modules::available('almoxarifado')) {
    header('Location: index',true,302);
    exit;
}
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'almoxarifado'
    .DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.'InventoryModel.php';
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'almoxarifado'
    .DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR.'InventoryController.php';

(new \Modules\Almoxarifado\Controllers\InventoryController())->handle($config);
