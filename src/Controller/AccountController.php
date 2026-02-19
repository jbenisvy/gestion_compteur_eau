<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\AuthLinkService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/compte/email', name: 'app_account_email', methods: ['GET', 'POST'])]
    public function email(Request $request, EntityManagerInterface $em, AuthLinkService $authLinkService): Response
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

        return $this->render('account/email.html.twig', [
            'userEmail' => $user->getEmail(),
            'isVerified' => $user->isVerified(),
        ]);
    }
}
