<?php

declare(strict_types=1);

namespace App\Controller;

use App\I18n\EnabledLocales;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_locale_switch', requirements: ['locale' => '[a-z]{2}'])]
    public function switchLocale(string $locale, Request $request, EnabledLocales $enabledLocales): RedirectResponse
    {
        if (!$enabledLocales->accepts($locale)) {
            return $this->redirectToRoute('app_login');
        }

        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('Referer');
        $base = $request->getSchemeAndHttpHost();
        if (\is_string($referer) && str_starts_with($referer, $base)) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_login');
    }
}
