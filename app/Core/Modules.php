<?php

declare(strict_types=1);

namespace App\Core;

final class Modules
{
    private const CATALOG = [
        'atletas' => ['title' => 'Atletas', 'description' => 'Cadastros e gestão esportiva da equipe.', 'admin_href' => 'atletas', 'icon' => 'athletes', 'toggleable' => true],
        'eventos' => ['title' => 'Eventos', 'description' => 'Jogos, treinos, amistosos e compromissos.', 'admin_href' => 'eventos', 'icon' => 'events', 'toggleable' => true],
    ];

    private const FILES = [
        'autenticacao' => [
            'modules/autenticacao/Controllers/AdminController.php',
            'modules/autenticacao/Controllers/LoginController.php',
            'modules/autenticacao/Controllers/LogoutController.php',
            'modules/autenticacao/Views/admin.php',
            'modules/autenticacao/Views/login.php',
        ],
        'atletas' => [
            'modules/atletas/Controllers/AthleteController.php',
            'modules/atletas/Models/AthleteModel.php',
            'modules/atletas/Views/index.php',
            'modules/atletas/Views/form.php',
            'modules/atletas/Views/categories.php',
            'modules/atletas/Assets/atletas.css',
            'modules/atletas/Assets/atletas.js',
        ],
        'eventos' => [
            'modules/eventos/Controllers/EventController.php',
            'modules/eventos/Models/EventModel.php',
            'modules/eventos/Views/index.php',
            'modules/eventos/Views/form.php',
            'modules/eventos/Views/public.php',
            'modules/eventos/Assets/eventos.css',
            'modules/eventos/Assets/eventos.js',
        ],
    ];

    public static function available(string $name): bool
    {
        return self::installed($name) && ModuleSettings::moduleEnabled($name);
    }

    public static function installed(string $name): bool
    {
        if (!isset(self::FILES[$name])) return false;
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) return false;
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        foreach (self::FILES[$name] ?? [] as $relativePath) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $resolvedPath = realpath($path);
            if ($resolvedPath === false || is_link($path) || !is_file($resolvedPath)
                || !str_starts_with($resolvedPath, $rootPrefix)) {
                return false;
            }
        }
        return true;
    }

    public static function catalog(): array
    {
        return self::CATALOG;
    }

    public static function isToggleable(string $name): bool
    {
        return (self::CATALOG[$name]['toggleable'] ?? false) === true;
    }

    public static function activeAdminModules(): array
    {
        return array_filter(self::CATALOG, static fn(array $module, string $name): bool => self::available($name), ARRAY_FILTER_USE_BOTH);
    }
}
