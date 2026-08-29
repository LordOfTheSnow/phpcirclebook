<?php

declare(strict_types=1);

namespace App;

final class RateLimiter
{
    private const IP_LIMIT = 5;
    private const IP_WINDOW_MINUTES = 15;

    private const EMAIL_LIMIT = 2;
    private const EMAIL_WINDOW_MINUTES = 10;

    public function __construct(
        private readonly Database $db,
    ) {}

    /**
     * Check if the given IP is rate-limited.
     */
    public function isIpLimited(string $ip): bool
    {
        $key = 'ip:' . $ip;
        return $this->db->countRateHits($key, self::IP_WINDOW_MINUTES) >= self::IP_LIMIT;
    }

    /**
     * Check if the given email is rate-limited.
     */
    public function isEmailLimited(string $email): bool
    {
        $key = 'email:' . strtolower(trim($email));
        return $this->db->countRateHits($key, self::EMAIL_WINDOW_MINUTES) >= self::EMAIL_LIMIT;
    }

    /**
     * Record a hit for both IP and email.
     */
    public function recordHit(string $ip, string $email): void
    {
        $this->db->addRateHit('ip:' . $ip);
        $this->db->addRateHit('email:' . strtolower(trim($email)));
    }

    /**
     * Periodically clean old entries (call occasionally).
     */
    public function cleanup(): void
    {
        $this->db->cleanOldRateHits();
    }

    /**
     * Count hits for an arbitrary key within a window. Used by callers that need
     * to throttle their own actions (e.g. admin login) reusing this store.
     */
    public function countHits(string $key, int $windowMinutes): int
    {
        return $this->db->countRateHits($key, $windowMinutes);
    }

    /**
     * Record a hit for an arbitrary key.
     */
    public function recordKeyHit(string $key): void
    {
        $this->db->addRateHit($key);
    }
}
