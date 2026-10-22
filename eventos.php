<?php

declare(strict_types=1);

$config = require __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (!\App\Core\Modules::available('eventos')) {
    header('Location: ./', true, 302);
    exit;
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'eventos'
    . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'EventModel.php';

try {
    if (isset($_GET['image'])) {
        $rawId = $_GET['image'];
        if (!is_string($rawId) || preg_match('/\A[1-9]\d{0,17}\z/', $rawId) !== 1) {
            http_response_code(400);
            exit;
        }
        $image = (new \Modules\Eventos\Models\EventModel())->releaseImage((int) $rawId);
        if ($image === null) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: image/jpeg');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=300, must-revalidate');
        header('Content-Length: ' . strlen($image));
        echo $image;
        exit;
    }
    $events = (new \Modules\Eventos\Models\EventModel())->publicCalendar();
    $appName = (string) ($config['app']['name'] ?? 'Sistema FLAG');
    $pageTitle = 'Agenda esportiva | ' . $appName;
    $pageDescription = 'Acompanhe jogos, treinos, amistosos e compromissos do Sistema FLAG.';
    $pageStylesheets = ['modules/eventos/Assets/eventos.css'];
    $pageBodyClass = 'public-events-page';
    echo \App\Core\View::renderPublic('eventos', compact('events', 'appName', 'pageTitle', 'pageDescription', 'pageStylesheets', 'pageBodyClass'), $config);
} catch (Throwable $exception) {
    error_log('Agenda pública indisponível: ' . get_class($exception));
    http_response_code(503);
    exit('A agenda está temporariamente indisponível.');
}
