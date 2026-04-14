<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Ticket;
use App\Entity\TicketAttachment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre les fichiers sous var/uploads/ticket_attachments/{publicToken projet}/.
 */
final class TicketAttachmentStorageService
{
    public function __construct(
        private readonly string $ticketAttachmentsDir,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws \RuntimeException si écriture disque impossible
     */
    public function storeForTicket(Ticket $ticket, string $binaryContent, string $originalFilename, ?string $mimeType): TicketAttachment
    {
        $project = $ticket->getProject();
        if ($project === null) {
            throw new \InvalidArgumentException('Ticket sans projet.');
        }

        $token = $project->getPublicToken();
        $dir = $this->ticketAttachmentsDir.\DIRECTORY_SEPARATOR.$token;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le répertoire des pièces jointes.');
        }

        $safeBase = $this->sanitizeBasename($originalFilename);
        $stored = bin2hex(random_bytes(16)).'_'.$safeBase;
        $path = $dir.\DIRECTORY_SEPARATOR.$stored;

        $written = @file_put_contents($path, $binaryContent);
        if ($written === false) {
            throw new \RuntimeException('Échec de l’enregistrement de la pièce jointe.');
        }

        $entity = (new TicketAttachment())
            ->setOriginalFilename(mb_substr($originalFilename, 0, 255))
            ->setStoredFilename($stored)
            ->setMimeType($mimeType !== null && $mimeType !== '' ? mb_substr($mimeType, 0, 128) : null)
            ->setSizeBytes(\strlen($binaryContent));

        $ticket->addAttachment($entity);
        $this->em->persist($entity);

        return $entity;
    }

    public function getAbsolutePath(TicketAttachment $attachment): string
    {
        $ticket = $attachment->getTicket();
        $project = $ticket?->getProject();
        if ($project === null) {
            throw new \InvalidArgumentException();
        }

        return $this->ticketAttachmentsDir
            .\DIRECTORY_SEPARATOR.$project->getPublicToken()
            .\DIRECTORY_SEPARATOR.$attachment->getStoredFilename();
    }

    private function sanitizeBasename(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '', $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name);
        if ($name === '') {
            return 'fichier.bin';
        }
        if (mb_strlen($name) > 180) {
            $name = mb_substr($name, 0, 180);
        }

        return $name;
    }
}
