<?php

declare(strict_types=1);

// Alias para o Nginx do Laragon, que não lê as regras de URL do .htaccess.
$_GET['route'] = 'categories';
require __DIR__ . DIRECTORY_SEPARATOR . 'atletas.php';
