<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Entity\TicketAttachment;
use App\Repository\TicketAttachmentRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\TicketAttachmentStorageService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TicketAttachmentDownloadController extends AbstractController
{
    #[Route('/api/tickets/{ticketId}/attachments/{attachmentId}/file', name: 'api_ticket_attachment_download', methods: ['GET'], requirements: ['ticketId' => '\d+', 'attachmentId' => '\d+'])]
    public function download(
        int $ticketId,
        int $attachmentId,
        TicketAttachmentRepository $ticketAttachmentRepository,
        TicketAttachmentStorageService $ticketAttachmentStorageService,
    ): Response {
        $attachment = $ticketAttachmentRepository->find($attachmentId);
        if (!$attachment instanceof TicketAttachment) {
            throw new NotFoundHttpException();
        }

        $ticket = $attachment->getTicket();
        if ($ticket === null || $ticket->getId() !== $ticketId) {
            throw new NotFoundHttpException();
        }

        $project = $ticket->getProject();
        if ($project === null) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        $path = $ticketAttachmentStorageService->getAbsolutePath($attachment);
        if (!is_file($path) || !is_readable($path)) {
            throw new NotFoundHttpException();
        }

        // Inline to allow in-app preview (PDF/images) in an iframe/img.
        return $this->file($path, $attachment->getOriginalFilename(), ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
