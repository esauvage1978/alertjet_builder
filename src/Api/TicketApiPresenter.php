<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Ticket;
use App\Entity\TicketAttachment;
use App\Entity\TicketLog;

final class TicketApiPresenter
{
    /** @return array<string, mixed> */
    public static function one(Ticket $t): array
    {
        $tid = $t->getId();

        return [
            'id' => $tid,
            'publicId' => (string) $t->getPublicId(),
            'projectId' => $t->getProject()?->getId(),
            'title' => $t->getTitle(),
            'description' => $t->getDescription(),
            'status' => $t->getStatus()->value,
            'priority' => $t->getPriority()->value,
            'source' => $t->getSource(),
            'eventCount' => $t->getEventCount(),
            'silenced' => $t->isSilenced(),
            'createdAt' => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'resolvedAt' => $t->getResolvedAt()?->format(\DateTimeInterface::ATOM),
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
