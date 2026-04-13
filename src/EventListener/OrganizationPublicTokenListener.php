<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
final class OrganizationPublicTokenListener
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
    ) {
    }

    public function __invoke(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Organization) {
            return;
        }
        if ($entity->getPublicToken() !== '') {
            return;
        }

        $this->organizationRepository->assignUniquePublicToken($entity);
    }
}
