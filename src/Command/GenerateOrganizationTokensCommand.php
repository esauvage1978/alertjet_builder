<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:organizations:generate-tokens',
    description: 'Génère un publicToken unique pour chaque organisation qui n\'en a pas.',
)]
final class GenerateOrganizationTokensCommand extends Command
{
    public function __construct(
        private readonly OrganizationRepository $orgRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orgs = $this->orgRepository->findAll();
        $count = 0;

        foreach ($orgs as $org) {
            if ($org->getPublicToken() === '') {
                $org->ensurePublicToken();
                $count++;
                $io->text(sprintf('  #%d %-30s → %s', $org->getId(), $org->getName(), $org->getPublicToken()));
            }
        }

        if ($count === 0) {
            $io->success('Toutes les organisations ont déjà un token.');

            return Command::SUCCESS;
        }

        $this->em->flush();
        $io->success(sprintf('%d organisation(s) mise(s) à jour.', $count));

        return Command::SUCCESS;
    }
}
