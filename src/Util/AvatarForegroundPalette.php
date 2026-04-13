<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Couleurs du texte des initiales sur la bulle (palette étendue, #RRGGBB).
 */
final class AvatarForegroundPalette
{
    public const DEFAULT_HEX = '#FFFFFF';

    /**
     * @return list<string> 42 codes hex distincts (clair → foncé + teintes)
     */
    public static function hexList(): array
    {
        return [
            '#FFFFFF', '#F8FAFC', '#F1F5F9', '#E2E8F0', '#CBD5E1', '#94A3B8', '#64748B', '#475569',
            '#334155', '#1E293B', '#0F172A', '#020617', '#000000', '#FEF3C7', '#FDE68A', '#FCD34D',
            '#FBBF24', '#FECACA', '#FCA5A5', '#F87171', '#EF4444', '#DBEAFE', '#BFDBFE', '#93C5FD',
            '#60A5FA', '#E9D5FF', '#DDD6FE', '#C4B5FD', '#A78BFA', '#FCE7F3', '#FBCFE8', '#F9A8D4',
            '#DCFCE7', '#BBF7D0', '#FFEDD5', '#FED7AA', '#E0E7FF', '#C7D2FE', '#F3E8FF', '#EDE9FE',
            '#CCFBF1', '#99F6E4',
        ];
    }

    /**
     * @return array<string, string> clé de traduction => valeur hex (#RRGGBB)
     */
    public static function choices(): array
    {
        $out = [];
        foreach (self::hexList() as $i => $hex) {
            $out[sprintf('avatar.palette.fg_%02d', $i + 1)] = $hex;
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
