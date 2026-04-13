<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case Administrateur = 'ROLE_ADMIN';
    case Gestionnaire = 'ROLE_GESTIONNAIRE';
    case Utilisateur = 'ROLE_USER';
    case Client = 'ROLE_CLIENT';

    public function label(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur',
            self::Gestionnaire => 'Gestionnaire',
            self::Utilisateur => 'Utilisateur',
            self::Client => 'Client',
        };
    }
}
