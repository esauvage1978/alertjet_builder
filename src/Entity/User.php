<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use App\Util\AvatarForegroundPalette;
use App\Util\AvatarPalette;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet e-mail.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerificationExpiresAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetExpiresAt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $displayName = null;

    /** Couleur de l’avatar (#RRGGBB), parmi {@see AvatarPalette}. */
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $avatarColor = null;

    /** Couleur du texte des initiales (#RRGGBB), parmi {@see AvatarForegroundPalette}. */
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $avatarForegroundColor = null;

    /**
     * Initiales affichées sur la bulle (1–3 caractères). Vide = calcul automatique à partir du nom / e-mail.
     */
    #[ORM\Column(name: 'avatar_initials_custom', length: 12, nullable: true)]
    private ?string $avatarInitialsCustom = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $environmentInitializedAt = null;

    /**
     * Invité par une organisation : après mot de passe (e-mail), l’utilisateur doit finaliser
     * nom d’affichage, initiales et couleurs avant d’accéder au reste de l’app.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $pendingProfileOnboarding = false;

    /** Jeton unique pour accepter l’invitation (mot de passe + validation), TTL géré via {@see self::organizationInviteExpiresAt}. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $organizationInviteToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $organizationInviteExpiresAt = null;

    /** @var Collection<int, Organization> */
    #[ORM\ManyToMany(targetEntity: Organization::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_organization')]
    private Collection $organizations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->organizations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        if ($this->roles === []) {
            return ['ROLE_USER'];
        }

        return array_values(array_unique($this->roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): self
    {
        $this->emailVerifiedAt = $emailVerifiedAt;

        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): self
    {
        $this->emailVerificationToken = $emailVerificationToken;

        return $this;
    }

    public function getEmailVerificationExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailVerificationExpiresAt;
    }

    public function setEmailVerificationExpiresAt(?\DateTimeImmutable $emailVerificationExpiresAt): self
    {
        $this->emailVerificationExpiresAt = $emailVerificationExpiresAt;

        return $this;
    }

    public function clearEmailVerification(): self
    {
        $this->emailVerificationToken = null;
        $this->emailVerificationExpiresAt = null;

        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): self
    {
        $this->passwordResetToken = $passwordResetToken;

        return $this;
    }

    public function getPasswordResetExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetExpiresAt;
    }

    public function setPasswordResetExpiresAt(?\DateTimeImmutable $passwordResetExpiresAt): self
    {
        $this->passwordResetExpiresAt = $passwordResetExpiresAt;

        return $this;
    }

    public function clearPasswordReset(): self
    {
        $this->passwordResetToken = null;
        $this->passwordResetExpiresAt = null;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName !== null ? trim($displayName) : null;
        if ($this->displayName === '') {
            $this->displayName = null;
        }

        return $this;
    }

    public function getDisplayNameForGreeting(): string
    {
        $name = $this->displayName;
        if ($name !== null && $name !== '') {
            return $name;
        }

        return $this->email;
    }

    public function getAvatarColor(): ?string
    {
        return $this->avatarColor;
    }

    public function setAvatarColor(?string $avatarColor): self
    {
        if ($avatarColor === null || $avatarColor === '') {
            $this->avatarColor = null;

            return $this;
        }

        $avatarColor = strtoupper(trim($avatarColor));
        if (!AvatarPalette::isAllowedHex($avatarColor)) {
            $this->avatarColor = null;

            return $this;
        }

        $this->avatarColor = $avatarColor;

        return $this;
    }

    public function getAvatarColorOrDefault(): string
    {
        $c = $this->avatarColor;
        if ($c !== null && $c !== '' && AvatarPalette::isAllowedHex($c)) {
            return $c;
        }

        return AvatarPalette::DEFAULT_HEX;
    }

    public function getAvatarForegroundColor(): ?string
    {
        return $this->avatarForegroundColor;
    }

    public function setAvatarForegroundColor(?string $avatarForegroundColor): self
    {
        if ($avatarForegroundColor === null || $avatarForegroundColor === '') {
            $this->avatarForegroundColor = null;

            return $this;
        }

        $avatarForegroundColor = strtoupper(trim($avatarForegroundColor));
        if (!AvatarForegroundPalette::isAllowedHex($avatarForegroundColor)) {
            $this->avatarForegroundColor = null;

            return $this;
        }

        $this->avatarForegroundColor = $avatarForegroundColor;

        return $this;
    }

    public function getAvatarForegroundColorOrDefault(): string
    {
        $c = $this->avatarForegroundColor;
        if ($c !== null && $c !== '' && AvatarForegroundPalette::isAllowedHex($c)) {
            return $c;
        }

        return AvatarForegroundPalette::DEFAULT_HEX;
    }

    public function getAvatarInitialsCustom(): ?string
    {
        return $this->avatarInitialsCustom;
    }

    public function setAvatarInitialsCustom(?string $avatarInitialsCustom): self
    {
        if ($avatarInitialsCustom === null || trim($avatarInitialsCustom) === '') {
            $this->avatarInitialsCustom = null;

            return $this;
        }

        $normalized = mb_strtoupper(mb_substr(trim($avatarInitialsCustom), 0, 3, 'UTF-8'), 'UTF-8');
        $this->avatarInitialsCustom = $normalized !== '' ? $normalized : null;

        return $this;
    }

    /**
     * Initiales calculées à partir du nom affiché ou de l’e-mail (sans tenir compte du champ personnalisé).
     */
    public static function initialsFromDisplayOrEmail(?string $displayName, string $email): string
    {
        $source = $displayName;
        if ($source === null || $source === '') {
            $source = $email;
        }

        $source = trim($source);
        $parts = preg_split('/\s+/u', $source, -1, PREG_SPLIT_NO_EMPTY);
        if (\is_array($parts) && \count($parts) >= 2) {
            $a = mb_substr($parts[0], 0, 1);
            $b = mb_substr($parts[1], 0, 1);

            return mb_strtoupper($a.$b, 'UTF-8');
        }

        $slice = mb_substr($source, 0, 2, 'UTF-8');

        return mb_strtoupper($slice, 'UTF-8');
    }

    public function getDeducedAvatarInitials(): string
    {
        return self::initialsFromDisplayOrEmail($this->displayName, $this->email);
    }

    public function getAvatarInitials(): string
    {
        $custom = $this->avatarInitialsCustom;
        if ($custom !== null && $custom !== '') {
            return $custom;
        }

        return $this->getDeducedAvatarInitials();
    }

    /**
     * Clé pour traduction Twig : user.role.{catalog} (hiérarchie : admin &gt; gestionnaire &gt; client &gt; utilisateur).
     */
    public function getPrimaryRoleCatalogKey(): string
    {
        $roles = $this->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return 'admin';
        }
        if (\in_array('ROLE_GESTIONNAIRE', $roles, true)) {
            return 'manager';
        }
        if (\in_array('ROLE_CLIENT', $roles, true)) {
            return 'client';
        }

        return 'user';
    }

    /**
     * Journal d’activité (/compte/activite) : non accessible au rôle simple « utilisateur ».
     */
    public function canViewActivityLog(): bool
    {
        return $this->getPrimaryRoleCatalogKey() !== 'user';
    }

    /**
     * Fiche « Mon organisation » (facturation / coordonnées) : hors rôle « utilisateur » seul.
     */
    public function canAccessOrganizationBillingPage(): bool
    {
        return $this->canViewActivityLog();
    }

    public function getRoleBadgeClass(): string
    {
        $roles = $this->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return 'badge-danger';
        }
        if (\in_array('ROLE_GESTIONNAIRE', $roles, true)) {
            return 'badge-role-manager';
        }
        if (\in_array('ROLE_CLIENT', $roles, true)) {
            return 'badge-warning';
        }

        return 'badge-secondary';
    }

    public function getEnvironmentInitializedAt(): ?\DateTimeImmutable
    {
        return $this->environmentInitializedAt;
    }

    public function setEnvironmentInitializedAt(?\DateTimeImmutable $environmentInitializedAt): self
    {
        $this->environmentInitializedAt = $environmentInitializedAt;

        return $this;
    }

    public function isAdministratorOrManager(): bool
    {
        $roles = $this->getRoles();

        return \in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_GESTIONNAIRE', $roles, true);
    }

    public function needsEnvironmentSetup(): bool
    {
        if (!$this->isAdministratorOrManager()) {
            return false;
        }

        return $this->environmentInitializedAt === null;
    }

    public function isPendingProfileOnboarding(): bool
    {
        return $this->pendingProfileOnboarding;
    }

    public function setPendingProfileOnboarding(bool $pendingProfileOnboarding): self
    {
        $this->pendingProfileOnboarding = $pendingProfileOnboarding;

        return $this;
    }

    public function getOrganizationInviteToken(): ?string
    {
        return $this->organizationInviteToken;
    }

    public function setOrganizationInviteToken(?string $organizationInviteToken): self
    {
        $this->organizationInviteToken = $organizationInviteToken;

        return $this;
    }

    public function getOrganizationInviteExpiresAt(): ?\DateTimeImmutable
    {
        return $this->organizationInviteExpiresAt;
    }

    public function setOrganizationInviteExpiresAt(?\DateTimeImmutable $organizationInviteExpiresAt): self
    {
        $this->organizationInviteExpiresAt = $organizationInviteExpiresAt;

        return $this;
    }

    public function clearOrganizationInvite(): self
    {
        $this->organizationInviteToken = null;
        $this->organizationInviteExpiresAt = null;

        return $this;
    }

    /** Invitation organisation encore active (lien renvoyable tant que non acceptée). */
    public function hasPendingOrganizationInvitation(): bool
    {
        return $this->organizationInviteToken !== null && $this->organizationInviteToken !== '';
    }

    /** @return Collection<int, Organization> */
    public function getOrganizations(): Collection
    {
        return $this->organizations;
    }

    public function addOrganization(Organization $organization): self
    {
        if (!$this->organizations->contains($organization)) {
            $this->organizations->add($organization);
            $organization->getUsers()->add($this);
        }

        return $this;
    }

    public function removeOrganization(Organization $organization): self
    {
        if ($this->organizations->removeElement($organization)) {
            $organization->getUsers()->removeElement($this);
        }

        return $this;
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations->contains($organization);
    }

    public function hasAnyOrganization(): bool
    {
        return !$this->organizations->isEmpty();
    }

    /** Première organisation (pour l’assistant d’initialisation). */
    public function getPrimaryOrganization(): ?Organization
    {
        $first = $this->organizations->first();

        return $first instanceof Organization ? $first : null;
    }
}
