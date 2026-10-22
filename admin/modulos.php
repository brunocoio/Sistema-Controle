<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
\App\Core\Session::start();
header('Cache-Control: no-store, private');
$user = \App\Core\Auth::requireRole('admin');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    exit('Método não permitido.');
}
$escape = [\App\Core\View::class, 'escape'];
$csrfToken = \App\Core\Csrf::token();
$schemaAvailable = \App\Core\ModuleSettings::schemaAvailable();
$notice = $_SESSION['module_settings_notice'] ?? null;
unset($_SESSION['module_settings_notice']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!\App\Core\Csrf::verify($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        $notice = ['tone' => 'error', 'message' => 'Sua sessão expirou. Atualize a página e tente novamente.'];
    } else {
        $moduleKey = is_string($_POST['module'] ?? null) ? $_POST['module'] : '';
        $enabled = ($_POST['enabled'] ?? null) === '1';
        if (!\App\Core\Modules::isToggleable($moduleKey) || !in_array($_POST['enabled'] ?? null, ['0', '1'], true)) {
            http_response_code(422);
            $notice = ['tone' => 'error', 'message' => 'A opção de módulo enviada é inválida.'];
        } else {
            try {
                \App\Core\ModuleSettings::setModuleEnabled($moduleKey, $enabled, (int) $user['id']);
                $_SESSION['module_settings_notice'] = [
                    'tone' => $enabled ? 'success' : 'warning',
                    'message' => ($enabled ? 'Módulo ativado: ' : 'Módulo desativado: ') . (\App\Core\Modules::catalog()[$moduleKey]['title'] ?? $moduleKey) . '.',
                ];
                session_write_close();
                header('Location: modulos.php', true, 303);
                exit;
            } catch (Throwable $exception) {
                error_log('Falha ao alterar módulo: ' . get_class($exception));
                http_response_code(503);
                $notice = ['tone' => 'error', 'message' => 'Não foi possível salvar o módulo. Confira a conexão e a migration do sistema.'];
            }
        }
    }
}

$moduleCatalog = \App\Core\Modules::catalog();
$moduleStates = [];
$moduleInstalled = [];
foreach ($moduleCatalog as $moduleKey => $moduleDefinition) {
    $moduleStates[$moduleKey] = \App\Core\ModuleSettings::moduleEnabled($moduleKey);
    $moduleInstalled[$moduleKey] = \App\Core\Modules::installed($moduleKey);
}
$appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
$adminActiveModule = 'system';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'modules.php';
