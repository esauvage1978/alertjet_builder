<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\AcceptJson;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseAbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Contrôleur de base : raccourci {@see trans()} (retiré du AbstractController du FrameworkBundle en Symfony 7).
 */
abstract class AbstractController extends BaseAbstractController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'translator' => '?'.TranslatorInterface::class,
        ]);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    protected function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $translator = $this->container->get('translator');
        \assert($translator instanceof TranslatorInterface);

        return $translator->trans($id, $parameters, $domain, $locale);
    }

    /** Réponse API pour les écrans encore non migrés côté React. */
    protected function spaJsonStub(Request $request, string $routeName): ?JsonResponse
    {
        if ($request->isMethod('GET') && AcceptJson::wants($request)) {
            return $this->json(['migrated' => false, 'route' => $routeName]);
        }

        return null;
    }
}
