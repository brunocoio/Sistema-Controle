<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class View
{
    public static function escape(mixed $value): string
    {
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function icon(string $name): string
    {
        return match ($name) {
            'edit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>',
            'activate' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/></svg>',
            'deactivate' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>',
            'delete' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>',
            'info' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>',
            'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1z"/></svg>',
            'athletes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.4 18.6c-2-2-1.6-7.2 2.3-11.1s9.1-4.3 11.1-2.3 1.6 7.2-2.3 11.1-9.1 4.3-11.1 2.3Z"/><path d="m8 8 8 8m-6-10 2-2m0 6 2-2m0 6 2-2"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8M17 14a5 5 0 0 1 3.5 4.8"/></svg>',
            'events' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V4m0 1c5-3 8 3 15 0v10c-7 3-10-3-15 0"/><path d="M8 8h.01M14 11h.01"/></svg>',
            'finance' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="15" rx="2"/><path d="M3 10h18m-4 5h2"/><circle cx="7" cy="4" r="2"/><path d="M7 3v2"/></svg>',
            'my-finance' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="7" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><circle cx="18" cy="16" r="4"/><path d="M18 14v4m-1.3-2.8h2.6"/></svg>',
            'finance-team' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 20h18M5 17v-5m5 5V7m5 10v-8m5 8V4"/><path d="m4 9 6-5 5 3 5-4"/></svg>',
            'inventory' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18v14H3zM3 7l2-4h14l2 4M8 11h8M8 15h5"/><path d="M7 3v4m10-4v4"/></svg>',
            'modules' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2"/></svg>',
            'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
            'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg>',
            'arrow-right' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>',
            'arrow-left' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>',
            'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>',
            'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>',
            default => '',
        };
    }

    public static function assetUrl(string $path, array $config): string
    {
        $prefix = '';
        if (str_starts_with($path, '../assets/')) {
            $prefix = '../';
            $path = substr($path, 3);
        } elseif (str_starts_with($path, './assets/')) {
            $prefix = './';
            $path = substr($path, 2);
        } elseif (str_starts_with($path, '../modules/')) {
            $prefix = '../';
            $path = substr($path, 3);
        }
        $isGlobalAsset = preg_match('#\Aassets/[A-Za-z0-9._/-]+\z#D', $path) === 1;
        $isModuleAsset = preg_match('#\Amodules/[a-z0-9-]+/Assets/[A-Za-z0-9._/-]+\z#D', $path) === 1;
        if (!$isGlobalAsset && !$isModuleAsset) {
            throw new RuntimeException('Caminho de recurso local inválido.');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Caminho de recurso local inválido.');
            }
        }
        $assetFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $contentVersion = is_file($assetFile) ? hash_file('sha256', $assetFile) : false;
        $version = is_string($contentVersion)
            ? substr($contentVersion, 0, 16)
            : ($config['assets']['version'] ?? '1.0.0');
        if (!is_string($version) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $version) !== 1) {
            throw new RuntimeException('Versão de recursos estáticos inválida.');
        }

        return $prefix . $path . '?v=' . rawurlencode($version);
    }

    public static function renderPublic(string $page, array $data, array $config): string
    {
        if (preg_match('/\A[a-z][a-z0-9_-]*\z/', $page) !== 1) {
            throw new RuntimeException('Identificador de página inválido.');
        }
        $viewsRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Views');
        if ($viewsRoot === false) {
            throw new RuntimeException('As views públicas não estão disponíveis.');
        }
        $pagesRoot = realpath($viewsRoot . DIRECTORY_SEPARATOR . 'Pages' . DIRECTORY_SEPARATOR . 'Public');
        if ($pagesRoot === false) {
            throw new RuntimeException('As páginas públicas não estão disponíveis.');
        }
        $pagePath = realpath($pagesRoot . DIRECTORY_SEPARATOR . $page . '.php');
        $pagePrefix = rtrim($pagesRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($pagePath === false || !str_starts_with($pagePath, $pagePrefix) || !is_file($pagePath)) {
            throw new RuntimeException('A página solicitada não está disponível.');
        }
        $layoutRoot = realpath($viewsRoot . DIRECTORY_SEPARATOR . 'Layout');
        $layoutPath = $layoutRoot === false ? false : realpath($layoutRoot . DIRECTORY_SEPARATOR . 'layout.php');
        $layoutPrefix = $layoutRoot === false ? '' : rtrim($layoutRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($layoutRoot === false || $layoutPath === false || !str_starts_with($layoutPath, $layoutPrefix) || !is_file($layoutPath)) {
            throw new RuntimeException('O layout público não está disponível.');
        }
        $appName = (string) ($data['appName'] ?? $config['app']['name'] ?? 'Sistema FLAG');
        $pageTitle = (string) ($data['pageTitle'] ?? $appName);
        $pageDescription = (string) ($data['pageDescription'] ?? 'Plataforma em desenvolvimento para organização de FLAG Football.');
        $pageStylesheets = is_array($data['pageStylesheets'] ?? null) ? $data['pageStylesheets'] : [];
        $pageBodyClass = (string) ($data['pageBodyClass'] ?? '');
        $escape = [self::class, 'escape'];
        $content = self::capture(static function () use ($pagePath, $data, $escape): void {
            extract($data, EXTR_SKIP);
            require $pagePath;
        });
        return self::capture(static function () use ($layoutPath, $appName, $pageTitle, $pageDescription, $pageStylesheets, $pageBodyClass, $content, $escape, $config): void {
            require $layoutPath;
        });
    }

    private static function capture(callable $render): string
    {
        ob_start();
        try {
            $render();
            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
