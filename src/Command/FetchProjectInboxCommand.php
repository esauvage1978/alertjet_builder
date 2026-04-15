<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\ProjectImapInboxService;
use App\Service\ImapFetchReportWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:project:fetch-inbox', description: 'Importe les e-mails non lus (IMAP) des projets et crée des tickets.')]
final class FetchProjectInboxCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectImapInboxService $projectImapInboxService,
        private readonly ImapFetchReportWriter $imapFetchReportWriter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Identifiant numérique d’un projet uniquement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\function_exists('imap_open')) {
            $io->error('L’extension PHP « imap » est requise pour cette commande.');

            return Command::FAILURE;
        }

        $projectId = $input->getOption('project');
        if ($projectId !== null && $projectId !== '') {
            $project = $this->projectRepository->find((int) $projectId);
            if (!$project instanceof Project) {
                $io->error('Projet introuvable.');

                return Command::FAILURE;
            }

            $projects = [$project];
        } else {
            $projects = $this->projectRepository->findBy(['imapEnabled' => true]);
        }

        $total = 0;
        $run = $this->imapFetchReportWriter->startRun($projectId !== null && $projectId !== '' ? (int) $projectId : null);
        foreach ($projects as $project) {
            if (!$project->isImapEnabled()) {
                continue;
            }
            $stats = $this->projectImapInboxService->fetchAndIngestUnread($project);
            $this->imapFetchReportWriter->addProjectResult($run, $project, $stats);
            $total += $stats->ticketsCreated;
            if ($stats->unseenCount > 0 || $stats->failureCount > 0) {
                $io->writeln(sprintf(
                    'Projet #%d (%s) : %d mail(s) non lu(s), %d ticket(s) créé(s), %d échec(s).',
                    $project->getId(),
                    $project->getName(),
                    $stats->unseenCount,
                    $stats->ticketsCreated,
                    $stats->failureCount,
                ));
            }
        }
        $this->imapFetchReportWriter->finishRun($run);

        $io->success(sprintf('Terminé. %d ticket(s) / fusion(s) au total.', $total));

        return Command::SUCCESS;
    }
}
