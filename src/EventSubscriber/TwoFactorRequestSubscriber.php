<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\TwoFactorAccessManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TwoFactorRequestSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = [
        'app_two_factor_challenge',
        'app_two_factor_verify',
        'app_logout',
        '_wdt',
        '_profiler',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly TwoFactorAccessManager $twoFactorAccessManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!$this->twoFactorAccessManager->requiresChallenge($user)) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_two_factor_challenge')));
    }
}
