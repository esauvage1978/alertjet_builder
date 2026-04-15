<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ImapFetchProjectStats;
use App\Entity\ImapFetchRun;
use App\Entity\ImapFetchRunProject;
use App\Entity\Project;
use App\Repository\ImapFetchRunRepository;
use App\Repository\OptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ImapFetchReportWriter
{
    public const OPTION_CATEGORY = 'imap';
    public const OPTION_RETENTION_DAYS = 'fetch_inbox_report_retention_days';
    public const DEFAULT_RETENTION_DAYS = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OptionRepository $optionRepository,
        private readonly ImapFetchRunRepository $imapFetchRunRepository,
    ) {
    }

    public function startRun(?int $projectFilterId): ImapFetchRun
    {
        $retentionDays = $this->retentionDays();
        $this->purgeOldRuns($retentionDays);

        $run = (new ImapFetchRun())
            ->setProjectFilterId($projectFilterId)
            ->setRetentionDays($retentionDays);

        $this->em->persist($run);
        $this->em->flush();

        return $run;
    }

    public function addProjectResult(ImapFetchRun $run, Project $project, ImapFetchProjectStats $stats): void
    {
        $org = $project->getOrganization();
        $line = (new ImapFetchRunProject())
            ->setRun($run)
            ->setOrganization($org)
            ->setOrganizationName($org?->getName() ?? '—')
            ->setProject($project)
            ->setProjectName($project->getName())
            ->setImapHost(trim((string) $project->getImapHost()))
            ->setImapPort((int) $project->getImapPort())
            ->setImapTls((bool) $project->isImapTls())
            ->setImapMailbox(trim((string) $project->getImapMailbox()) !== '' ? trim((string) $project->getImapMailbox()) : 'INBOX')
            ->setUnseenCount($stats->unseenCount)
            ->setTicketsCreated($stats->ticketsCreated)
            ->setFailureCount($stats->failureCount)
            ->setConnectionError($stats->connectionError)
            ->setMailboxError($stats->mailboxError)
            ->setFailuresJson($stats->failures !== [] ? $stats->failures : null);

        $run->addProject($line);
        $this->em->persist($line);
        $this->em->flush();
    }

    public function finishRun(ImapFetchRun $run): void
    {
        $finishedAt = new \DateTimeImmutable();
        $run->setFinishedAt($finishedAt);
        $run->setDurationMs(max(0, (int) (($finishedAt->getTimestamp() - $run->getStartedAt()->getTimestamp()) * 1000)));

        $orgs = [];
        $totalProjects = 0;
        $totalUnseen = 0;
        $totalTickets = 0;
        $totalFailures = 0;

        foreach ($run->getProjects() as $p) {
            $totalProjects++;
            $totalUnseen += $p->getUnseenCount();
            $totalTickets += $p->getTicketsCreated();
            $totalFailures += $p->getFailureCount();
            $orgs[$p->getOrganizationName()] = true;
        }

        $run
            ->setTotalOrganizations(\count($orgs))
            ->setTotalProjects($totalProjects)
            ->setTotalUnseen($totalUnseen)
            ->setTotalTickets($totalTickets)
            ->setTotalFailures($totalFailures);

        $this->em->flush();
    }

    private function retentionDays(): int
    {
        $d = $this->optionRepository->getIntValue(
            self::OPTION_CATEGORY,
            self::OPTION_RETENTION_DAYS,
            null,
            self::DEFAULT_RETENTION_DAYS,
        );

        return max(1, min(3650, $d));
    }

    private function purgeOldRuns(int $retentionDays): void
    {
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $retentionDays));

        $conn = $this->em->getConnection();
        $platform = $conn->getDatabasePlatform();
        $dt = $cutoff->format($platform->getDateTimeFormatString());

        // On supprime d'abord les lignes enfants (même si CASCADE existe, SQLite peut varier selon FK).
        $conn->executeStatement(
            'DELETE FROM imap_fetch_run_projects WHERE run_id IN (SELECT id FROM imap_fetch_runs WHERE started_at < ?)',
            [$dt],
        );
        $conn->executeStatement('DELETE FROM imap_fetch_runs WHERE started_at < ?', [$dt]);
    }
}

