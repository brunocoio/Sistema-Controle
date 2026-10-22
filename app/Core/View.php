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
        $escape = [self::class, 'escape'];
        $content = self::capture(static function () use ($pagePath, $data, $escape): void {
            extract($data, EXTR_SKIP);
            require $pagePath;
        });
        return self::capture(static function () use ($layoutPath, $appName, $pageTitle, $content, $escape, $config): void {
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
