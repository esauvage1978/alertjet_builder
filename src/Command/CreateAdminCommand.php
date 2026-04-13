<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée ou met à jour un compte administrateur (e-mail marqué comme vérifié).',
)]
final class CreateAdminCommand extends Command
{
    private const DEFAULT_EMAIL = 'emmanuel.sauvage@live.fr';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'email',
                InputArgument::OPTIONAL,
                'Adresse e-mail du compte admin',
                self::DEFAULT_EMAIL,
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'Mot de passe (sinon variable APP_ADMIN_BOOTSTRAP_PASSWORD ou saisie masquée)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));

        $password = $input->getOption('password');
        if (!\is_string($password) || $password === '') {
            $password = $_ENV['APP_ADMIN_BOOTSTRAP_PASSWORD'] ?? getenv('APP_ADMIN_BOOTSTRAP_PASSWORD') ?: null;
        }
        if (!\is_string($password) || $password === '') {
            $password = $io->askHidden('Mot de passe (saisie masquée)');
        }
        if ($password === null || $password === '') {
            $io->error('Mot de passe manquant : utilisez --password=… ou définissez APP_ADMIN_BOOTSTRAP_PASSWORD.');

            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $created = $user === null;

        if ($user === null) {
            $user = new User();
            $user->setEmail($email);
        }

        $user->setRoles([UserRole::Administrateur->value]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setEmailVerifiedAt(new \DateTimeImmutable());
        $user->clearEmailVerification();
        $user->clearPasswordReset();

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf(
            '%s : %s (rôle %s, e-mail vérifié).',
            $created ? 'Compte administrateur créé' : 'Compte administrateur mis à jour',
            $email,
            UserRole::Administrateur->value,
        ));

        return Command::SUCCESS;
    }
}
