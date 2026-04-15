<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Classe les messages d’erreur renvoyés par l’extension PHP IMAP (c-client), souvent en anglais.
 */
final class ImapConnectionErrorClassifier
{
    /**
     * @return 'credentials'|'tls'|'host_port'|'unknown'
     */
    public static function classifyConnectionFailure(string $error): string
    {
        $e = mb_strtolower($error);

        if ($e === '') {
            return 'unknown';
        }

        // TLS / certificat (avant « connection » pour éviter les faux positifs)
        if (self::matchesAny($e, [
            'certificate', 'cert verify', 'ssl', 'tls', 'openssl', 'peer', 'handshake',
            'x509', 'self signed', 'unable to verify', 'wrong version number',
        ])) {
            return 'tls';
        }

        // Identifiants (messages fr. / angl.)
        if (self::matchesAny($e, [
            'authentication failed', 'auth', 'login failed', 'invalid credentials',
            'credential', 'password', 'bad user', 'unauthorized', 'denied',
            'authenticationfailure', '[auth]', 'login aborted',
            'authentification', 'mot de passe', 'identifiant', 'refusé par le serveur',
        ])) {
            return 'credentials';
        }

        // Réseau / hôte / port
        if (self::matchesAny($e, [
            'connection refused', 'connection timed out', 'timed out', 'timeout',
            'name or service not known', 'nodename nor servname', 'getaddrinfo',
            'no route to host', 'network is unreachable', 'could not connect',
            'can\'t connect', 'cannot connect', 'failed to connect', 'errno=',
            'connection reset', 'host not found', 'temporary failure in name resolution',
            'connexion refusée', 'délai d', 'delai d', 'hôte inconnu', 'hote inconnu',
            'impossible de se connecter', 'aucune route vers',
        ])) {
            return 'host_port';
        }

        return 'unknown';
    }

    private static function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }
}
