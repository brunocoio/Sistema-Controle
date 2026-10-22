<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class ModuleSettings
{
    private static ?bool $schemaAvailable = null;
    /** @var array<string, bool> */
    private static array $moduleStates = [];
    /** @var array<int, bool> */
    private static array $sidebarStates = [];

    public static function schemaAvailable(): bool
    {
        if (self::$schemaAvailable !== null) return self::$schemaAvailable;
        try {
            $statement = Database::connection()->query(
                "SELECT to_regclass('app_module_settings') IS NOT NULL AND to_regclass('app_user_preferences') IS NOT NULL"
            );
            self::$schemaAvailable = in_array($statement->fetchColumn(), [true, 1, '1', 't', 'true'], true);
        } catch (Throwable) {
            self::$schemaAvailable = false;
        }
        return self::$schemaAvailable;
    }

    public static function moduleEnabled(string $module): bool
    {
        if (!Modules::isToggleable($module)) return true;
        if (array_key_exists($module, self::$moduleStates)) return self::$moduleStates[$module];
        if (!self::schemaAvailable()) return self::$moduleStates[$module] = true;
        try {
            $statement = Database::connection()->prepare('SELECT enabled FROM app_module_settings WHERE module_key = :module');
            $statement->execute(['module' => $module]);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);
            return self::$moduleStates[$module] = $row === false
                ? true
                : in_array($row['enabled'] ?? null, [true, 1, '1', 't', 'true'], true);
        } catch (Throwable) {
            return self::$moduleStates[$module] = true;
        }
    }

    public static function setModuleEnabled(string $module, bool $enabled, int $userId): void
    {
        if (!Modules::isToggleable($module) || $userId < 1) {
            throw new \InvalidArgumentException('Módulo ou usuário inválido.');
        }
        if (!self::schemaAvailable()) {
            throw new \RuntimeException('A estrutura de controle de módulos não está instalada.');
        }
        $statement = Database::connection()->prepare(
            'INSERT INTO app_module_settings (module_key, enabled, updated_by, updated_at)
             VALUES (:module, :enabled, :user_id, CURRENT_TIMESTAMP)
             ON CONFLICT (module_key) DO UPDATE
             SET enabled = EXCLUDED.enabled, updated_by = EXCLUDED.updated_by, updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute(['module' => $module, 'enabled' => $enabled ? 'true' : 'false', 'user_id' => $userId]);
        self::$moduleStates[$module] = $enabled;
    }

    public static function sidebarCollapsed(int $userId): bool
    {
        if ($userId < 1) return false;
        if (array_key_exists($userId, self::$sidebarStates)) return self::$sidebarStates[$userId];
        if (!self::schemaAvailable()) return self::$sidebarStates[$userId] = false;
        try {
            $statement = Database::connection()->prepare(
                'SELECT sidebar_collapsed FROM app_user_preferences WHERE user_id = :user_id'
            );
            $statement->execute(['user_id' => $userId]);
            $value = $statement->fetchColumn();
            return self::$sidebarStates[$userId] = in_array($value, [true, 1, '1', 't', 'true'], true);
        } catch (Throwable) {
            return self::$sidebarStates[$userId] = false;
        }
    }

    public static function setSidebarCollapsed(int $userId, bool $collapsed): void
    {
        if ($userId < 1) throw new \InvalidArgumentException('Usuário inválido.');
        if (!self::schemaAvailable()) {
            throw new \RuntimeException('A estrutura de preferências do usuário não está instalada.');
        }
        $statement = Database::connection()->prepare(
            'INSERT INTO app_user_preferences (user_id, sidebar_collapsed, updated_at)
             VALUES (:user_id, :collapsed, CURRENT_TIMESTAMP)
             ON CONFLICT (user_id) DO UPDATE
             SET sidebar_collapsed = EXCLUDED.sidebar_collapsed, updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute(['user_id' => $userId, 'collapsed' => $collapsed ? 'true' : 'false']);
        self::$sidebarStates[$userId] = $collapsed;
    }
}
