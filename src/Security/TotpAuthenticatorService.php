<?php

declare(strict_types=1);

namespace App\Security;

final class TotpAuthenticatorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public function generateSecret(int $length = 32): string
    {
        $secret = '';

        while (strlen($secret) < $length) {
            $secret .= self::BASE32_ALPHABET[random_int(0, strlen(self::BASE32_ALPHABET) - 1)];
        }

        return substr($secret, 0, $length);
    }

    public function getProvisioningUri(string $issuer, string $accountLabel, string $secret): string
    {
        $label = rawurlencode(sprintf('%s:%s', $issuer, $accountLabel));

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            rawurlencode($secret),
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    public function verifyCode(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($normalizedCode) !== self::DIGITS) {
            return false;
        }

        $time = $timestamp ?? time();
        $counter = intdiv($time, self::PERIOD);

        for ($offset = -$window; $offset <= $window; ++$offset) {
            if (hash_equals($this->generateCode($secret, $counter + $offset), $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    public function generateCode(string $secret, int $counter): string
    {
        $binarySecret = $this->decodeBase32($secret);
        $binaryCounter = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $binaryCounter, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function decodeBase32(string $secret): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($normalized) as $character) {
            $position = strpos(self::BASE32_ALPHABET, $character);
            if ($position === false) {
                throw new \InvalidArgumentException('Secret TOTP invalide.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
