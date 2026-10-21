<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            throw new RuntimeException('A sessão não pode ser iniciada depois do envio da resposta.');
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
        $cookiePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if (basename($cookiePath) === 'admin') {
            $cookiePath = dirname($cookiePath);
        }
        if ($cookiePath === '.' || $cookiePath === '') {
            $cookiePath = '/';
        }
        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || $forwardedProto === 'https';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_name('FLAGSESSID');
        session_set_save_handler(new DatabaseSessionHandler(), true);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // O handler de sessão registra a causa técnica no log. Suprima o warning
        // do PHP aqui para que ele não seja enviado antes dos headers HTTP.
        set_error_handler(static function (int $severity): bool {
            return $severity === E_WARNING;
        });
        try {
            $started = session_start();
        } finally {
            restore_error_handler();
        }
        if (!$started) {
            throw new RuntimeException('Não foi possível iniciar a sessão.');
        }
    }

    public static function regenerate(): void
    {
        self::start();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Não foi possível renovar a sessão.');
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}
