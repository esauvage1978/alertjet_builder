<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liste d’origines (schéma + hôte [+ port]) pour autoriser les POST depuis un navigateur (en-tête Origin).
 * Vide = aucune vérification sur Origin (intégrations serveur-à-serveur inchangées).
 */
final class WebhookCorsHelper
{
    /**
     * @return list<string> origines normalisées (ex. https://app.exemple.fr)
     */
    public function normalizedAllowedOrigins(Project $project): array
    {
        $raw = $project->getWebhookCorsAllowedOrigins();
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $out = [];
        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $n = self::lineToNormalizedOrigin($line);
            if ($n !== null) {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Si la liste est non vide et qu’un Origin est envoyé, il doit être autorisé.
     * Pas d’en-tête Origin (curl, cron, etc.) : toujours autorisé.
     */
    public function isOriginRequestAllowed(Request $request, Project $project): bool
    {
        $allowed = $this->normalizedAllowedOrigins($project);
        if ($allowed === []) {
            return true;
        }
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '') {
            return true;
        }
        $normalized = self::lineToNormalizedOrigin($origin);

        return $normalized !== null && \in_array($normalized, $allowed, true);
    }

    public function attachResponseCorsHeaders(Response $response, Request $request, Project $project): void
    {
        $allowed = $this->normalizedAllowedOrigins($project);
        if ($allowed === []) {
            return;
        }
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '') {
            return;
        }
        $normalized = self::lineToNormalizedOrigin($origin);
        if ($normalized === null || !\in_array($normalized, $allowed, true)) {
            return;
        }
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Vary', 'Origin');
    }

    public function attachPreflightHeaders(Response $response, Request $request, Project $project): void
    {
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Max-Age', '86400');
        $this->attachResponseCorsHeaders($response, $request, $project);
    }

    /**
     * Valide une ligne saisie ; retourne null si invalide.
     */
    public static function lineToNormalizedOrigin(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $p = parse_url($value);
        if ($p === false || !isset($p['scheme'], $p['host']) || $p['host'] === '') {
            return null;
        }
        $scheme = strtolower((string) $p['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        $host = strtolower((string) $p['host']);
        $port = isset($p['port']) ? (int) $p['port'] : ($scheme === 'https' ? 443 : 80);
        $defaultPort = $scheme === 'https' ? 443 : 80;
        if ($port === $defaultPort) {
            return $scheme.'://'.$host;
        }

        return $scheme.'://'.$host.':'.$port;
    }

    /**
     * @return list<string> lignes non vides invalides
     */
    public static function invalidOriginLines(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }
        $bad = [];
        foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            if (self::lineToNormalizedOrigin($trim) === null) {
                $bad[] = $trim;
            }
        }

        return $bad;
    }
}
