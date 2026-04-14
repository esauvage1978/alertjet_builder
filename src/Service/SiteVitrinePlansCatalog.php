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
        $catalog = $this->getCatalog();

        /** @var list<array<string, mixed>> $plans */
        $plans = $catalog['plans'] ?? [];

        return $plans;
    }

    /**
     * @return array{meta?: array<string, mixed>, plans: list<array<string, mixed>>, packs?: list<array<string, mixed>>}
     */
    public function getCatalog(): array
    {
        if (is_readable($this->plansJsonPath)) {
            try {
                /** @var array{meta?: array<string, mixed>, plans?: list<array<string, mixed>>, packs?: list<array<string, mixed>>} $data */
                $data = json_decode((string) file_get_contents($this->plansJsonPath), true, 512, JSON_THROW_ON_ERROR);
                $plans = $data['plans'] ?? [];
                $packs = $data['packs'] ?? [];
                $meta = $data['meta'] ?? null;

                return [
                    'meta' => \is_array($meta) ? $meta : null,
                    'plans' => \is_array($plans) ? $plans : [],
                    'packs' => \is_array($packs) ? $packs : [],
                ];
            } catch (\JsonException) {
            }
        }

        return $this->fallbackCatalog();
    }

    /**
     * @return array{meta?: array<string, mixed>, plans: list<array<string, mixed>>, packs?: list<array<string, mixed>>}
     */
    private function fallbackCatalog(): array
    {
        /** @var array{meta?: array<string, mixed>, plans: list<array<string, mixed>>, packs?: list<array<string, mixed>>} $data */
        $data = json_decode(self::FALLBACK_JSON, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    private const FALLBACK_JSON = <<<'JSON'
{
  "meta": {
    "currency": "EUR",
    "pricesAreExclTax": true,
    "vatNote": "Tous les prix ci-dessous sont indiqués hors taxes (HT) : la TVA applicable sera ajoutée au moment de la facturation.",
    "eventDefinition": "1 déclaration d’incident = 1 événement. 1 réponse à un incident = 1 événement."
  },
  "plans": [
    {
      "id": "free",
      "name": "Plan Free",
      "emoji": "🟢",
      "priceDisplay": "0€",
      "period": "/ mois",
      "badge": null,
      "accent": "emerald",
      "highlight": false,
      "target": null,
      "targetLabel": null,
      "features": ["100 événements inclus par période", "1 projet maximum", "Pas de dépassement de quota — passage à une offre payante requis au-delà"],
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
      "features": ["5 000 événements inclus (~0,0018 € HT par événement)", "Projets illimités", "Dépassement possible via packs d’événements"],
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
      "badge": "Recommandé",
      "accent": "violet",
      "highlight": true,
      "target": null,
      "targetLabel": null,
      "features": ["50 000 événements inclus (~0,00058 € HT par événement)", "Projets illimités", "Packs d’extension à tarifs dégressifs"],
      "footerNote": null,
      "footerHighlight": null,
      "footerSubnote": null,
      "ctaLabel": "Choisir Pro"
    }
  ],
  "packs": [
    { "planId": "starter", "label": "+1 000 événements", "priceDisplay": "2 € HT" },
    { "planId": "starter", "label": "+5 000 événements", "priceDisplay": "8 € HT" },
    { "planId": "pro", "label": "+10 000 événements", "priceDisplay": "5 € HT" },
    { "planId": "pro", "label": "+50 000 événements", "priceDisplay": "20 € HT" }
  ]
}
JSON;
}
