<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
final class ProjectPublicTokenListener
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    public function __invoke(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Project) {
            return;
        }
        if ($entity->getPublicToken() !== '') {
            return;
        }

        $this->projectRepository->assignUniquePublicToken($entity);
    }
}
