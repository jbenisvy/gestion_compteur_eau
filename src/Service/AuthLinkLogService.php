<?php

namespace App\Service;

final class AuthLinkLogService
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $environment,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findEmailSendEvents(?string $emailFilter = null, ?string $statusFilter = null, int $limit = 100): array
    {
        $entries = [];
        $path = $this->getLogPath();
        if (!is_file($path) || !is_readable($path)) {
            return $entries;
        }

        $emailFilter = $this->normalizeFilter($emailFilter);
        $statusFilter = $this->normalizeStatus($statusFilter);
        $lines = @file($path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return $entries;
        }

        foreach (array_reverse($lines) as $line) {
            $entry = $this->parseJsonLine($line);
            if ($entry === null) {
                continue;
            }

            $message = (string) ($entry['message'] ?? '');
            if (!str_starts_with($message, 'auth_link.email_send_')) {
                continue;
            }

            $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
            $email = strtolower(trim((string) ($context['email'] ?? '')));
            $status = $this->extractStatus($message);

            if ($emailFilter !== null && !str_contains($email, $emailFilter)) {
                continue;
            }

            if ($statusFilter !== null && $status !== $statusFilter) {
                continue;
            }

            $entries[] = [
                'timestamp' => (string) ($entry['datetime'] ?? ''),
                'status' => $status,
                'type' => (string) ($context['type'] ?? ''),
                'email' => $email,
                'user_id' => $context['user_id'] ?? null,
                'is_verified' => $context['is_verified'] ?? null,
                'mail_from' => (string) ($context['mail_from'] ?? ''),
                'subject' => (string) ($context['subject'] ?? ''),
                'expires_at' => (string) ($context['expires_at'] ?? ''),
                'link_url' => (string) ($context['link_url'] ?? ''),
                'error_class' => (string) ($context['error_class'] ?? ''),
                'error_message' => (string) ($context['error_message'] ?? ''),
            ];

            if (\count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    public function getLogPath(): string
    {
        return $this->projectDir . '/var/log/auth_link_' . $this->environment . '.log';
    }

    private function normalizeFilter(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return $value !== '' ? $value : null;
    }

    private function normalizeStatus(?string $value): ?string
    {
        $value = $this->normalizeFilter($value);

        return \in_array($value, ['started', 'succeeded', 'failed'], true) ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonLine(string $line): ?array
    {
        $decoded = json_decode($line, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractStatus(string $message): string
    {
        return match ($message) {
            'auth_link.email_send_started' => 'started',
            'auth_link.email_send_succeeded' => 'succeeded',
            'auth_link.email_send_failed' => 'failed',
            default => 'unknown',
        };
    }
}
