<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOStatement;

final class Database
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO("sqlite:{$dbPath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS recipients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                name TEXT DEFAULT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
                token TEXT DEFAULT NULL,
                token_expires_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $this->pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_rate_limits_key_created
            ON rate_limits (key, created_at)
        ");
    }

    // --- Recipient queries ---

    public function findRecipientByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM recipients WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findRecipientByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM recipients WHERE token = :token AND token_expires_at > datetime('now')"
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getApprovedRecipients(): array
    {
        $stmt = $this->pdo->query("SELECT email, name, created_at FROM recipients WHERE status = 'approved' ORDER BY name, email");
        return $stmt->fetchAll();
    }

    public function createRecipient(string $email, ?string $name, string $token, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO recipients (email, name, status, token, token_expires_at)
            VALUES (:email, :name, 'pending', :token, :expires)
        ");
        $stmt->execute([
            'email' => $email,
            'name' => $name,
            'token' => $token,
            'expires' => $expiresAt,
        ]);
    }

    /**
     * Create a recipient with "approved" status (used for imports).
     */
    public function createApprovedRecipient(string $email, ?string $name): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO recipients (email, name, status)
            VALUES (:email, :name, 'approved')
        ");
        $stmt->execute([
            'email' => $email,
            'name' => $name,
        ]);
    }

    public function updateRecipientToken(string $email, string $token, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE recipients
            SET token = :token, token_expires_at = :expires, updated_at = datetime('now')
            WHERE email = :email
        ");
        $stmt->execute([
            'email' => $email,
            'token' => $token,
            'expires' => $expiresAt,
        ]);
    }

    public function approveRecipient(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE recipients
            SET status = 'approved', token = NULL, token_expires_at = NULL, updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }

    public function rejectRecipient(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE recipients
            SET status = 'rejected', token = NULL, token_expires_at = NULL, updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }

    public function deleteRecipientByEmail(string $email): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM recipients WHERE email = :email");
        $stmt->execute(['email' => $email]);
    }

    // --- Rate limiting ---

    public function countRateHits(string $key, int $windowMinutes): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as cnt FROM rate_limits
            WHERE key = :key AND created_at > datetime('now', :window)
        ");
        $stmt->execute([
            'key' => $key,
            'window' => "-{$windowMinutes} minutes",
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function addRateHit(string $key): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO rate_limits (key) VALUES (:key)");
        $stmt->execute(['key' => $key]);
    }

    public function cleanOldRateHits(): void
    {
        $this->pdo->exec("DELETE FROM rate_limits WHERE created_at < datetime('now', '-1 hour')");
    }
}
