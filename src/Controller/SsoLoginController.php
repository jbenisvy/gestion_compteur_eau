<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use App\Security\SsoTokenService;
use App\Security\SsoValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SsoLoginController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $authLogger)
    {
    }

    #[Route('/sso-login', name: 'app_sso_login', methods: ['GET'])]
    public function __invoke(
        Request $request,
        UserRepository $userRepository,
        SsoTokenService $ssoTokenService,
        Security $security,
    ): RedirectResponse {
        $token = trim((string) $request->query->get('token', ''));
        if ($token === '') {
            return $this->rejectSso($request, 'Token SSO absent.');
        }

        if (!$ssoTokenService->isPortalRefererAllowed($request->headers->get('referer'))) {
            return $this->rejectSso($request, 'Referer SSO non autorisé.');
        }

        try {
            $payload = $ssoTokenService->validateIncomingToken($token);
        } catch (SsoValidationException $exception) {
            return $this->rejectSso($request, $exception->getMessage());
        }

        $user = $userRepository->findOneBy(['email' => $payload['email']]);
        if ($user === null) {
            return $this->rejectSso($request, 'Utilisateur SSO introuvable.', $payload);
        }

        if ((string) $user->getId() !== $payload['user_id']) {
            return $this->rejectSso($request, 'Identifiant utilisateur SSO non concordant.', $payload);
        }

        $this->authLogger->info('sso_login.success', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'app' => $payload['app'],
            'ip' => $request->getClientIp(),
        ]);

        $response = $security->login($user, LoginFormAuthenticator::class, 'main');

        return $response instanceof RedirectResponse
            ? $response
            : $this->redirectToRoute('home');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function rejectSso(Request $request, string $reason, array $payload = []): RedirectResponse
    {
        $this->authLogger->warning('sso_login.failed', [
            'reason' => $reason,
            'payload' => $payload,
            'ip' => $request->getClientIp(),
            'referer' => $request->headers->get('referer'),
            'user_agent' => substr((string) $request->headers->get('User-Agent', ''), 0, 255),
        ]);

        $this->addFlash('error', 'Connexion SSO impossible ou expirée.');

        return $this->redirectToRoute('app_login');
    }
}
