<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Couleurs de fond du disque d’avatar (palette étendue, #RRGGBB).
 */
final class AvatarPalette
{
    public const DEFAULT_HEX = '#0C1929';

    /**
     * @return list<string> 42 codes hex distincts
     */
    public static function hexList(): array
    {
        return [
            '#0891B2', '#0D9488', '#14B8A6', '#10B981', '#22C55E', '#65A30D', '#84CC16', '#CA8A04',
            '#EAB308', '#F59E0B', '#F97316', '#EA580C', '#EF4444', '#F43F5E', '#E11D48', '#EC4899',
            '#DB2777', '#D946EF', '#A855F7', '#8B5CF6', '#7C3AED', '#6366F1', '#4F46E5', '#3B82F6',
            '#0EA5E9', '#06B6D4', '#22D3EE', '#0284C7', '#075985', '#1E40AF', '#312E81', '#4C1D95',
            '#78716C', '#57534E', '#44403C', '#14532D', '#166534', '#713F12', '#78350F', '#881337',
            '#9F1239', '#701A75',
        ];
    }

    /**
     * @return array<string, string> clé de traduction => valeur hex (#RRGGBB)
     */
    public static function choices(): array
    {
        $out = [];
        foreach (self::hexList() as $i => $hex) {
            $out[sprintf('avatar.palette.bg_%02d', $i + 1)] = $hex;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function allowedHexValues(): array
    {
        return array_values(self::choices());
    }

    public static function isAllowedHex(string $hex): bool
    {
        return \in_array(strtoupper($hex), array_map(strtoupper(...), self::allowedHexValues()), true);
    }
}
