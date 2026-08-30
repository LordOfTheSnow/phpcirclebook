<?php

declare(strict_types=1);

/**
 * Command-line admin tool for managing recipients (ADR-003).
 *
 * Usage:
 *   php bin/admin.php list
 *   php bin/admin.php add <email> [name] [--comment=..] [--public-note=..] [--tags=..]
 *   php bin/admin.php edit <id> [--name=..] [--email=..] [--comment=..] [--public-note=..] [--tags=..] [--status=..]
 *   php bin/admin.php status <id> <pending|approved|rejected>
 *   php bin/admin.php delete <id>
 *
 * Recipients are addressed by their numeric id (stable even when the email is
 * edited). Editing is non-interactive: only the flags you pass are changed;
 * omitted flags leave the corresponding field untouched.
 *
 * Setting status to "approved" sends the recipient a confirmation email. On
 * shared hosting the CLI often cannot send mail; in that case a warning is
 * printed and the status change still succeeds.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

use App\Database;
use App\DuplicateEmailException;
use App\Mailer;
use App\TokenService;
use Dotenv\Dotenv;

// --- Bootstrap ---

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
$dotenv->required(['APP_NAME', 'APP_URL', 'ADMIN_EMAIL', 'HMAC_SECRET', 'DB_PATH']);

$appName = $_ENV['APP_NAME'];
$appUrl = rtrim($_ENV['APP_URL'], '/');
$adminEmail = $_ENV['ADMIN_EMAIL'];
$hmacSecret = $_ENV['HMAC_SECRET'];
$dbPath = dirname(__DIR__) . '/' . $_ENV['DB_PATH'];

// Translator is needed because Mailer uses __() for email bodies.
if (isset($_ENV['APP_LOCALE'])) {
    App\Translator::init($_ENV['APP_LOCALE'], dirname(__DIR__) . '/lang');
}

$db = new Database($dbPath);
$tokenService = new TokenService($hmacSecret);
$mailer = new Mailer($appName, $appUrl, $adminEmail, $tokenService);

// --- Argument parsing ---

$argvCopy = $argv;
array_shift($argvCopy); // drop script name
$command = array_shift($argvCopy) ?? '';

$ALLOWED_STATUSES = ['pending', 'approved', 'rejected'];

/**
 * Split remaining args into positional args and --key=value flags.
 *
 * @return array{0: list<string>, 1: array<string,string>}
 */
function parse_args(array $args): array
{
    $positional = [];
    $flags = [];
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--')) {
            $pair = substr($arg, 2);
            $eq = strpos($pair, '=');
            if ($eq === false) {
                $flags[$pair] = '';
            } else {
                $flags[substr($pair, 0, $eq)] = substr($pair, $eq + 1);
            }
        } else {
            $positional[] = $arg;
        }
    }
    return [$positional, $flags];
}

function fail(string $message): never
{
    fwrite(STDERR, "Error: {$message}\n");
    exit(1);
}

function print_usage(): void
{
    echo <<<TXT
Usage:
  php bin/admin.php list
  php bin/admin.php add <email> [name] [--comment=..] [--public-note=..] [--tags=..]
  php bin/admin.php edit <id> [--name=..] [--email=..] [--comment=..] [--public-note=..] [--tags=..] [--status=..]
  php bin/admin.php status <id> <pending|approved|rejected>
  php bin/admin.php delete <id>

Notes:
  - Recipients are addressed by numeric id (see `list`).
  - On `edit`, only the flags you pass are changed; omit a flag to leave it unchanged.
  - Setting status to "approved" sends a confirmation email (warns if mail can't send).

TXT;
}

[$positional, $flags] = parse_args($argvCopy);

// --- Dispatch ---

switch ($command) {
    case 'list':
        cmd_list($db);
        break;
    case 'add':
        cmd_add($db, $positional, $flags);
        break;
    case 'edit':
        cmd_edit($db, $mailer, $positional, $flags, $ALLOWED_STATUSES);
        break;
    case 'status':
        cmd_status($db, $mailer, $positional, $ALLOWED_STATUSES);
        break;
    case 'delete':
        cmd_delete($db, $positional);
        break;
    case '':
    case 'help':
    case '--help':
    case '-h':
        print_usage();
        break;
    default:
        fwrite(STDERR, "Unknown command: {$command}\n\n");
        print_usage();
        exit(1);
}

// --- Commands ---

function cmd_list(Database $db): void
{
    $recipients = $db->getAllRecipients();

    if ($recipients === []) {
        echo "No recipients.\n";
        return;
    }

    // Size each column to the widest value it holds (with header minimums), so
    // nothing is truncated regardless of how long names or emails are.
    $headers = [
        'id'          => 'ID',
        'email'       => 'Email',
        'name'        => 'Name',
        'status'      => 'Status',
        'public_note' => 'Public note',
        'tags'        => 'Tags',
    ];

    $widths = [];
    foreach ($headers as $key => $label) {
        $widths[$key] = mb_strlen($label);
    }
    foreach ($recipients as $r) {
        foreach ($headers as $key => $label) {
            $widths[$key] = max($widths[$key], mb_strlen((string) ($r[$key] ?? '')));
        }
    }

    $render = static function (array $cells) use ($headers, $widths): string {
        $parts = [];
        foreach ($headers as $key => $label) {
            $value = (string) ($cells[$key] ?? '');
            // mb-aware left-pad to the column width.
            $pad = $widths[$key] - mb_strlen($value);
            $parts[] = $value . ($pad > 0 ? str_repeat(' ', $pad) : '');
        }
        return implode('  ', $parts);
    };

    $headerLine = $render($headers);
    echo $headerLine . "\n";
    echo str_repeat('-', mb_strlen($headerLine)) . "\n";
    foreach ($recipients as $r) {
        echo $render([
            'id'          => $r['id'],
            'email'       => $r['email'] ?? '',
            'name'        => $r['name'] ?? '',
            'status'      => $r['status'] ?? '',
            'public_note' => $r['public_note'] ?? '',
            'tags'        => $r['tags'] ?? '',
        ]) . "\n";
    }
    echo "\n" . count($recipients) . " recipient(s).\n";
}

function cmd_add(Database $db, array $positional, array $flags): void
{
    $email = trim($positional[0] ?? '');
    // Name can come from the positional arg or the --name flag (positional wins if both).
    $name = $positional[1] ?? ($flags['name'] ?? null);
    $name = $name !== null ? trim((string) $name) : null;
    if ($name === '') {
        $name = null;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail("invalid email address: {$email}");
    }

    if ($db->findRecipientByEmail($email) !== null) {
        fail("a recipient with that email already exists: {$email}");
    }

    $db->createApprovedRecipient($email, $name);
    $created = $db->findRecipientByEmail($email);
    $id = $created ? (int) $created['id'] : 0;

    // Apply any optional extra fields supplied as flags.
    $extraMap = [
        'comment'     => 'comment',
        'public-note' => 'public_note',
        'tags'        => 'tags',
    ];
    $extra = [];
    foreach ($extraMap as $flag => $column) {
        if (array_key_exists($flag, $flags)) {
            $value = $flags[$flag];
            $extra[$column] = $value === '' ? null : $value;
        }
    }
    if ($id > 0 && $extra !== []) {
        $db->updateRecipient($id, $extra);
    }

    echo "Added recipient #{$id}: {$email}" . ($name ? " ({$name})" : '') . " [approved]\n";
}

function cmd_edit(Database $db, Mailer $mailer, array $positional, array $flags, array $allowedStatuses): void
{
    $id = (int) ($positional[0] ?? 0);
    if ($id <= 0) {
        fail('edit requires a numeric id. Usage: edit <id> [--flags]');
    }

    $recipient = $db->findRecipientById($id);
    if ($recipient === null) {
        fail("no recipient with id {$id}");
    }

    // Map CLI flags to DB columns; only include provided flags.
    $flagMap = [
        'name'        => 'name',
        'email'       => 'email',
        'comment'     => 'comment',
        'public-note' => 'public_note',
        'tags'        => 'tags',
        'status'      => 'status',
    ];

    $fields = [];
    foreach ($flagMap as $flag => $column) {
        if (array_key_exists($flag, $flags)) {
            $value = $flags[$flag];
            $fields[$column] = $value === '' ? null : $value;
        }
    }

    if ($fields === []) {
        fail('no fields to update. Provide at least one --flag.');
    }

    if (isset($fields['email']) && ($fields['email'] === null || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL))) {
        fail('invalid email address for --email');
    }

    if (isset($fields['status'])) {
        if (!in_array($fields['status'], $allowedStatuses, true)) {
            fail('--status must be one of: ' . implode(', ', $allowedStatuses));
        }
    }

    $wasApproved = $recipient['status'] === 'approved';
    $willApprove = isset($fields['status']) && $fields['status'] === 'approved';

    try {
        $db->updateRecipient($id, $fields);
    } catch (DuplicateEmailException $e) {
        fail('that email address is already in use by another recipient.');
    }

    echo "Updated recipient #{$id}.\n";

    if ($willApprove && !$wasApproved) {
        $email = $fields['email'] ?? $recipient['email'];
        $name = array_key_exists('name', $fields) ? $fields['name'] : $recipient['name'];
        send_confirmation_with_warning($mailer, $email, $name);
    }
}

function cmd_status(Database $db, Mailer $mailer, array $positional, array $allowedStatuses): void
{
    $id = (int) ($positional[0] ?? 0);
    $status = trim($positional[1] ?? '');

    if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
        fail('usage: status <id> <' . implode('|', $allowedStatuses) . '>');
    }

    $recipient = $db->findRecipientById($id);
    if ($recipient === null) {
        fail("no recipient with id {$id}");
    }

    $wasApproved = $recipient['status'] === 'approved';
    $db->updateRecipient($id, ['status' => $status]);
    echo "Recipient #{$id} status set to {$status}.\n";

    if ($status === 'approved' && !$wasApproved) {
        send_confirmation_with_warning($mailer, (string) $recipient['email'], $recipient['name']);
    }
}

function cmd_delete(Database $db, array $positional): void
{
    $id = (int) ($positional[0] ?? 0);
    if ($id <= 0) {
        fail('delete requires a numeric id. Usage: delete <id>');
    }

    $recipient = $db->findRecipientById($id);
    if ($recipient === null) {
        fail("no recipient with id {$id}");
    }

    $db->deleteRecipientById($id);
    echo "Deleted recipient #{$id}: {$recipient['email']}\n";
}

/**
 * Attempt to send the approval-confirmation email, printing a warning instead of
 * failing when the host cannot send mail (common on shared-hosting CLI).
 */
function send_confirmation_with_warning(Mailer $mailer, string $email, ?string $name): void
{
    $sent = false;
    try {
        $sent = $mailer->sendApprovalConfirmation($email, $name);
    } catch (\Throwable $e) {
        $sent = false;
    }

    if ($sent) {
        echo "Sent approval confirmation email to {$email}.\n";
    } else {
        fwrite(STDERR, "Warning: could not send confirmation email to {$email}. "
            . "The status change was applied; check the mail configuration if delivery is expected.\n");
    }
}
