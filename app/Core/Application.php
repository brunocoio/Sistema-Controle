<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Application
{
    public static function run(array $config): void
    {
        $router = new Router();
        $router->add('GET', '/', static function () use ($config): Response {
            $name = (string) ($config['app']['name'] ?? 'Sistema FLAG');
            return Response::html(View::renderPublic('home', ['appName' => $name], $config));
        });

        $request = Request::fromGlobals();
        try {
            $router->dispatch($request)->send($request->method());
        } catch (Throwable $exception) {
            $incidentId = bin2hex(random_bytes(6));
            error_log('Falha interna da aplicação. Referência: ' . $incidentId . '; tipo: ' . get_class($exception));
            Response::text('Erro interno. Referência: ' . $incidentId, 500)->send($request->method());
        }
    }
}
