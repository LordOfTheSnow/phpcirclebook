<?php

declare(strict_types=1);

/**
 * Import members from a semicolon-separated file into the database.
 *
 * Usage: php bin/import.php <file>
 *
 * File format (one entry per line), fields separated by ";":
 *   email@example.com;Optional Name;Optional public note;Optional tags
 *
 * - Only the email is required. Any of the trailing fields (name, public note,
 *   tags) may be left empty and are skipped for that entry.
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
    fwrite(STDERR, "File format: email;name;public note;tags (one per line; only email is required)\n");
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

    // Fields: email ; name ; public note ; tags. Only email is required; any
    // trailing field may be empty and is then skipped (stored as NULL).
    $parts = explode(';', $line, 4);
    $email = trim($parts[0]);

    $normalise = static function (?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    };

    $name = $normalise($parts[1] ?? null);
    $publicNote = $normalise($parts[2] ?? null);
    $tags = $normalise($parts[3] ?? null);

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

    // Insert as approved member with current timestamp, then apply any extra fields.
    $db->createApprovedRecipient($email, $name);
    $extra = [];
    if ($publicNote !== null) {
        $extra['public_note'] = $publicNote;
    }
    if ($tags !== null) {
        $extra['tags'] = $tags;
    }
    if ($extra !== []) {
        $created = $db->findRecipientByEmail($email);
        if ($created !== null) {
            $db->updateRecipient((int) $created['id'], $extra);
        }
    }

    $imported++;
    $displayName = $name ?? '(no name)';
    $extraSummary = [];
    if ($publicNote !== null) {
        $extraSummary[] = 'note';
    }
    if ($tags !== null) {
        $extraSummary[] = 'tags';
    }
    $suffix = $extraSummary !== [] ? ' (+' . implode(', ', $extraSummary) . ')' : '';
    $importedEntries[] = "Line {$lineNum}: {$email} — imported as \"{$displayName}\"{$suffix}";
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
