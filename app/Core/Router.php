<?php

declare(strict_types=1);

namespace App\Core;

use LogicException;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);
        $path = $this->normalizeRoutePath($path);

        if (preg_match('/\A[A-Z]{1,16}\z/', $method) !== 1) {
            throw new \InvalidArgumentException('Método HTTP inválido.');
        }

        if (isset($this->routes[$path][$method])) {
            throw new LogicException('Rota duplicada registrada.');
        }

        $this->routes[$path][$method] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        if (!$request->isValid()) {
            return Response::text('Requisição inválida.', 400);
        }

        $path = $request->path();
        $method = $request->method();
        $handlers = $this->routes[$path] ?? [];

        if ($method === 'HEAD' && isset($handlers['GET'])) {
            $response = $handlers['GET']($request);
            if (!$response instanceof Response) {
                throw new LogicException('O handler da rota deve retornar Response.');
            }

            return new Response($response->status(), $response->headers(), '');
        }

        if (isset($handlers[$method])) {
            $response = $handlers[$method]($request);
            if (!$response instanceof Response) {
                throw new LogicException('O handler da rota deve retornar Response.');
            }

            return $response;
        }

        if ($handlers !== []) {
            $allowed = array_keys($handlers);
            if (isset($handlers['GET']) && !in_array('HEAD', $allowed, true)) {
                $allowed[] = 'HEAD';
            }

            return Response::text('Método não permitido.', 405, ['Allow' => implode(', ', $allowed)]);
        }

        return Response::text('Página não encontrada.', 404);
    }

    private function normalizeRoutePath(string $path): string
    {
        if ($path === '' || $path[0] !== '/' || preg_match('/[\r\n\x00]/', $path) === 1) {
            throw new \InvalidArgumentException('Caminho de rota inválido.');
        }

        $path = preg_replace('#/+#', '/', $path) ?? '/';
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
