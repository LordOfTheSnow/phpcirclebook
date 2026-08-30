<?php

declare(strict_types=1);

/**
 * Generate a bcrypt hash for the admin password (ADR-003).
 *
 * Usage: php bin/hash-password.php
 *
 * The password is read from a hidden stdin prompt so it never appears in the
 * process arguments or your shell history. The script prints the ready-to-paste
 * line for your .env file:
 *
 *   ADMIN_PASSWORD_HASH="$2y$..."
 *
 * The hash uses password_hash() with PASSWORD_BCRYPT and is verified at login
 * with password_verify(). Any tool that produces a compatible bcrypt hash works
 * equally well (e.g. `htpasswd -bnBC 12 "" 'yourpassword'`).
 */

/**
 * Prompt for a line of input with terminal echo disabled, so the typed
 * characters are not shown. Falls back to visible input if the terminal cannot
 * be controlled (e.g. when stdin is piped).
 */
function prompt_hidden(string $label): string
{
    fwrite(STDOUT, $label);

    $isInteractive = stream_isatty(STDIN);

    if ($isInteractive && DIRECTORY_SEPARATOR !== '\\') {
        // POSIX: disable echo via stty, restore afterwards.
        $previous = shell_exec('stty -g 2>/dev/null');
        shell_exec('stty -echo 2>/dev/null');
        $value = fgets(STDIN);
        if ($previous !== null) {
            shell_exec('stty ' . trim($previous) . ' 2>/dev/null');
        } else {
            shell_exec('stty echo 2>/dev/null');
        }
        fwrite(STDOUT, "\n");
    } else {
        // Non-interactive (piped) or Windows: read plainly.
        $value = fgets(STDIN);
    }

    if ($value === false) {
        fwrite(STDERR, "\nError: could not read input.\n");
        exit(1);
    }

    return rtrim($value, "\r\n");
}

$password = prompt_hidden('Enter admin password: ');

if ($password === '') {
    fwrite(STDERR, "Error: password must not be empty.\n");
    exit(1);
}

$confirm = prompt_hidden('Confirm admin password: ');

if (!hash_equals($password, $confirm)) {
    fwrite(STDERR, "Error: passwords do not match.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

if ($hash === false) {
    fwrite(STDERR, "Error: hashing failed.\n");
    exit(1);
}

echo "\nAdd (or update) this line in your .env file:\n\n";
echo 'ADMIN_PASSWORD_HASH="' . $hash . "\"\n\n";
