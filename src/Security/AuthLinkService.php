<?php

namespace App\Security;

use App\Entity\User;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $authLogger,
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

        $this->sendEmail($email, $user, 'magic_link', [
            'expires_at' => $loginLink->getExpiresAt()->format(\DATE_ATOM),
            'link_url' => $loginLink->getUrl(),
        ]);
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

        $this->sendEmail($email, $user, 'verify_email');
    }

    private function sendEmail(TemplatedEmail $email, User $user, string $type, array $context = []): void
    {
        $logContext = array_merge($context, [
            'type' => $type,
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'is_verified' => $user->isVerified(),
            'mail_from' => $this->mailFrom,
            'subject' => $email->getSubject(),
        ]);

        $this->authLogger->info('auth_link.email_send_started', $logContext);

        try {
            $this->mailer->send($email);
            $this->authLogger->info('auth_link.email_send_succeeded', $logContext);
        } catch (\Throwable $e) {
            $this->authLogger->error('auth_link.email_send_failed', array_merge($logContext, [
                'error_class' => $e::class,
                'error_message' => $e->getMessage(),
            ]));

            throw $e;
        }
    }
}
