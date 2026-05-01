<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\TotpAuthenticatorService;
use App\Security\TwoFactorAccessManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TwoFactorController extends AbstractController
{
    #[Route('/connexion/2fa', name: 'app_two_factor_challenge', methods: ['GET'])]
    public function challenge(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/two_factor_challenge.html.twig', [
            'userEmail' => $user->getEmail(),
        ]);
    }

    #[Route('/connexion/2fa', name: 'app_two_factor_verify', methods: ['POST'])]
    public function verify(
        Request $request,
        TotpAuthenticatorService $totpAuthenticatorService,
        TwoFactorAccessManager $twoFactorAccessManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('two_factor_verify', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
            return $this->redirectToRoute('app_two_factor_challenge');
        }

        $secret = $user->getTwoFactorSecret();
        if (!$user->isTwoFactorEnabled() || $secret === null) {
            $twoFactorAccessManager->markVerified($user);
            return $this->redirectToRoute('home');
        }

        $code = (string) $request->request->get('code', '');
        if (!$totpAuthenticatorService->verifyCode($secret, $code)) {
            $this->addFlash('error', 'Code invalide. Vérifiez l’heure du téléphone et réessayez.');
            return $this->redirectToRoute('app_two_factor_challenge');
        }

        $twoFactorAccessManager->markVerified($user);
        $this->addFlash('success', 'Authentification renforcée validée.');

        return $this->redirectToRoute('home');
    }
}
