<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\TwoFactorAccessManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class TwoFactorLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TwoFactorAccessManager $twoFactorAccessManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->twoFactorAccessManager->clearVerified();

        if (!$this->twoFactorAccessManager->requiresChallenge($user)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_two_factor_challenge')));
    }
}
