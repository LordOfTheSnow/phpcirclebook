<?php

declare(strict_types=1);

/**
 * Reset rate limits — clears all entries from the rate_limits table.
 *
 * Usage: php bin/reset-ratelimit.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$dbPath = dirname(__DIR__) . '/' . $_ENV['DB_PATH'];

if (!is_file($dbPath)) {
    fwrite(STDERR, "Error: Database not found at {$dbPath}\n");
    exit(1);
}

$pdo = new PDO("sqlite:{$dbPath}");
$count = $pdo->exec('DELETE FROM rate_limits');

echo "Done. Cleared {$count} rate limit entries.\n";
