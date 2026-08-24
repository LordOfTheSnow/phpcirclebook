<?php

declare(strict_types=1);

namespace App;

final class TokenService
{
    public function __construct(
        private readonly string $hmacSecret,
    ) {}

    /**
     * Generate a random approval token (hex-encoded, 32 bytes = 64 chars).
     */
    public function generateApprovalToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Calculate expiry datetime string (UTC) for an approval token.
     */
    public function approvalTokenExpiry(int $days = 7): string
    {
        return gmdate('Y-m-d H:i:s', time() + ($days * 86400));
    }

    /**
     * Generate an HMAC-based unsubscribe token for a given email.
     */
    public function generateUnsubscribeToken(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), $this->hmacSecret);
    }

    /**
     * Verify an HMAC-based unsubscribe token.
     */
    public function verifyUnsubscribeToken(string $email, string $token): bool
    {
        $expected = $this->generateUnsubscribeToken($email);
        return hash_equals($expected, $token);
    }
}
