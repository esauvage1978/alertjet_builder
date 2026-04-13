<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Entity\Organization;
use App\Entity\User;
use App\Service\SiteVitrinePlansCatalog;
use App\Util\AvatarForegroundPalette;
use App\Util\AvatarPalette;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/setup')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SetupWizardApiController extends AbstractController
{
    #[Route('/organisation', name: 'api_setup_organization', methods: ['GET'])]
    public function organization(CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        $org = $user->getPrimaryOrganization();
        $organizationPayload = null;
        if ($org instanceof Organization) {
            $organizationPayload = [
                'name' => $org->getName(),
                'billingLine1' => $org->getBillingLine1() ?? '',
                'billingLine2' => $org->getBillingLine2() ?? '',
                'billingPostalCode' => $org->getBillingPostalCode() ?? '',
                'billingCity' => $org->getBillingCity() ?? '',
                'billingCountry' => $org->getBillingCountry() ?? '',
            ];
        }

        return $this->json([
            // Même id que framework.form.csrf_protection.token_id (submit), pas le nom du FormType.
            'csrf' => $csrfTokenManager->getToken('submit')->getValue(),
            'action' => $this->generateUrl('app_environment_setup_organization'),
            'organization' => $organizationPayload,
        ]);
    }

    #[Route('/plans', name: 'api_setup_plans', methods: ['GET'])]
    public function plans(
        SiteVitrinePlansCatalog $siteVitrinePlansCatalog,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        $org = $user->getPrimaryOrganization();
        $selectedPlanId = $org?->getPlan()?->value;

        return $this->json([
            'plans' => $siteVitrinePlansCatalog->getPlans(),
            'csrf' => $csrfTokenManager->getToken('environment_plan')->getValue(),
            'action' => $this->generateUrl('app_environment_setup_plan'),
            'selectedPlanId' => $selectedPlanId,
        ]);
    }

    #[Route('/profil', name: 'api_setup_profil', methods: ['GET'])]
    public function profile(CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        return $this->json([
            'csrf' => $csrfTokenManager->getToken('submit')->getValue(),
            'action' => $this->generateUrl('app_environment_setup_profile'),
            'displayName' => $user->getDisplayName(),
            'avatarInitialsCustom' => $user->getAvatarInitialsCustom(),
            'avatarColor' => $user->getAvatarColor(),
            'avatarForegroundColor' => $user->getAvatarForegroundColor(),
            'avatarBgChoices' => AvatarPalette::choices(),
            'avatarFgChoices' => AvatarForegroundPalette::choices(),
        ]);
    }

    #[Route('/projet', name: 'api_setup_projet', methods: ['GET'])]
    public function project(CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        return $this->json([
            'csrf' => $csrfTokenManager->getToken('submit')->getValue(),
            'action' => $this->generateUrl('app_environment_setup_project'),
        ]);
    }
}
