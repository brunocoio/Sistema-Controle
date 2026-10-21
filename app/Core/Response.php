<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int $status;
    private array $headers;
    private string $body;

    public function __construct(int $status = 200, array $headers = [], string $body = '')
    {
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException('Código HTTP inválido.');
        }

        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=UTF-8'] + $headers, $body);
    }

    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=UTF-8'] + $headers, $body);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(string $requestMethod = 'GET'): void
    {
        http_response_code($this->status);

        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'",
        ];

        foreach ($securityHeaders + $this->headers as $name => $value) {
            if (!is_string($name) || !is_string($value)
                || preg_match('/\A[a-zA-Z0-9!#$%&\'*+.^_`|~-]+\z/', $name) !== 1
                || preg_match('/[\r\n]/', $value) === 1
            ) {
                continue;
            }

            header($name . ': ' . $value, true);
        }

        if (strtoupper($requestMethod) !== 'HEAD') {
            echo $this->body;
        }
    }
}
