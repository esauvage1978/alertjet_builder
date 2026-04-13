<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Ticket;
use App\Enum\TicketPriority;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class TicketNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(ALERTJET_NOTIFY_EMAIL)%')]
        private readonly string $notifyTo,
        #[Autowire('%env(ALERTJET_MAIL_FROM)%')]
        private readonly string $mailFrom,
        private readonly ApplicationErrorLogger $applicationErrorLogger,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function notifyNewTicket(Ticket $ticket): void
    {
        if (trim($this->notifyTo) === '') {
            return;
        }

        $email = (new Email())
            ->from($this->mailFrom)
            ->to(trim($this->notifyTo))
            ->subject(sprintf('[AlertJet] Nouveau ticket — %s', $ticket->getTitle()))
            ->text($this->formatTicketBody($ticket, 'Nouveau ticket créé.'));

        if ($ticket->getPriority() === TicketPriority::Critical) {
            $email->subject('[AlertJet][CRITIQUE] '.$ticket->getTitle());
        }

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->applicationErrorLogger->logThrowable($e, $this->requestStack->getCurrentRequest(), null, [
                'layer' => 'ticket_notify_new',
                'ticketId' => $ticket->getId(),
            ], 'caught');
        }
    }

    public function notifyTicketEventMerged(Ticket $ticket): void
    {
        if (trim($this->notifyTo) === '' || $ticket->getPriority() !== TicketPriority::Critical) {
            return;
        }

        $email = (new Email())
            ->from($this->mailFrom)
            ->to(trim($this->notifyTo))
            ->subject(sprintf('[AlertJet][CRITIQUE] Événement fusionné — %s', $ticket->getTitle()))
            ->text($this->formatTicketBody($ticket, sprintf('Nouvel événement fusionné (x%d).', $ticket->getEventCount())));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->applicationErrorLogger->logThrowable($e, $this->requestStack->getCurrentRequest(), null, [
                'layer' => 'ticket_notify_merge',
                'ticketId' => $ticket->getId(),
            ], 'caught');
        }
    }

    private function formatTicketBody(Ticket $ticket, string $intro): string
    {
        return sprintf(
            "%s\n\nProjet : %s\nTitre : %s\nPriorité : %s\nStatut : %s\nÉvénements : %d\nID public : %s\n\nDescription :\n%s\n",
            $intro,
            $ticket->getProject()?->getName() ?? '',
            $ticket->getTitle(),
            $ticket->getPriority()->value,
            $ticket->getStatus()->value,
            $ticket->getEventCount(),
            (string) $ticket->getPublicId(),
            $ticket->getDescription() ?? '',
        );
    }
}
