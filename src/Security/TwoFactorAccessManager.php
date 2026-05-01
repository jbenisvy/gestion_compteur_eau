<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

final class TwoFactorAccessManager
{
    public const SESSION_VERIFIED_USER_ID = '_two_factor_verified_user_id';
    public const SESSION_SETUP_SECRET = '_two_factor_setup_secret';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function requiresChallenge(User $user): bool
    {
        if (!$user->isTwoFactorEnabled() || $user->getTwoFactorSecret() === null) {
            return false;
        }

        $session = $this->requestStack->getSession();
        if ($session === null) {
            return true;
        }

        return $session->get(self::SESSION_VERIFIED_USER_ID) !== $user->getId();
    }

    public function markVerified(User $user): void
    {
        $this->requestStack->getSession()?->set(self::SESSION_VERIFIED_USER_ID, $user->getId());
    }

    public function clearVerified(): void
    {
        $this->requestStack->getSession()?->remove(self::SESSION_VERIFIED_USER_ID);
    }

    public function storePendingSetupSecret(string $secret): void
    {
        $this->requestStack->getSession()?->set(self::SESSION_SETUP_SECRET, $secret);
    }

    public function getPendingSetupSecret(): ?string
    {
        $value = $this->requestStack->getSession()?->get(self::SESSION_SETUP_SECRET);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function clearPendingSetupSecret(): void
    {
        $this->requestStack->getSession()?->remove(self::SESSION_SETUP_SECRET);
    }
}
