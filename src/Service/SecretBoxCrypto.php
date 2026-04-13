<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Chiffrement réversible des secrets (mot de passe IMAP) avec AES-256-GCM.
 */
final class SecretBoxCrypto
{
    private const PREFIX = 'v1';

    public function __construct(
        private readonly string $appSecret,
    ) {
    }

    public function encrypt(#[\SensitiveParameter] string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        $key = $this->deriveKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('openssl_encrypt a échoué.');
        }

        return base64_encode(self::PREFIX.$iv.$tag.$cipher);
    }

    public function decrypt(string $encoded): ?string
    {
        if ($encoded === '') {
            return null;
        }

        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < strlen(self::PREFIX) + 12 + 16 + 1) {
            return null;
        }

        if (!str_starts_with($raw, self::PREFIX)) {
            return null;
        }

        $iv = substr($raw, strlen(self::PREFIX), 12);
        $tag = substr($raw, strlen(self::PREFIX) + 12, 16);
        $cipher = substr($raw, strlen(self::PREFIX) + 12 + 16);
        $key = $this->deriveKey();
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            return null;
        }

        return $plain;
    }

    private function deriveKey(): string
    {
        return hash('sha256', self::PREFIX.'|imap|'.$this->appSecret, true);
    }
}
