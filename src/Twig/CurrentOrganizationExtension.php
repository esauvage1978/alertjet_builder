<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Organization;
use App\Service\CurrentOrganizationService;
use Twig\Attribute\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

#[AsTwigExtension]
final class CurrentOrganizationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly CurrentOrganizationService $currentOrganizationService,
    ) {
    }

    public function getGlobals(): array
    {
        /** @var Organization|null $current */
        $current = $this->currentOrganizationService->getCurrentOrganization();

        return [
            'current_organization' => $current,
            'organizations_sorted' => $this->currentOrganizationService->getOrganizationsSorted(),
        ];
    }
}
