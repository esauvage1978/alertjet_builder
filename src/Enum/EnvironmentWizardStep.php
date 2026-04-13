<?php

declare(strict_types=1);

namespace App\Enum;

enum EnvironmentWizardStep: string
{
    case Organization = 'organization';
    case Plan = 'plan';
    case Profile = 'profile';
    case Project = 'project';
    case Complete = 'complete';
}
