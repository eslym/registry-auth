<?php

namespace App\Lib;

/**
 * Helpers for toast
 * Usage:
 * - response()->with('toast', Toast::success(...))
 */
enum Toast: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';

    public static function success(string $title, ?string $description = null): array
    {
        return self::make(self::SUCCESS, $title, $description);
    }

    public static function make(Toast $type, string $title, ?string $description = null): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'description' => $description,
        ];
    }

    public static function error(string $title, ?string $description = null): array
    {
        return self::make(self::ERROR, $title, $description);
    }

    public static function warning(string $title, ?string $description = null): array
    {
        return self::make(self::WARNING, $title, $description);
    }

    public static function warn(string $title, ?string $description = null): array
    {
        return self::make(self::WARNING, $title, $description);
    }

    public static function info(string $title, ?string $description = null): array
    {
        return self::make(self::INFO, $title, $description);
    }
}
