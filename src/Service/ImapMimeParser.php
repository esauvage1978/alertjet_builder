<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Extrait le texte principal et les pièces jointes d’un message IMAP (structure MIME).
 *
 * @phpstan-type Attachment array{filename: string, content: string, mime: string}
 */
final class ImapMimeParser
{
    private const MAX_ATTACHMENT_BYTES = 10485760;

    private const MAX_ATTACHMENTS = 25;

    /** @var list<Attachment> */
    private array $attachments = [];

    private string $plain = '';

    private int $plainPartsSeen = 0;

    /**
     * @param \IMAP\Connection|resource $conn
     *
     * @return array{plain: string, attachments: list<Attachment>}
     */
    public function extractPlainAndAttachments($conn, int $msgNum): array
    {
        $this->attachments = [];
        $this->plain = '';
        $this->plainPartsSeen = 0;

        $structure = @\imap_fetchstructure($conn, $msgNum);
        if ($structure === false || !\is_object($structure)) {
            return ['plain' => '', 'attachments' => []];
        }

        $this->walk($conn, $msgNum, $structure, '');

        return [
            'plain' => $this->plain,
            'attachments' => $this->attachments,
        ];
    }

    /**
     * @param \IMAP\Connection|resource $conn
     */
    private function walk($conn, int $msgNum, object $structure, string $partNo): void
    {
        if (isset($structure->parts) && \is_array($structure->parts)) {
            foreach ($structure->parts as $i => $part) {
                if (!\is_object($part)) {
                    continue;
                }
                $sub = $partNo === '' ? (string) ($i + 1) : $partNo.'.'.($i + 1);
                $this->walk($conn, $msgNum, $part, $sub);
            }

            return;
        }

        $leafNo = $partNo === '' ? '1' : $partNo;
        $this->processLeaf($conn, $msgNum, $structure, $leafNo);
    }

    /**
     * @param \IMAP\Connection|resource $conn
     */
    private function processLeaf($conn, int $msgNum, object $structure, string $partNo): void
    {
        $body = (string) @\imap_fetchbody($conn, $msgNum, $partNo);
        $encoding = isset($structure->encoding) ? (int) $structure->encoding : 0;
        $decoded = $this->decodeBody($body, $encoding);

        $type = isset($structure->type) ? (int) $structure->type : 0;
        $subtype = isset($structure->subtype) ? strtoupper((string) $structure->subtype) : '';
        $mime = $this->guessMime($type, $subtype);

        $filename = $this->extractFilename($structure);
        $disposition = '';
        if (isset($structure->ifdisposition) && (int) $structure->ifdisposition === 1 && isset($structure->disposition)) {
            $disposition = strtolower((string) $structure->disposition);
        }

        $isPlainText = $type === 0 && $subtype === 'PLAIN';
        $isHtml = $type === 0 && $subtype === 'HTML';

        $isBodyPlain = $isPlainText && $filename === null && $disposition !== 'attachment';
        if ($isBodyPlain) {
            if ($this->plainPartsSeen === 0) {
                $this->plain = trim($decoded);
                ++$this->plainPartsSeen;
            }

            return;
        }

        if ($isHtml && $filename === null && $disposition !== 'attachment') {
            return;
        }

        $isAttachment = $disposition === 'attachment'
            || ($filename !== null && $filename !== '')
            || ($type >= 3 && $type <= 7);

        if (! $isAttachment) {
            return;
        }

        if (\count($this->attachments) >= self::MAX_ATTACHMENTS) {
            return;
        }

        if ($filename === null || $filename === '') {
            $filename = 'piece-jointe-'.(\count($this->attachments) + 1);
        }

        $filename = $this->sanitizeFilename($filename);
        if (\strlen($decoded) > self::MAX_ATTACHMENT_BYTES) {
            return;
        }

        if ($decoded === '') {
            return;
        }

        $this->attachments[] = [
            'filename' => $filename,
            'content' => $decoded,
            'mime' => $mime,
        ];
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode(str_replace(["\r", "\n", ' '], '', $body), true) ?: '',
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function guessMime(int $type, string $subtype): string
    {
        $st = strtolower($subtype);

        return match ($type) {
            0 => 'text/'.$st,
            2 => 'message/rfc822',
            3 => 'application/'.$st,
            4 => 'audio/'.$st,
            5 => 'image/'.$st,
            6 => 'video/'.$st,
            default => 'application/octet-stream',
        };
    }

    private function extractFilename(object $structure): ?string
    {
        foreach (['dparameters', 'parameters'] as $key) {
            if (!isset($structure->{$key}) || !\is_array($structure->{$key})) {
                continue;
            }
            foreach ($structure->{$key} as $p) {
                if (!\is_object($p) || !isset($p->attribute, $p->value)) {
                    continue;
                }
                $attr = strtolower((string) $p->attribute);
                if (\in_array($attr, ['filename', 'name'], true)) {
                    $raw = (string) $p->value;
                    $decoded = @\iconv_mime_decode($raw, \ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

                    return \is_string($decoded) && $decoded !== '' ? $decoded : $raw;
                }
            }
        }

        return null;
    }

    private function sanitizeFilename(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '', $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name);
        if ($name === '') {
            return 'fichier';
        }
        if (mb_strlen($name) > 200) {
            $name = mb_substr($name, 0, 200);
        }

        return $name;
    }
}
