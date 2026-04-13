<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ApplicationErrorLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Persiste les erreurs en base pour consultation admin, sans jamais relancer d’exception.
 */
final class ApplicationErrorLogger
{
    private const TRACE_MAX_BYTES = 524288;
    private const MESSAGE_MAX_BYTES = 65535;
    private const PREVIOUS_MAX_DEPTH = 12;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context Sérialisable en JSON (pas d’objets non sérialisables).
     */
    public function logThrowable(
        \Throwable $throwable,
        ?Request $request = null,
        ?User $user = null,
        array $context = [],
        string $source = 'caught',
    ): void {
        try {
            $row = $this->buildRow($throwable, $request, $user, $context, $source);
            $this->entityManager->persist($row);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->critical('ApplicationErrorLogger: échec de persistance du journal d’erreur.', [
                'original' => $throwable->getMessage(),
                'persistError' => $e->getMessage(),
                'persistClass' => $e::class,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildRow(
        \Throwable $throwable,
        ?Request $request,
        ?User $user,
        array $context,
        string $source,
    ): ApplicationErrorLog {
        $row = new ApplicationErrorLog();
        $row->setExceptionClass($this->truncateAscii($throwable::class, ApplicationErrorLog::MAX_EXCEPTION_CLASS_LEN));
        $row->setMessage($this->truncateString($throwable->getMessage(), self::MESSAGE_MAX_BYTES));
        $row->setCode((int) $throwable->getCode());
        $row->setFile($throwable->getFile());
        $row->setLine($throwable->getLine());
        $row->setTrace($this->truncateString($throwable->getTraceAsString(), self::TRACE_MAX_BYTES));
        $prev = $this->serializePreviousChain($throwable);
        $row->setPreviousChain($prev !== [] ? $prev : null);
        $row->setSource($source);

        if ($throwable instanceof HttpExceptionInterface) {
            $row->setHttpStatus($throwable->getStatusCode());
        }

        $sanitizedContext = $this->sanitizeContext($context);
        if ($sanitizedContext !== []) {
            $row->setContext($sanitizedContext);
        }

        if ($request !== null) {
            $row->setHttpMethod($request->getMethod());
            $uri = $request->getRequestUri();
            $row->setRequestUri(\strlen($uri) > 2048 ? substr($uri, 0, 2040).'…' : $uri);
            $route = $request->attributes->get('_route');
            $row->setRoute(\is_string($route) ? $route : null);
            $row->setIp($request->getClientIp());
            $row->setUserAgent($request->headers->get('User-Agent'));
        }

        if ($user !== null) {
            $row->setUser($user);
            $row->setActorEmail($user->getEmail());
        }

        return $row;
    }

    /**
     * @return list<array{class: string, message: string, code: int, file: string, line: int}>
     */
    private function serializePreviousChain(\Throwable $root): array
    {
        $chain = [];
        $current = $root->getPrevious();
        $depth = 0;
        while ($current !== null && $depth < self::PREVIOUS_MAX_DEPTH) {
            ++$depth;
            $chain[] = [
                'class' => $this->truncateAscii($current::class, ApplicationErrorLog::MAX_EXCEPTION_CLASS_LEN),
                'message' => $this->truncateString($current->getMessage(), 8000),
                'code' => (int) $current->getCode(),
                'file' => (string) $current->getFile(),
                'line' => (int) $current->getLine(),
            ];
            $current = $current->getPrevious();
        }

        return $chain;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            if (!\is_string($k)) {
                continue;
            }
            try {
                json_encode($v, JSON_THROW_ON_ERROR);
                $out[$k] = $v;
            } catch (\JsonException) {
                $out[$k] = '« valeur non sérialisable »';
            }
        }

        return $out;
    }

    private function truncateString(string $s, int $maxBytes): string
    {
        if (\strlen($s) <= $maxBytes) {
            return $s;
        }

        return substr($s, 0, $maxBytes - 20)."\n… [tronqué]";
    }

    private function truncateAscii(string $s, int $maxChars): string
    {
        if (\strlen($s) <= $maxChars) {
            return $s;
        }

        return substr($s, 0, $maxChars);
    }
}
