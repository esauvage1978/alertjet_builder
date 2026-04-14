<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Service\ProjectAuditHelper;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Compte de service AlertJet : gestionnaire, organisation hors offres vitrine.
 */
#[AsCommand(
    name: 'app:create-alertjet-manager',
    description: 'Synchronise le schéma Doctrine si nécessaire, puis crée ou met à jour le gestionnaire et l’org. AlertJet.',
)]
final class CreateAlertjetManagerCommand extends Command
{
    /** Compte gestionnaire par défaut (surchageable en 1er argument de la commande). */
    private const DEFAULT_EMAIL = 'gestionnaire@alertjet.builders';

    private const DEFAULT_PASSWORD = 'Fckgwrhqq101';

    private const ORGANIZATION_NAME = 'AlertJet';

    private const DEFAULT_PROJECT_NAME = 'Principal';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly OrganizationRepository $organizationRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserActionLogger $userActionLogger,
        private readonly ProjectAuditHelper $projectAuditHelper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'email',
                InputArgument::OPTIONAL,
                'E-mail du compte gestionnaire (celui utilisé sur /app/login)',
                self::DEFAULT_EMAIL,
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'Mot de passe à appliquer (sinon mot de passe par défaut de la commande)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $password = $input->getOption('password');
        if (!\is_string($password) || $password === '') {
            $password = self::DEFAULT_PASSWORD;
        }

        $this->synchronizeDatabaseSchema($io);

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $userCreated = $user === null;

        if ($user === null) {
            $user = (new User())->setEmail($email);
            $this->entityManager->persist($user);
        }

        $user->setRoles([UserRole::Gestionnaire->value]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setEmailVerifiedAt(new \DateTimeImmutable());
        $user->clearEmailVerification();
        $user->clearPasswordReset();

        if ($user->getDisplayName() === null || trim($user->getDisplayName() ?? '') === '') {
            $user->setDisplayName('Gestionnaire AlertJet');
        }

        $organization = $this->organizationRepository->findOneBy(['name' => self::ORGANIZATION_NAME]);
        $orgCreated = $organization === null;

        if ($organization === null) {
            $organization = (new Organization())->setName(self::ORGANIZATION_NAME);
            $this->entityManager->persist($organization);
        }

        $organization->setPlan(null);
        $organization->setPlanExempt(true);

        if (!$user->belongsToOrganization($organization)) {
            $user->addOrganization($organization);
        }

        $defaultProjectCreated = null;
        if ($organization->getProjects()->isEmpty()) {
            $defaultProjectCreated = (new Project())
                ->setName(self::DEFAULT_PROJECT_NAME)
                ->setAccentColor(Project::randomAccentColor())
                ->setWebhookToken(bin2hex(random_bytes(16)));
            $organization->addProject($defaultProjectCreated);
            $this->entityManager->persist($defaultProjectCreated);
        }

        $user->setEnvironmentInitializedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        if ($defaultProjectCreated !== null) {
            $this->userActionLogger->log(
                'CLI_DEFAULT_PROJECT_CREATED',
                null,
                null,
                array_merge($this->projectAuditHelper->contextualize($defaultProjectCreated, $organization), [
                    'event' => 'created',
                    'source' => 'app:create-alertjet-manager',
                    'initialSnapshot' => $this->projectAuditHelper->snapshot($defaultProjectCreated),
                ]),
                null,
            );
        }

        $io->success(\sprintf(
            '%s | %s | organisation « %s » (plan_exempt) — %s.',
            $userCreated ? 'Utilisateur créé' : 'Utilisateur mis à jour',
            $email,
            self::ORGANIZATION_NAME,
            $orgCreated ? 'organisation créée' : 'organisation mise à jour',
        ));

        return Command::SUCCESS;
    }

    /**
     * Équivalent à « doctrine:schema:update » : crée / met à jour les tables pour toutes les entités mappées.
     */
    private function synchronizeDatabaseSchema(SymfonyStyle $io): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        if ($allMetadata === []) {
            return;
        }

        $sqlQueue = $schemaTool->getUpdateSchemaSql($allMetadata);

        if ($sqlQueue === []) {
            return;
        }

        $conn = $this->entityManager->getConnection();
        foreach ($sqlQueue as $sql) {
            $conn->executeStatement($sql);
        }

        $io->note(\sprintf(
            'Schéma BDD synchronisé (%d requête%s).',
            \count($sqlQueue),
            \count($sqlQueue) > 1 ? 's' : '',
        ));
    }
}
