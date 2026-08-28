<?php

declare(strict_types=1);

/**
 * Import members from a semicolon-separated file into the database.
 *
 * Usage: php bin/import.php <file>
 *
 * File format (one entry per line):
 *   email@example.com;Optional Name
 *
 * - Imported members are set to "approved" status with the current date as registration date.
 * - Duplicates (email already in the database) are skipped and listed in the output.
 * - Invalid lines are reported with line numbers.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

use App\Database;
use Dotenv\Dotenv;

// --- Bootstrap ---

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$dbPath = dirname(__DIR__) . '/' . $_ENV['DB_PATH'];
$db = new Database($dbPath);

// --- Argument handling ---

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/import.php <file>\n");
    fwrite(STDERR, "File format: email;name (one per line, name is optional)\n");
    exit(1);
}

$filePath = $argv[1];

if (!is_file($filePath)) {
    fwrite(STDERR, "Error: File not found: {$filePath}\n");
    exit(1);
}

$lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if ($lines === false) {
    fwrite(STDERR, "Error: Could not read file: {$filePath}\n");
    exit(1);
}

// --- Import ---

$imported = 0;
$importedEntries = [];
$duplicates = [];
$errors = [];

foreach ($lines as $lineNumber => $line) {
    $lineNum = $lineNumber + 1;

    // Skip comment lines
    if (str_starts_with(trim($line), '#')) {
        continue;
    }

    $parts = explode(';', $line, 2);
    $email = trim($parts[0]);
    $name = isset($parts[1]) ? trim($parts[1]) : null;

    // Treat empty name as null
    if ($name === '') {
        $name = null;
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Line {$lineNum}: Invalid email address: {$email}";
        continue;
    }

    // Check for duplicates in the database
    $existing = $db->findRecipientByEmail($email);

    if ($existing !== null) {
        $existingName = $existing['name'] ?? '(no name)';
        $newName = $name ?? '(no name)';
        $duplicates[] = "Line {$lineNum}: {$email} — already exists as \"{$existingName}\" (registered {$existing['created_at']}), skipping import of \"{$newName}\"";
        continue;
    }

    // Insert as approved member with current timestamp
    $db->createApprovedRecipient($email, $name);
    $imported++;
    $displayName = $name ?? '(no name)';
    $importedEntries[] = "Line {$lineNum}: {$email} — imported as \"{$displayName}\"";
}

// --- Summary ---

echo "\n=== Import Summary ===\n\n";
echo "File:      {$filePath}\n";
echo "Lines:     " . count($lines) . "\n";
echo "Imported:  {$imported}\n";
echo "Duplicates: " . count($duplicates) . "\n";
echo "Errors:    " . count($errors) . "\n";

if (!empty($importedEntries)) {
    echo "\n--- Imported ---\n";
    foreach ($importedEntries as $i) {
        echo "  {$i}\n";
    }
}

if (!empty($duplicates)) {
    echo "\n--- Duplicates (kept existing entry) ---\n";
    foreach ($duplicates as $d) {
        echo "  {$d}\n";
    }
}

if (!empty($errors)) {
    echo "\n--- Errors ---\n";
    foreach ($errors as $e) {
        echo "  {$e}\n";
    }
}

echo "\nDone.\n";
