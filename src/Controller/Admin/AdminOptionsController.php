<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/options')]
#[IsGranted('ROLE_ADMIN')]
final class AdminOptionsController extends AbstractController
{
    #[Route('', name: 'admin_options_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $qs = $request->getQueryString();

        return $this->redirect('/app/admin/options'.($qs !== null && $qs !== '' ? '?'.$qs : ''));
    }
}

