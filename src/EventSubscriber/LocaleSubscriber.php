<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\I18n\EnabledLocales;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EnabledLocales $enabledLocales,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => [['onKernelRequest', 20]]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->hasSession() ? $request->getSession() : null;
        $locale = $session?->get('_locale');

        if (!$this->enabledLocales->accepts(\is_string($locale) ? $locale : null)) {
            $locale = $this->enabledLocales->default();
        }

        $request->setLocale($locale);
    }
}
