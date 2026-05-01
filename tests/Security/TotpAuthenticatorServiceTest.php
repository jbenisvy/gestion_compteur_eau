<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\TotpAuthenticatorService;
use PHPUnit\Framework\TestCase;

final class TotpAuthenticatorServiceTest extends TestCase
{
    public function testGeneratesExpectedCodeFromKnownSecret(): void
    {
        $service = new TotpAuthenticatorService();

        self::assertSame('282760', $service->generateCode('JBSWY3DPEHPK3PXP', 0));
    }

    public function testVerifiesCodeWithinAllowedTimeWindow(): void
    {
        $service = new TotpAuthenticatorService();
        $secret = 'JBSWY3DPEHPK3PXP';
        $timestamp = 59;

        self::assertTrue($service->verifyCode($secret, '996554', 0, $timestamp));
        self::assertFalse($service->verifyCode($secret, '000000', 0, $timestamp));
    }

    public function testBuildsProvisioningUriForAuthenticatorApps(): void
    {
        $service = new TotpAuthenticatorService();
        $uri = $service->getProvisioningUri('Gestion Compteurs Eau', 'copro@example.com', 'ABCDEF1234567890');

        self::assertStringContainsString('otpauth://totp/', $uri);
        self::assertStringContainsString('secret=ABCDEF1234567890', $uri);
        self::assertStringContainsString('issuer=Gestion%20Compteurs%20Eau', $uri);
        self::assertStringContainsString('Gestion%20Compteurs%20Eau%3Acopro%40example.com', $uri);
    }
}
