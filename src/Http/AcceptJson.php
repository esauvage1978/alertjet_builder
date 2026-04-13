<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

final class AcceptJson
{
    public static function wants(Request $request): bool
    {
        return str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    /**
     * Requêtes fetch/XHR du parcours React : le client peut envoyer {@see Request::isXmlHttpRequest}
     * même si l’en-tête Accept est altéré par un proxy ou le navigateur.
     */
    public static function prefersFormJsonErrors(Request $request): bool
    {
        if (self::wants($request)) {
            return true;
        }

        return $request->isXmlHttpRequest();
    }
}
