<?php

declare(strict_types=1);

return [
    'enabled' => filter_var($_ENV['SSO_ENABLED'] ?? $_SERVER['SSO_ENABLED'] ?? false, \FILTER_VALIDATE_BOOL),
    'secret_key' => (string) ($_ENV['SSO_SECRET_KEY'] ?? $_SERVER['SSO_SECRET_KEY'] ?? ''),
    'portal_url' => (string) ($_ENV['SSO_PORTAL_URL'] ?? $_SERVER['SSO_PORTAL_URL'] ?? ''),
    'token_ttl' => max(60, (int) ($_ENV['SSO_TOKEN_TTL'] ?? $_SERVER['SSO_TOKEN_TTL'] ?? 300)),
    'allowed_app_id' => (string) ($_ENV['SSO_ALLOWED_APP_ID'] ?? $_SERVER['SSO_ALLOWED_APP_ID'] ?? 'gestion-compteurs-eau'),
];
