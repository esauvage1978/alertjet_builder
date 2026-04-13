<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Identifiants alignés sur {@see SiteVitrinePlansCatalog} / site_vitrine/src/data/plans.json.
 */
enum OrganizationPlan: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Plan Free',
            self::Starter => 'Plan Starter',
            self::Pro => 'Plan Pro',
        };
    }
}
