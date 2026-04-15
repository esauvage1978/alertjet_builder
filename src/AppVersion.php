<?php

declare(strict_types=1);

namespace App;

/**
 * Version de l’application (semver), lue depuis le fichier {@see PROJECT_ROOT/VERSION}.
 */
final class AppVersion
{
    private static ?string $semver = null;

    public static function semver(): string
    {
        if (self::$semver !== null) {
            return self::$semver;
        }

        $path = dirname(__DIR__).'/VERSION';
        $raw = @\is_readable($path) ? (string) file_get_contents($path) : '';
        $raw = trim($raw);
        self::$semver = preg_match('/^\d+\.\d+\.\d+$/', $raw) === 1 ? $raw : '0.0.0';

        return self::$semver;
    }

    public static function label(): string
    {
        return 'V'.self::semver();
    }
}
