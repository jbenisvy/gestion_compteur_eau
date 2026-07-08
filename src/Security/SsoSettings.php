<?php

declare(strict_types=1);

namespace App\Security;

final class SsoSettings
{
    public function __construct(
        private readonly bool $enabled,
        private readonly string $secretKey,
        private readonly string $portalUrl,
        private readonly int $tokenTtl,
        private readonly string $allowedAppId,
    ) {
    }

    public static function fromConfigFile(string $path): self
    {
        $config = require $path;
        if (!is_array($config)) {
            throw new \RuntimeException('La configuration SSO doit retourner un tableau.');
        }

        return new self(
            (bool) ($config['enabled'] ?? false),
            trim((string) ($config['secret_key'] ?? '')),
            trim((string) ($config['portal_url'] ?? '')),
            max(60, (int) ($config['token_ttl'] ?? 300)),
            trim((string) ($config['allowed_app_id'] ?? '')),
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getPortalUrl(): string
    {
        return $this->portalUrl;
    }

    public function getTokenTtl(): int
    {
        return $this->tokenTtl;
    }

    public function getAllowedAppId(): string
    {
        return $this->allowedAppId;
    }
}
