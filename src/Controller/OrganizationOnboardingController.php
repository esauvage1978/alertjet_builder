<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @deprecated Utiliser {@see EnvironmentSetupController} (parcours /initialisation).
 */
final class OrganizationOnboardingController extends AbstractController
{
    #[Route('/premiere-organisation', name: 'app_organization_onboarding')]
    public function legacy(): Response
    {
        return $this->redirectToRoute('app_environment_setup_organization', [], Response::HTTP_FOUND);
    }
}
