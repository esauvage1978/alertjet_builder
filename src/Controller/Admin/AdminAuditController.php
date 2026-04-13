<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit')]
#[IsGranted('ROLE_ADMIN')]
final class AdminAuditController extends AbstractController
{
    #[Route('/actions', name: 'admin_audit_actions', methods: ['GET'])]
    public function actions(Request $request): Response
    {
        $qs = $request->getQueryString();

        return $this->redirect('/app/admin/audit/actions'.($qs !== null && $qs !== '' ? '?'.$qs : ''));
    }

    #[Route('/erreurs', name: 'admin_audit_errors', methods: ['GET'])]
    public function errors(Request $request): Response
    {
        $qs = $request->getQueryString();

        return $this->redirect('/app/admin/audit/erreurs'.($qs !== null && $qs !== '' ? '?'.$qs : ''));
    }

    #[Route('/erreurs/{id}', name: 'admin_audit_error_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function errorShow(int $id): Response
    {
        return $this->redirect('/app/admin/audit/erreurs/'.$id);
    }
}
