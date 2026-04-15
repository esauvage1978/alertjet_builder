<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Parse un e-mail brut (raw MIME) en contenu exploitable pour ticketing.
 *
 * @phpstan-type ParsedAttachment array{filename: string, content: string, mime: string}
 * @phpstan-type ParsedEmail array{
 *   subject: string,
 *   fromEmail: string|null,
 *   fromName: string|null,
 *   to: list<string>,
 *   messageId: string|null,
 *   inReplyTo: string|null,
 *   references: list<string>,
 *   text: string,
 *   rawHeaders: string,
 *   attachments: list<ParsedAttachment>
 * }
 */
final class InboundEmailParser
{
    /**
     * @return ParsedEmail
     */
    public function parseRaw(string $rawMime): array
    {
        $rawHeaders = $this->extractRawHeaders($rawMime);
        $headers = $this->parseHeaders($rawHeaders);

        $subject = $headers['subject'] ?? '';
        [$fromEmail, $fromName] = $this->parseFrom($headers['from'] ?? null);
        $to = $this->parseAddressList($headers['to'] ?? null);

        $messageId = $this->singleHeaderId($headers['message-id'] ?? null);
        $inReplyTo = $this->singleHeaderId($headers['in-reply-to'] ?? null);
        $references = $this->parseReferences($headers['references'] ?? '');

        $text = $this->extractBodyText($rawMime);
        $text = $this->cleanupBody($text);

        // Parsing MIME complet (pièces jointes) : à brancher côté pipe ou via lib dédiée.
        $attachments = [];

        return [
            'subject' => trim($subject),
            'fromEmail' => $fromEmail,
            'fromName' => $fromName,
            'to' => $to,
            'messageId' => $messageId,
            'inReplyTo' => $inReplyTo,
            'references' => $references,
            'text' => $text,
            'rawHeaders' => $rawHeaders,
            'attachments' => $attachments,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $rawHeaders = str_replace(["\r\n", "\r"], "\n", $rawHeaders);
        $lines = explode("\n", $rawHeaders);
        $out = [];
        $current = null;
        foreach ($lines as $line) {
            if ($line === '') continue;
            // header folding
            if (($line[0] ?? '') === ' ' || ($line[0] ?? '') === "\t") {
                if ($current !== null) {
                    $out[$current] .= ' '.trim($line);
                }
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) continue;
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            $out[$name] = $value;
            $current = $name;
        }

        return $out;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseFrom(?string $raw): array
    {
        $raw = $raw !== null ? trim($raw) : '';
        if ($raw === '') return [null, null];
        // "Name <mail@host>" ou "<mail@host>" ou "mail@host"
        if (preg_match('/^(?:"?([^"]+)"?\s*)?<([^>]+)>$/', $raw, $m)) {
            $name = trim((string) ($m[1] ?? ''));
            $email = mb_strtolower(trim((string) ($m[2] ?? '')));
            return [$email !== '' ? $email : null, $name !== '' ? $name : null];
        }
        $email = mb_strtolower($raw);
        return [$email !== '' ? $email : null, null];
    }

    /**
     * @return list<string>
     */
    private function parseAddressList(?string $raw): array
    {
        $raw = $raw !== null ? trim($raw) : '';
        if ($raw === '') return [];
        $parts = array_map('trim', explode(',', $raw));
        $out = [];
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (preg_match('/<([^>]+)>/', $p, $m)) {
                $addr = mb_strtolower(trim((string) $m[1]));
                if ($addr !== '') $out[] = $addr;
            } else {
                $addr = mb_strtolower($p);
                if ($addr !== '') $out[] = $addr;
            }
        }

        return array_values(array_unique($out));
    }

    private function extractRawHeaders(string $rawMime): string
    {
        $pos = strpos($rawMime, "\r\n\r\n");
        if ($pos !== false) {
            return substr($rawMime, 0, $pos);
        }
        $pos = strpos($rawMime, "\n\n");
        if ($pos !== false) {
            return substr($rawMime, 0, $pos);
        }

        return '';
    }

    private function singleHeaderId(?string $raw): ?string
    {
        if ($raw === null) return null;
        $raw = trim($raw);
        if ($raw === '') return null;
        // Normalise "<id@host>" ou "id@host"
        if (preg_match('/<([^>]+)>/', $raw, $m)) {
            return trim($m[1]);
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private function parseReferences(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];
        preg_match_all('/<([^>]+)>/', $raw, $m);
        $ids = $m[1] ?? [];
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '') $out[] = $id;
        }
        $out = array_values(array_unique($out));

        return $out;
    }

    private function extractBodyText(string $rawMime): string
    {
        $rawMime = str_replace(["\r\n", "\r"], "\n", $rawMime);
        $pos = strpos($rawMime, "\n\n");
        if ($pos === false) return '';
        $body = substr($rawMime, $pos + 2);

        return trim($body);
    }

    /**
     * Nettoyage “pragmatique” (pas parfait) :
     * - coupe les blocs de citations standard
     * - coupe les signatures courantes
     */
    private function cleanupBody(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);
        if ($text === '') return '';

        // stop at common reply separators
        $stopPatterns = [
            '/\nOn .*wrote:\n/i',
            '/\nLe .* a écrit\s*:\n/i',
            '/\n-----Original Message-----\n/i',
            '/\nDe\s*:\s.*\nEnvoyé\s*:\s.*\nÀ\s*:\s.*\nObjet\s*:\s.*\n/is',
        ];
        foreach ($stopPatterns as $re) {
            if (preg_match($re, $text, $m, \PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1] ?? null;
                if (\is_int($pos) && $pos > 0) {
                    $text = rtrim(substr($text, 0, $pos));
                    break;
                }
            }
        }

        // stop at signature delimiter
        $sigPos = strpos($text, "\n-- \n");
        if ($sigPos !== false) {
            $text = rtrim(substr($text, 0, $sigPos));
        }

        return trim($text);
    }
}

