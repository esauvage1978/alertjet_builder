<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\Form\FormInterface;

final class FormErrorPayload
{
    /**
     * @return array{fieldErrors: array<string, list<string>>, formErrors: list<string>}
     */
    public static function fromForm(FormInterface $form): array
    {
        $formErrors = [];
        foreach ($form->getErrors() as $error) {
            $formErrors[] = $error->getMessage();
        }

        $fieldErrors = [];
        foreach ($form as $field) {
            $msgs = [];
            foreach ($field->getErrors() as $error) {
                $msgs[] = $error->getMessage();
            }
            if ($msgs !== []) {
                $fieldErrors[$field->getName()] = $msgs;
            }
        }

        return ['fieldErrors' => $fieldErrors, 'formErrors' => $formErrors];
    }

    /**
     * @param array{fieldErrors: array<string, list<string>>, formErrors: list<string>} $payload
     */
    public static function summaryMessage(array $payload, string $fallback): string
    {
        $lines = [...$payload['formErrors']];
        foreach ($payload['fieldErrors'] as $msgs) {
            foreach ($msgs as $m) {
                $lines[] = $m;
            }
        }

        $lines = array_values(array_unique($lines));
        if ($lines === []) {
            return $fallback;
        }

        if (\count($lines) === 1) {
            return $lines[0];
        }

        return implode(' · ', \array_slice($lines, 0, 3)).(\count($lines) > 3 ? '…' : '');
    }
}
