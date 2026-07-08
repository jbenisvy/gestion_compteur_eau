<?php

declare(strict_types=1);

namespace App\Security;

use Psr\Cache\CacheItemPoolInterface;

final class SsoTokenService
{
    public function __construct(
        private readonly SsoSettings $settings,
        private readonly CacheItemPoolInterface $cachePool,
    ) {
    }

    /**
     * @return array{user_id:string,email:string,name:string,app:string,iat:int,exp:int}
     */
    public function validateIncomingToken(string $token): array
    {
        if (!$this->settings->isEnabled()) {
            throw new SsoValidationException('SSO désactivé.');
        }

        if ($this->settings->getSecretKey() === '') {
            throw new SsoValidationException('Clé secrète SSO absente.');
        }

        [$header, $payload, $signature, $signedData] = $this->decodeJwt($token);

        if (($header['alg'] ?? null) !== 'HS256') {
            throw new SsoValidationException('Algorithme SSO non autorisé.');
        }

        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $signedData, $this->settings->getSecretKey(), true));
        if (!hash_equals($expectedSignature, $signature)) {
            throw new SsoValidationException('Signature SSO invalide.');
        }

        $now = time();
        $issuedAt = $this->readPositiveInt($payload, 'iat');
        $expiresAt = $this->readPositiveInt($payload, 'exp');

        if ($issuedAt > $now + 30) {
            throw new SsoValidationException('Token SSO non encore valide.');
        }

        if ($expiresAt <= $now) {
            throw new SsoValidationException('Token SSO expiré.');
        }

        if (($expiresAt - $issuedAt) > $this->settings->getTokenTtl()) {
            throw new SsoValidationException('Durée de vie du token SSO invalide.');
        }

        $appId = trim((string) ($payload['app'] ?? ''));
        if ($appId === '' || $appId !== $this->settings->getAllowedAppId()) {
            throw new SsoValidationException('Application cible SSO invalide.');
        }

        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $userId = trim((string) ($payload['user_id'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));

        if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new SsoValidationException('Email SSO invalide.');
        }

        if ($userId === '') {
            throw new SsoValidationException('Identifiant utilisateur SSO absent.');
        }

        if ($name === '') {
            throw new SsoValidationException('Nom utilisateur SSO absent.');
        }

        $this->guardAgainstReplay($token, $expiresAt - $now);

        return [
            'user_id' => $userId,
            'email' => $email,
            'name' => $name,
            'app' => $appId,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];
    }

    public function isPortalRefererAllowed(?string $referer): bool
    {
        $portalUrl = $this->settings->getPortalUrl();
        if ($referer === null || $referer === '' || $portalUrl === '') {
            return true;
        }

        $portalHost = parse_url($portalUrl, \PHP_URL_HOST);
        $refererHost = parse_url($referer, \PHP_URL_HOST);

        if (!is_string($portalHost) || !is_string($refererHost) || $portalHost === '' || $refererHost === '') {
            return false;
        }

        return hash_equals(mb_strtolower($portalHost), mb_strtolower($refererHost));
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:string,3:string}
     */
    private function decodeJwt(string $token): array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            throw new SsoValidationException('Format du token SSO invalide.');
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (!is_array($header) || !is_array($payload)) {
            throw new SsoValidationException('Contenu du token SSO invalide.');
        }

        return [$header, $payload, $signature, $encodedHeader.'.'.$encodedPayload];
    }

    private function readPositiveInt(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new SsoValidationException(sprintf('Champ SSO "%s" invalide.', $key));
        }

        return (int) $value;
    }

    private function guardAgainstReplay(string $token, int $ttl): void
    {
        $cacheKey = 'sso_token_'.hash('sha256', $token);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            throw new SsoValidationException('Token SSO déjà utilisé.');
        }

        $cacheItem->set(true);
        $cacheItem->expiresAfter(max(1, $ttl));
        $this->cachePool->save($cacheItem);
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);

        if ($decoded === false) {
            throw new SsoValidationException('Encodage du token SSO invalide.');
        }

        return $decoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
