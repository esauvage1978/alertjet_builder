<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;

/**
 * Snapshots et diff pour journaliser les changements sur un projet (qui / quand via UserActionLog, quoi dans details).
 */
final class ProjectAuditHelper
{
    /** @return array<string, mixed> */
    public function snapshot(Project $project): array
    {
        $handlerIds = $project->getTicketHandlers()->map(static fn (User $u) => $u->getId())->getValues();
        sort($handlerIds);

        return [
            'name' => $project->getName(),
            'imapEnabled' => $project->isImapEnabled(),
            'imapHost' => $project->getImapHost(),
            'imapPort' => $project->getImapPort(),
            'imapTls' => $project->isImapTls(),
            'imapUsername' => $project->getImapUsername(),
            'imapMailbox' => $project->getImapMailbox(),
            'imapPasswordStored' => $project->hasStoredImapPassword(),
            'slaAckTargetMinutes' => $project->getSlaAckTargetMinutes(),
            'slaResolveTargetMinutes' => $project->getSlaResolveTargetMinutes(),
            'ticketHandlerIds' => $handlerIds,
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    public function diff(array $before, array $after, bool $imapPasswordWasSet): array
    {
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $out = [];
        foreach ($keys as $key) {
            $fromVal = $before[$key] ?? null;
            $toVal = $after[$key] ?? null;
            if ($fromVal !== $toVal) {
                $out[] = [
                    'field' => $key,
                    'before' => $fromVal,
                    'after' => $toVal,
                ];
            }
        }

        if ($imapPasswordWasSet) {
            $out[] = [
                'field' => 'imapPassword',
                'before' => null,
                'after' => '[mis_à_jour]',
            ];
        }

        return $this->normalizeDiffRows($out);
    }

    /**
     * @param list<array{field: string, before: mixed, after: mixed}> $rows
     *
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    private function normalizeDiffRows(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => strcmp($a['field'], $b['field']));

        return $rows;
    }

    /** @return array<string, mixed> */
    public function contextualize(Project $project, ?Organization $organization = null): array
    {
        $out = [
            'projectId' => $project->getId(),
            'projectPublicToken' => $project->getPublicToken(),
            'projectName' => $project->getName(),
        ];
        if ($organization !== null) {
            $out['organizationId'] = $organization->getId();
            $out['organizationPublicToken'] = $organization->getPublicToken();
            $out['organizationName'] = $organization->getName();
        }

        return $out;
    }

    /**
     * Résumé lisible des champs modifiés (pour recherche rapide dans l’historique).
     *
     * @param list<array{field: string, before: mixed, after: mixed}> $changes
     */
    public function summarizeChangeFields(array $changes): string
    {
        $fields = array_map(static fn (array $c): string => (string) $c['field'], $changes);

        return implode(', ', $fields);
    }
}
