<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Charge les offres décrites sur le site vitrine (fichier partagé plans.json).
 */
final class SiteVitrinePlansCatalog
{
    public function __construct(
        private readonly string $plansJsonPath,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPlans(): array
    {
        if (is_readable($this->plansJsonPath)) {
            try {
                /** @var array{plans?: list<array<string, mixed>>} $data */
                $data = json_decode((string) file_get_contents($this->plansJsonPath), true, 512, JSON_THROW_ON_ERROR);
                $plans = $data['plans'] ?? [];

                return \is_array($plans) ? $plans : [];
            } catch (\JsonException) {
            }
        }

        return $this->fallbackPlans();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackPlans(): array
    {
        return json_decode(self::FALLBACK_JSON, true, 512, JSON_THROW_ON_ERROR)['plans'];
    }

    private const FALLBACK_JSON = <<<'JSON'
{
  "plans": [
    {
      "id": "free",
      "name": "Plan Free",
      "emoji": "🟢",
      "priceDisplay": "0€",
      "period": "/ mois",
      "badge": "Obligatoire",
      "accent": "emerald",
      "highlight": false,
      "target": null,
      "targetLabel": null,
      "features": ["1 projet", "50–100 incidents / mois", "Alertes basiques"],
      "footerNote": null,
      "footerHighlight": null,
      "ctaLabel": "Commencer gratuitement"
    },
    {
      "id": "starter",
      "name": "Plan Starter",
      "emoji": "🔵",
      "priceDisplay": "9€",
      "period": "/ mois",
      "badge": null,
      "accent": "blue",
      "highlight": false,
      "target": null,
      "targetLabel": null,
      "features": ["3 projets", "1 000 incidents / mois", "E-mail + webhook alert", "Historique limité"],
      "footerNote": null,
      "footerHighlight": null,
      "ctaLabel": "Choisir Starter"
    },
    {
      "id": "pro",
      "name": "Plan Pro",
      "emoji": "🟣",
      "priceDisplay": "29€",
      "period": "/ mois",
      "badge": null,
      "accent": "violet",
      "highlight": true,
      "target": null,
      "targetLabel": null,
      "features": ["10 projets", "10 000 incidents / mois", "Slack / webhook avancé", "Priorités / tags", "Historique complet"],
      "footerNote": null,
      "footerHighlight": null,
      "footerSubnote": null,
      "ctaLabel": "Choisir Pro"
    }
  ]
}
JSON;
}
