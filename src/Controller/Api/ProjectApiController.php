<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Service\ProjectAuditHelper;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProjectApiController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserActionLogger $userActionLogger,
        private readonly ProjectAuditHelper $projectAuditHelper,
    ) {
    }

    #[Route('/api/projects', name: 'api_projects_list', methods: ['GET'])]
    public function list(): Response
    {
        $projects = $this->projectRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->json(array_map(fn (Project $p) => $this->serializeProject($p), $projects));
    }

    #[Route('/api/projects', name: 'api_projects_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $name = isset($data['name']) && \is_string($data['name']) ? trim($data['name']) : '';
        if ($name === '' || mb_strlen($name) > 180) {
            return $this->json(['error' => 'invalid_name'], Response::HTTP_BAD_REQUEST);
        }

        $project = (new Project())
            ->setName($name)
            ->setWebhookToken(bin2hex(random_bytes(16)));

        $this->em->persist($project);
        $this->em->flush();

        $actor = $this->getUser();
        $this->userActionLogger->log(
            'API_PROJECT_CREATED',
            $actor instanceof User ? $actor : null,
            null,
            array_merge($this->projectAuditHelper->contextualize($project, $project->getOrganization()), [
                'event' => 'created',
                'source' => 'POST /api/projects',
                'initialSnapshot' => $this->projectAuditHelper->snapshot($project),
                'unauthenticated' => !$actor instanceof User,
            ]),
            $request,
        );

        return $this->json($this->serializeProject($project), Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function serializeProject(Project $p): array
    {
        return [
            'id' => $p->getId(),
            'publicToken' => $p->getPublicToken(),
            'name' => $p->getName(),
            'webhookToken' => $p->getWebhookToken(),
            'webhookUrl' => $this->generateUrl(
                'api_webhook_receive',
                ['token' => $p->getWebhookToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            'createdAt' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
