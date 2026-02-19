<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class AuthLinkService
{
    public function __construct(
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly string $mailFrom,
    ) {
    }

    public function sendMagicLinkEmail(User $user): void
    {
        $loginLink = $this->loginLinkHandler->createLoginLink($user);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Gestion Compteurs Eau'))
            ->to(new Address($user->getEmail()))
            ->subject('Votre lien de connexion sécurisé')
            ->htmlTemplate('emails/magic_link.html.twig')
            ->context([
                'user' => $user,
                'magicUrl' => $loginLink->getUrl(),
                'expiresAt' => $loginLink->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    public function sendVerificationEmail(User $user): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Gestion Compteurs Eau'))
            ->to(new Address($user->getEmail()))
            ->subject('Vérifiez votre adresse email')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'user' => $user,
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAtMessageKey' => $signature->getExpirationMessageKey(),
                'expiresAtMessageData' => $signature->getExpirationMessageData(),
            ]);

        $this->mailer->send($email);
    }
}
