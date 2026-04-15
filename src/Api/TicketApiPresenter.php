<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\OrganizationContact;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Entity\TicketAttachment;
use App\Entity\TicketLog;
use App\Entity\User;
use App\Enum\TicketSource;
use App\Enum\TicketStatus;

final class TicketApiPresenter
{
    /**
     * Liste / pagination : pas de pièces jointes ni journal (léger).
     *
     * @return array<string, mixed>
     */
    public static function listSummary(Ticket $t): array
    {
        $project = $t->getProject();

        return [
            'id' => $t->getId(),
            'publicId' => (string) $t->getPublicId(),
            'projectId' => $project?->getId(),
            'project' => self::projectSummary($project),
            'title' => $t->getTitle(),
            'status' => $t->getStatus()->value,
            'priority' => $t->getPriority()->value,
            'type' => $t->getType()->value,
            'source' => $t->getSource()->value,
            'sourceLabel' => self::sourceLabelFr($t->getSource()),
            'createdAt' => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'assignee' => self::assigneeSummary($t->getAssignee()),
            'contact' => self::contactSummary($t->getOrganizationContact()),
            'incomingEmailMessageId' => $t->getIncomingEmailMessageId(),
            'silenced' => $t->isSilenced(),
            'sla' => self::slaSummary($t),
        ];
    }

    private static function sourceLabelFr(TicketSource $source): string
    {
        return match ($source) {
            TicketSource::Phone => 'Téléphone',
            TicketSource::Email => 'E-mail',
            TicketSource::Webhook => 'Webhook',
            TicketSource::ClientForm => 'Formulaire client',
            TicketSource::InternalForm => 'Formulaire interne',
        };
    }

    /** @return array<string, mixed> */
    public static function one(Ticket $t): array
    {
        $tid = $t->getId();
        $project = $t->getProject();

        return [
            'id' => $tid,
            'publicId' => (string) $t->getPublicId(),
            'projectId' => $project?->getId(),
            'project' => self::projectSummary($project),
            'title' => $t->getTitle(),
            'description' => $t->getDescription(),
            'type' => $t->getType()->value,
            'status' => $t->getStatus()->value,
            'priority' => $t->getPriority()->value,
            'source' => $t->getSource()->value,
            'sourceLabel' => self::sourceLabelFr($t->getSource()),
            'eventCount' => $t->getEventCount(),
            'contact' => self::contactSummary($t->getOrganizationContact()),
            'incomingEmailMessageId' => $t->getIncomingEmailMessageId(),
            'silenced' => $t->isSilenced(),
            'createdAt' => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'acknowledgedAt' => $t->getAcknowledgedAt()?->format(\DateTimeInterface::ATOM),
            'resolvedAt' => $t->getResolvedAt()?->format(\DateTimeInterface::ATOM),
            'closedAt' => $t->getClosedAt()?->format(\DateTimeInterface::ATOM),
            'cancelledAt' => $t->getCancelledAt()?->format(\DateTimeInterface::ATOM),
            'onHoldReason' => $t->getOnHoldReason(),
            'cancelReason' => $t->getCancelReason(),
            'assignee' => self::assigneeSummary($t->getAssignee()),
            'assignableMembers' => self::assignableMembers($t),
            'sla' => self::slaSummary($t),
            'attachments' => array_map(
                static function (TicketAttachment $a) use ($tid) {
                    $aid = $a->getId();

                    return [
                        'id' => $aid,
                        'originalFilename' => $a->getOriginalFilename(),
                        'mimeType' => $a->getMimeType(),
                        'sizeBytes' => $a->getSizeBytes(),
                        'createdAt' => $a->getCreatedAt()->format(\DateTimeInterface::ATOM),
                        'downloadPath' => $tid !== null && $aid !== null
                            ? '/api/tickets/'.$tid.'/attachments/'.$aid.'/file'
                            : null,
                    ];
                },
                $t->getAttachments()->toArray(),
            ),
            'logs' => array_map(static fn (TicketLog $l) => self::log($l), $t->getLogs()->toArray()),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function slaSummary(Ticket $t): ?array
    {
        $project = $t->getProject();
        if ($project === null) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $type = $t->getType()->value;
        $status = $t->getStatus();

        $ackMinutes = self::slaAckMinutesForType($project, $type);
        $resolveMinutes = self::slaResolveMinutesForType($project, $type);

        $createdAt = $t->getCreatedAt();
        $ackDueAt = $ackMinutes !== null ? $createdAt->modify(sprintf('+%d minutes', $ackMinutes)) : null;
        $resolveDueAt = $resolveMinutes !== null ? $createdAt->modify(sprintf('+%d minutes', $resolveMinutes)) : null;

        $ackBreached = false;
        if ($ackDueAt !== null && $t->getAcknowledgedAt() === null) {
            // tant que non pris en compte, "new/open" = en cours de SLA prise en charge.
            if (\in_array($status, [TicketStatus::New, TicketStatus::Open], true)) {
                $ackBreached = $now > $ackDueAt;
            }
        }

        $resolveBreached = false;
        if ($resolveDueAt !== null && $t->getResolvedAt() === null) {
            // tant que non résolu, tous les statuts "actifs" comptent.
            if (!\in_array($status, [TicketStatus::Closed, TicketStatus::Cancelled], true)) {
                $resolveBreached = $now > $resolveDueAt;
            }
        }

        $current = null;
        if (\in_array($status, [TicketStatus::New, TicketStatus::Open], true) && $ackDueAt !== null) {
            $current = [
                'kind' => 'ack',
                'dueAt' => $ackDueAt->format(\DateTimeInterface::ATOM),
                'breached' => $ackBreached,
            ];
        } elseif (!\in_array($status, [TicketStatus::Resolved, TicketStatus::Closed, TicketStatus::Cancelled], true) && $resolveDueAt !== null) {
            $current = [
                'kind' => 'resolve',
                'dueAt' => $resolveDueAt->format(\DateTimeInterface::ATOM),
                'breached' => $resolveBreached,
            ];
        }

        return [
            'type' => $type,
            'ackTargetMinutes' => $ackMinutes,
            'resolveTargetMinutes' => $resolveMinutes,
            'ackDueAt' => $ackDueAt?->format(\DateTimeInterface::ATOM),
            'resolveDueAt' => $resolveDueAt?->format(\DateTimeInterface::ATOM),
            'ackBreached' => $ackBreached,
            'resolveBreached' => $resolveBreached,
            'current' => $current,
        ];
    }

    private static function slaAckMinutesForType(Project $project, string $type): ?int
    {
        return match ($type) {
            'incident' => $project->getSlaIncidentAckTargetMinutes() ?? $project->getSlaAckTargetMinutes(),
            'problem' => $project->getSlaProblemAckTargetMinutes() ?? $project->getSlaAckTargetMinutes(),
            'request' => $project->getSlaRequestAckTargetMinutes() ?? $project->getSlaAckTargetMinutes(),
            default => $project->getSlaAckTargetMinutes(),
        };
    }

    private static function slaResolveMinutesForType(Project $project, string $type): ?int
    {
        return match ($type) {
            'incident' => $project->getSlaIncidentResolveTargetMinutes() ?? $project->getSlaResolveTargetMinutes(),
            'problem' => $project->getSlaProblemResolveTargetMinutes() ?? $project->getSlaResolveTargetMinutes(),
            'request' => $project->getSlaRequestResolveTargetMinutes() ?? $project->getSlaResolveTargetMinutes(),
            default => $project->getSlaResolveTargetMinutes(),
        };
    }

    /** @return array{id: int, email: string, displayName: string|null, validatedAt: string|null}|null */
    private static function contactSummary(?OrganizationContact $contact): ?array
    {
        if ($contact === null || $contact->getId() === null) {
            return null;
        }

        return [
            'id' => $contact->getId(),
            'email' => $contact->getEmail(),
            'displayName' => $contact->getDisplayName(),
            'validatedAt' => $contact->getValidatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array{id: int, initials: string, avatarColor: string, avatarForegroundColor: string, label: string}|null */
    private static function assigneeSummary(?User $user): ?array
    {
        if ($user === null || $user->getId() === null) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'initials' => $user->getAvatarInitials(),
            'avatarColor' => $user->getAvatarColorOrDefault(),
            'avatarForegroundColor' => $user->getAvatarForegroundColorOrDefault(),
            'label' => $user->getDisplayNameForGreeting(),
        ];
    }

    /**
     * Membres du projet éligibles à l’affectation (gestionnaires de tickets),
     * plus l’assigné actuel s’il n’est plus dans la liste.
     *
     * @return list<array{id: int, initials: string, avatarColor: string, avatarForegroundColor: string, label: string}>
     */
    private static function assignableMembers(Ticket $ticket): array
    {
        $project = $ticket->getProject();
        if ($project === null) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($project->getTicketHandlers() as $handler) {
            $id = $handler->getId();
            if ($id === null) {
                continue;
            }
            $seen[$id] = true;
            $summary = self::assigneeSummary($handler);
            if ($summary !== null) {
                $out[] = $summary;
            }
        }

        $current = $ticket->getAssignee();
        $cid = $current?->getId();
        if ($cid !== null && !isset($seen[$cid])) {
            $summary = self::assigneeSummary($current);
            if ($summary !== null) {
                $out[] = $summary;
            }
        }

        return $out;
    }

    /** @return array{name: string, publicToken: string, accentColor: string, accentTextColor: string, accentBorderColor: string}|null */
    private static function projectSummary(?Project $project): ?array
    {
        if ($project === null) {
            return null;
        }

        return [
            'name' => $project->getName(),
            'publicToken' => $project->getPublicToken(),
            'accentColor' => $project->getAccentColor(),
            'accentTextColor' => $project->getAccentTextColor(),
            'accentBorderColor' => $project->getAccentBorderColor(),
        ];
    }

    /** @return array<string, mixed> */
    public static function log(TicketLog $l): array
    {
        return [
            'id' => $l->getId(),
            'type' => $l->getType(),
            'message' => $l->getMessage(),
            'context' => $l->getContext(),
            'createdAt' => $l->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
