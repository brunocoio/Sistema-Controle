<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
\App\Core\Session::start();
header('Cache-Control: no-store, private');
\App\Core\Auth::requireRole('admin');
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!\App\Core\Csrf::verify($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Sua sessão expirou. Atualize a página.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$rawCollapsed = $_POST['sidebar_collapsed'] ?? null;
if (!in_array($rawCollapsed, ['0', '1'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Preferência inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    $user = \App\Core\Auth::requireRole('admin');
    \App\Core\ModuleSettings::setSidebarCollapsed((int) $user['id'], $rawCollapsed === '1');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Falha ao salvar preferência do menu: ' . get_class($exception));
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível salvar a preferência. Confira a conexão e a migration do sistema.'], JSON_UNESCAPED_UNICODE);
}
