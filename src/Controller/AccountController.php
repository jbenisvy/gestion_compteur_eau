<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\AuthLinkService;
use App\Security\TotpAuthenticatorService;
use App\Security\TwoFactorAccessManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/compte/email', name: 'app_account_email', methods: ['GET', 'POST'])]
    public function email(
        Request $request,
        EntityManagerInterface $em,
        AuthLinkService $authLinkService,
        TotpAuthenticatorService $totpAuthenticatorService,
        TwoFactorAccessManager $twoFactorAccessManager,
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_change_email', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
                return $this->redirectToRoute('app_account_email');
            }

            $newEmail = mb_strtolower(trim((string) $request->request->get('email', '')));
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Adresse email invalide.');
                return $this->redirectToRoute('app_account_email');
            }

            if ($newEmail === $user->getEmail()) {
                $this->addFlash('info', 'Cette adresse est déjà celle de votre compte.');
                return $this->redirectToRoute('app_account_email');
            }

            $user->setEmail($newEmail);
            $user->setIsVerified(false);

            try {
                $em->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Cette adresse email est déjà utilisée.');
                return $this->redirectToRoute('app_account_email');
            }

            $authLinkService->sendVerificationEmail($user);
            $this->addFlash('success', 'Email mis à jour. Vérifiez votre nouvelle adresse pour activer la connexion.');
            return $this->redirectToRoute('app_account_email');
        }

        $pendingTwoFactorSecret = $twoFactorAccessManager->getPendingSetupSecret();
        $canManageTwoFactor = $this->isGranted('ROLE_USER') || $this->isGranted('ROLE_SYNDIC');

        return $this->render('account/email.html.twig', [
            'userEmail' => $user->getEmail(),
            'isVerified' => $user->isVerified(),
            'canManageTwoFactor' => $canManageTwoFactor,
            'twoFactorEnabled' => $user->isTwoFactorEnabled(),
            'twoFactorSetupSecret' => $pendingTwoFactorSecret,
            'twoFactorProvisioningUri' => $pendingTwoFactorSecret !== null
                ? $totpAuthenticatorService->getProvisioningUri('Gestion Compteurs Eau', $user->getEmail(), $pendingTwoFactorSecret)
                : null,
        ]);
    }

    #[Route('/compte/2fa/demarrer', name: 'app_account_two_factor_start', methods: ['POST'])]
    public function startTwoFactorSetup(
        Request $request,
        TotpAuthenticatorService $totpAuthenticatorService,
        TwoFactorAccessManager $twoFactorAccessManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || !($this->isGranted('ROLE_USER') || $this->isGranted('ROLE_SYNDIC'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('account_two_factor_start', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
            return $this->redirectToRoute('app_account_email');
        }

        $twoFactorAccessManager->storePendingSetupSecret($totpAuthenticatorService->generateSecret());
        $this->addFlash('info', 'Scannez le QR code puis saisissez le code affiché par votre application.');

        return $this->redirectToRoute('app_account_email');
    }

    #[Route('/compte/2fa/activer', name: 'app_account_two_factor_enable', methods: ['POST'])]
    public function enableTwoFactor(
        Request $request,
        EntityManagerInterface $em,
        TotpAuthenticatorService $totpAuthenticatorService,
        TwoFactorAccessManager $twoFactorAccessManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || !($this->isGranted('ROLE_USER') || $this->isGranted('ROLE_SYNDIC'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('account_two_factor_enable', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
            return $this->redirectToRoute('app_account_email');
        }

        $secret = $twoFactorAccessManager->getPendingSetupSecret();
        if ($secret === null) {
            $this->addFlash('error', 'La configuration a expiré. Relancez l’activation.');
            return $this->redirectToRoute('app_account_email');
        }

        $code = (string) $request->request->get('code', '');
        if (!$totpAuthenticatorService->verifyCode($secret, $code)) {
            $this->addFlash('error', 'Code invalide. Vérifiez l’heure du téléphone et réessayez.');
            return $this->redirectToRoute('app_account_email');
        }

        $user
            ->setTwoFactorSecret($secret)
            ->setTwoFactorEnabled(true);
        $em->flush();

        $twoFactorAccessManager->clearPendingSetupSecret();
        $twoFactorAccessManager->markVerified($user);
        $this->addFlash('success', 'Double authentification activée.');

        return $this->redirectToRoute('app_account_email');
    }

    #[Route('/compte/2fa/annuler', name: 'app_account_two_factor_cancel', methods: ['POST'])]
    public function cancelTwoFactorSetup(Request $request, TwoFactorAccessManager $twoFactorAccessManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !($this->isGranted('ROLE_USER') || $this->isGranted('ROLE_SYNDIC'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('account_two_factor_cancel', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
            return $this->redirectToRoute('app_account_email');
        }

        $twoFactorAccessManager->clearPendingSetupSecret();
        $this->addFlash('info', 'Configuration de la double authentification annulée.');

        return $this->redirectToRoute('app_account_email');
    }

    #[Route('/compte/2fa/desactiver', name: 'app_account_two_factor_disable', methods: ['POST'])]
    public function disableTwoFactor(
        Request $request,
        EntityManagerInterface $em,
        TwoFactorAccessManager $twoFactorAccessManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || !($this->isGranted('ROLE_USER') || $this->isGranted('ROLE_SYNDIC'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('account_two_factor_disable', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
            return $this->redirectToRoute('app_account_email');
        }

        $user
            ->setTwoFactorEnabled(false)
            ->setTwoFactorSecret(null);
        $em->flush();

        $twoFactorAccessManager->clearPendingSetupSecret();
        $twoFactorAccessManager->markVerified($user);
        $this->addFlash('success', 'Double authentification désactivée.');

        return $this->redirectToRoute('app_account_email');
    }
}
