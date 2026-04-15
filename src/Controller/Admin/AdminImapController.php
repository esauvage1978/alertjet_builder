<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/imap')]
#[IsGranted('ROLE_ADMIN')]
final class AdminImapController extends AbstractController
{
    #[Route('/fetch-inbox', name: 'admin_imap_fetch_inbox_runs', methods: ['GET'])]
    public function runs(Request $request): Response
    {
        $qs = $request->getQueryString();

        return $this->redirect('/app/admin/imap/fetch-inbox'.($qs !== null && $qs !== '' ? '?'.$qs : ''));
    }

    #[Route('/fetch-inbox/{id}', name: 'admin_imap_fetch_inbox_run_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function runShow(int $id): Response
    {
        return $this->redirect('/app/admin/imap/fetch-inbox/'.$id);
    }
}

