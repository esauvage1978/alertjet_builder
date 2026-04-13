<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserActionLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class UserActionLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public function log(
        string $action,
        ?User $user = null,
        ?string $actorEmail = null,
        ?array $details = null,
        ?Request $request = null,
    ): void {
        $row = new UserActionLog();
        $row->setAction($action);
        $row->setUser($user);
        $row->setActorEmail($actorEmail ?? $user?->getEmail());
        $row->setDetails($details);

        if ($request !== null) {
            $row->setIp($request->getClientIp());
            $row->setUserAgent($request->headers->get('User-Agent'));
        }

        try {
            $this->entityManager->persist($row);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('UserActionLogger : échec d’écriture du journal d’actions.', [
                'action' => $action,
                'exception' => $e->getMessage(),
                'exceptionClass' => $e::class,
            ]);
        }
    }
}
