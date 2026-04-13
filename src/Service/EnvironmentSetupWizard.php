<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\EnvironmentWizardStep;

final class EnvironmentSetupWizard
{
    public function resolveStep(User $user): EnvironmentWizardStep
    {
        if (!$user->hasAnyOrganization()) {
            return EnvironmentWizardStep::Organization;
        }

        $org = $user->getOrganizations()->first();
        if (!$org instanceof Organization) {
            return EnvironmentWizardStep::Organization;
        }

        if (!$org->isPlanExempt() && $org->getPlan() === null) {
            return EnvironmentWizardStep::Plan;
        }

        if (trim($user->getDisplayName() ?? '') === '') {
            return EnvironmentWizardStep::Profile;
        }

        if ($org->getProjects()->isEmpty()) {
            return EnvironmentWizardStep::Project;
        }

        return EnvironmentWizardStep::Complete;
    }

    public function routeNameForStep(EnvironmentWizardStep $step): string
    {
        return match ($step) {
            EnvironmentWizardStep::Organization => 'app_environment_setup_organization',
            EnvironmentWizardStep::Plan => 'app_environment_setup_plan',
            EnvironmentWizardStep::Profile => 'app_environment_setup_profile',
            EnvironmentWizardStep::Project => 'app_environment_setup_project',
            EnvironmentWizardStep::Complete => throw new \LogicException('Étape « complete » sans route.'),
        };
    }

    public function stepNumber(EnvironmentWizardStep $step): int
    {
        return match ($step) {
            EnvironmentWizardStep::Organization => 1,
            EnvironmentWizardStep::Plan => 2,
            EnvironmentWizardStep::Profile => 3,
            EnvironmentWizardStep::Project => 4,
            EnvironmentWizardStep::Complete => 5,
        };
    }
}
