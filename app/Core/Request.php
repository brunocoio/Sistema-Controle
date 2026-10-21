<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private string $method;
    private string $path;
    private bool $valid;

    private function __construct(string $method, string $path, bool $valid)
    {
        $this->method = $method;
        $this->path = $path;
        $this->valid = $valid;
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        if (preg_match('/\A[A-Z]{1,16}\z/', $method) !== 1 || !is_string($path)) {
            return new self($method, '/', false);
        }

        if (preg_match('/%(?:2f|5c|00)/i', $path) === 1) {
            return new self($method, '/', false);
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        }

        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath));
        }

        // The homepage is served by index.php, so both / and /index.php
        // must resolve to the same route in Apache/Laragon and Vercel.
        if (preg_match('#/index\.php\z#i', $path) === 1) {
            $path = substr($path, 0, -strlen('/index.php'));
            if ($path === '') {
                $path = '/';
            }
        }

        $path = preg_replace('#/+#', '/', $path) ?? '/';
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach (explode('/', trim($path, '/')) as $segment) {
            $decoded = rawurldecode($segment);
            if ($decoded === '.' || $decoded === '..' || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
                return new self($method, '/', false);
            }
        }

        return new self($method, $path, true);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }
}
