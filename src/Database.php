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
                comment TEXT DEFAULT NULL,
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

        // Add optional columns for existing databases (idempotent).
        $this->addColumnIfMissing('recipients', 'comment', "TEXT DEFAULT NULL");
        // public_note: admin-curated annotation, visible to list recipients (ADR-003).
        $this->addColumnIfMissing('recipients', 'public_note', "TEXT DEFAULT NULL");
        // tags: single free-text taxonomy field, admin-internal (ADR-003).
        $this->addColumnIfMissing('recipients', 'tags', "TEXT DEFAULT NULL");
    }

    /**
     * Add a column to a table only if it does not already exist. Idempotent, so
     * it is safe to run on every boot for both fresh and existing databases.
     */
    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $columns = $this->pdo->query("PRAGMA table_info({$table})")->fetchAll();
        foreach ($columns as $existing) {
            if (($existing['name'] ?? '') === $column) {
                return;
            }
        }
        $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
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
        $stmt = $this->pdo->query("SELECT email, name, public_note, tags, created_at FROM recipients WHERE status = 'approved' ORDER BY name, email");
        return $stmt->fetchAll();
    }

    public function createRecipient(string $email, ?string $name, ?string $comment, string $token, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO recipients (email, name, comment, status, token, token_expires_at)
            VALUES (:email, :name, :comment, 'pending', :token, :expires)
        ");
        $stmt->execute([
            'email' => $email,
            'name' => $name,
            'comment' => $comment,
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

    // --- Admin operations (ADR-003) ---

    /**
     * Return all recipients regardless of status, newest first. Used by the admin tool.
     */
    public function getAllRecipients(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, email, name, comment, public_note, tags, status, created_at, updated_at
            FROM recipients
            ORDER BY created_at DESC, id DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Find a single recipient by primary key, or null if not found.
     */
    public function findRecipientById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM recipients WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Partial update of a recipient, keyed on id.
     *
     * Only fields in the allowed set are written; unknown keys are rejected to
     * prevent editing security-sensitive columns (token, timestamps). updated_at
     * is always refreshed. A uniqueness collision on email surfaces as a
     * DuplicateEmailException rather than a raw PDOException.
     *
     * @param array<string,mixed> $fields
     * @throws \InvalidArgumentException on an unknown field or empty update
     * @throws DuplicateEmailException   when the new email is already in use
     */
    public function updateRecipient(int $id, array $fields): void
    {
        $allowed = ['name', 'email', 'comment', 'public_note', 'tags', 'status'];

        $set = [];
        $params = ['id' => $id];
        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                throw new \InvalidArgumentException("Field not editable: {$column}");
            }
            $set[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        if ($set === []) {
            throw new \InvalidArgumentException('No fields to update.');
        }

        $set[] = "updated_at = datetime('now')";
        $sql = "UPDATE recipients SET " . implode(', ', $set) . " WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new DuplicateEmailException(
                    'Email address is already in use by another recipient.',
                    0,
                    $e
                );
            }
            throw $e;
        }
    }

    /**
     * Delete a recipient by primary key. The admin handle is id (stable even if
     * the email is later edited), unlike deleteRecipientByEmail used by unsubscribe.
     */
    public function deleteRecipientById(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM recipients WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private function isUniqueConstraintViolation(\PDOException $e): bool
    {
        // SQLite reports UNIQUE violations with SQLSTATE 23000 and a message
        // containing "UNIQUE constraint failed".
        return str_contains($e->getMessage(), 'UNIQUE constraint failed');
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
