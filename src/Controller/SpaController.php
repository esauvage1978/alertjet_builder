<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Shell HTML unique pour l’interface React (invités et connectés) sous /app.
 */
final class SpaController extends AbstractController
{
    #[Route('/app/login', name: 'app_login', methods: ['GET'], priority: 40)]
    public function loginPage(): Response
    {
        return $this->render('spa.html.twig');
    }

    /** Ancienne URL ; conservée pour les favoris et liens externes. */
    #[Route('/app/connexion', name: 'app_login_legacy_redirect', methods: ['GET'], priority: 39)]
    public function loginLegacyRedirect(): Response
    {
        return $this->redirectToRoute('app_login', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/app', name: 'app_spa', methods: ['GET'])]
    #[Route('/app/{reactPath}', name: 'app_spa_catch', requirements: ['reactPath' => '.+'], methods: ['GET'], priority: -50)]
    public function app(): Response
    {
        return $this->render('spa.html.twig');
    }
}
