<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * Langues proposées dans l’UI. Ajouter un code ici, les fichiers messages.{code}.yaml,
 * et la clé de libellé `locale.{code}` dans les traductions.
 */
final class EnabledLocales
{
    /**
     * @param list<string> $codes codes ISO courts (ex. fr, en, de)
     */
    public function __construct(
        private readonly array $codes,
        private readonly string $defaultLocale,
    ) {
        if ($this->defaultLocale !== '' && !\in_array($this->defaultLocale, $this->codes, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'kernel.default_locale (%s) doit figurer dans app.enabled_locales (%s).',
                $this->defaultLocale,
                implode(', ', $this->codes),
            ));
        }
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->codes;
    }

    public function default(): string
    {
        return $this->defaultLocale !== '' ? $this->defaultLocale : ($this->codes[0] ?? 'fr');
    }

    public function accepts(?string $locale): bool
    {
        return \is_string($locale) && \in_array($locale, $this->codes, true);
    }
}
