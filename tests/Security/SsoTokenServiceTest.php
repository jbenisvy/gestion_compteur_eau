<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\SsoSettings;
use App\Security\SsoTokenService;
use App\Security\SsoValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class SsoTokenServiceTest extends TestCase
{
    public function testValidTokenIsAccepted(): void
    {
        $service = $this->buildService();
        $now = time();
        $token = $this->buildToken([
            'user_id' => '42',
            'email' => 'user@example.com',
            'name' => 'Test User',
            'app' => 'gestion-compteurs-eau',
            'iat' => $now,
            'exp' => $now + 120,
        ]);

        $payload = $service->validateIncomingToken($token);

        self::assertSame('42', $payload['user_id']);
        self::assertSame('user@example.com', $payload['email']);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $service = $this->buildService();
        $now = time();
        $token = $this->buildToken([
            'user_id' => '42',
            'email' => 'user@example.com',
            'name' => 'Test User',
            'app' => 'gestion-compteurs-eau',
            'iat' => $now - 180,
            'exp' => $now - 60,
        ]);

        $this->expectException(SsoValidationException::class);
        $this->expectExceptionMessage('expiré');
        $service->validateIncomingToken($token);
    }

    public function testForgedTokenIsRejected(): void
    {
        $service = $this->buildService();
        $now = time();
        $token = $this->buildToken([
            'user_id' => '42',
            'email' => 'user@example.com',
            'name' => 'Test User',
            'app' => 'gestion-compteurs-eau',
            'iat' => $now,
            'exp' => $now + 120,
        ], 'another-secret');

        $this->expectException(SsoValidationException::class);
        $this->expectExceptionMessage('Signature');
        $service->validateIncomingToken($token);
    }

    public function testReplayIsRejected(): void
    {
        $service = $this->buildService();
        $now = time();
        $token = $this->buildToken([
            'user_id' => '42',
            'email' => 'user@example.com',
            'name' => 'Test User',
            'app' => 'gestion-compteurs-eau',
            'iat' => $now,
            'exp' => $now + 120,
        ]);

        $service->validateIncomingToken($token);

        $this->expectException(SsoValidationException::class);
        $this->expectExceptionMessage('déjà utilisé');
        $service->validateIncomingToken($token);
    }

    private function buildService(): SsoTokenService
    {
        return new SsoTokenService(
            new SsoSettings(
                true,
                'top-secret-key',
                'https://portal.jsb-solutions.example',
                300,
                'gestion-compteurs-eau',
            ),
            new ArrayAdapter(),
        );
    }

    /**
     * @param array<string, int|string> $payload
     */
    private function buildToken(array $payload, string $secret = 'top-secret-key'): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $encodedHeader = $this->base64UrlEncode(json_encode($header, \JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, \JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $secret, true);

        return $encodedHeader.'.'.$encodedPayload.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
