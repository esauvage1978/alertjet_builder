<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TicketLog;
use App\Enum\TicketStatus;
use App\Repository\ProjectRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:tickets:auto-close',
    description: 'Clôture automatiquement les tickets résolus après un délai (par projet).',
)]
final class AutoCloseResolvedTicketsCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $totalClosed = 0;

        foreach ($this->projectRepository->findAll() as $project) {
            $hours = $project->getAutoCloseResolvedAfterHours();
            if ($hours <= 0) {
                continue;
            }
            $cutoff = $now->modify(sprintf('-%d hours', $hours));

            $tickets = $this->ticketRepository->createQueryBuilder('t')
                ->andWhere('t.project = :p')->setParameter('p', $project)
                ->andWhere('t.status = :s')->setParameter('s', TicketStatus::Resolved->value)
                ->andWhere('t.resolvedAt IS NOT NULL')
                ->andWhere('t.resolvedAt <= :cutoff')->setParameter('cutoff', $cutoff)
                ->setMaxResults(500)
                ->getQuery()->getResult();

            foreach ($tickets as $ticket) {
                $before = $ticket->getStatus()->value;
                $ticket->setStatus(TicketStatus::Closed);
                $ticket->incrementEventCount();
                $ticket->addLog(
                    (new TicketLog())
                        ->setType('automation')
                        ->setMessage(sprintf('Clôture automatique (%dh après résolution)', $hours))
                        ->setContext([
                            'automation' => true,
                            'rule' => 'auto_close_resolved_after_hours',
                            'beforeStatus' => $before,
                            'afterStatus' => $ticket->getStatus()->value,
                        ]),
                );
                ++$totalClosed;
            }

            if (\count($tickets) > 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $output->writeln(sprintf('OK: %d tickets clôturés.', $totalClosed));

        return Command::SUCCESS;
    }
}

