<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\AuthLinkService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('security/login.html.twig');
    }

    #[Route('/login/send', name: 'app_login_send', methods: ['POST'])]
    public function sendLoginLink(
        Request $request,
        UserRepository $userRepository,
        AuthLinkService $authLinkService
    ): Response {
        if (!$this->isCsrfTokenValid('magic_login_request', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
            return $this->redirectToRoute('app_login');
        }

        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        if ($email === '') {
            $this->addFlash('error', 'Veuillez saisir une adresse email.');
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if ($user instanceof User) {
            if ($user->isVerified()) {
                $authLinkService->sendMagicLinkEmail($user);
            } else {
                $authLinkService->sendVerificationEmail($user);
            }
        }

        $this->addFlash('success', 'Si un compte existe, un email vient d\'être envoyé.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/login/check', name: 'app_login_check', methods: ['GET'])]
    public function check(): never
    {
        throw new \LogicException('Cette route est gérée par le firewall.');
    }

    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyUserEmail(
        Request $request,
        UserRepository $userRepository,
        VerifyEmailHelperInterface $verifyEmailHelper,
        EntityManagerInterface $em
    ): Response {
        $id = $request->query->get('id');
        $user = is_scalar($id) ? $userRepository->find((int) $id) : null;

        if (!$user instanceof User) {
            $this->addFlash('error', 'Lien invalide.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', $e->getReason());
            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $em->flush();

        $this->addFlash('success', 'Adresse email vérifiée. Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/admin/login', name: 'app_login_admin', methods: ['GET', 'POST'])]
    public function adminLogin(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('security/login_admin.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Symfony gère automatiquement la déconnexion via firewall
    }
}
