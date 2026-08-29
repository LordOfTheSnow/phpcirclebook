<?php

declare(strict_types=1);

/*
 * Admin front controller (ADR-003).
 *
 * A standalone entry point, separate from public/index.php, that lets the single
 * operator read, add, edit, and remove recipients. Password-gated with a session
 * login; all state-changing POSTs carry a CSRF token. English-only by design.
 *
 * MUST be served from behind public/ over HTTPS. The .htaccess rules keep the
 * database and .env unreachable; this file is the only admin surface.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

use App\Database;
use App\DuplicateEmailException;
use App\Mailer;
use App\RateLimiter;
use App\TokenService;
use Dotenv\Dotenv;

// --- Bootstrap ---

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
// ADMIN_PASSWORD_HASH is intentionally NOT required here: when it is missing we
// want a friendly "not configured yet" page, not a dotenv ValidationException.
$dotenv->required(['APP_NAME', 'APP_URL', 'ADMIN_EMAIL', 'HMAC_SECRET', 'DB_PATH']);

$appName = $_ENV['APP_NAME'];
$appUrl = rtrim($_ENV['APP_URL'], '/');
$adminEmail = $_ENV['ADMIN_EMAIL'];
$hmacSecret = $_ENV['HMAC_SECRET'];
$dbPath = dirname(__DIR__) . '/' . $_ENV['DB_PATH'];
$adminPasswordHash = trim((string) ($_ENV['ADMIN_PASSWORD_HASH'] ?? ''));
$forceSecureCookie = filter_var($_ENV['ADMIN_FORCE_SECURE_COOKIE'] ?? false, FILTER_VALIDATE_BOOL);

// Guard: the admin tool cannot run until a password hash is configured. Show a
// clear setup message instead of a fatal error or an unusable login form.
if ($adminPasswordHash === '') {
    http_response_code(503);
    render_page('Admin not configured', <<<HTML
<article>
    <header><strong>Admin tool not configured</strong></header>
    <p>The admin tool is not available yet because no admin password has been set.</p>
    <p>To enable it, generate a password hash and add it to your <code>.env</code> file:</p>
    <pre><code>php bin/hash-password.php</code></pre>
    <p>Copy the printed <code>ADMIN_PASSWORD_HASH="…"</code> line into <code>.env</code>,
    then reload this page. See the "Admin tool" section of the README for details.</p>
</article>
HTML);
    exit;
}

$db = new Database($dbPath);
$tokenService = new TokenService($hmacSecret);
$rateLimiter = new RateLimiter($db);
$mailer = new Mailer($appName, $appUrl, $adminEmail, $tokenService);

// --- Constants ---

const ADMIN_IDLE_TIMEOUT = 1800;      // 30 minutes
const ADMIN_LOGIN_LIMIT = 5;          // failed attempts...
const ADMIN_LOGIN_WINDOW_MIN = 15;    // ...per this many minutes, per IP

// --- Session hardening (ADR-003 decision 6) ---

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443)
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $isHttps || $forceSecureCookie,
]);
session_name('phpcirclebook_admin');
session_start();

// --- Helpers ---

function admin_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_logged_in(): bool
{
    if (empty($_SESSION['admin_authenticated'])) {
        return false;
    }
    // Idle timeout.
    $last = $_SESSION['admin_last_activity'] ?? 0;
    if (time() - (int) $last > ADMIN_IDLE_TIMEOUT) {
        admin_logout();
        return false;
    }
    $_SESSION['admin_last_activity'] = time();
    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        http_response_code(400);
        render_page('Bad Request', '<p>Invalid or missing CSRF token. Please go back and try again.</p>'
            . '<p><a href="admin.php">Return to admin</a></p>');
        exit;
    }
}

function redirect_admin(string $query = ''): void
{
    $url = 'admin.php' . ($query !== '' ? '?' . $query : '');
    header("Location: {$url}");
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// --- Routing ---

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Login / logout are handled before the auth gate.
if ($action === 'login' && $method === 'POST') {
    handle_login();
    exit;
}

if ($action === 'logout') {
    admin_logout();
    redirect_admin();
}

if (!is_logged_in()) {
    render_login();
    exit;
}

// Authenticated actions.
switch ($action) {
    case 'add':
        if ($method === 'POST') {
            handle_add();
        } else {
            render_form();
        }
        break;
    case 'edit':
        if ($method === 'POST') {
            handle_edit();
        } else {
            render_form();
        }
        break;
    case 'status':
        handle_status();
        break;
    case 'delete':
        if ($method === 'POST') {
            handle_delete();
        } else {
            render_delete_confirm();
        }
        break;
    case 'list':
    default:
        render_list();
        break;
}

// --- Action handlers ---

function handle_login(): void
{
    global $rateLimiter, $adminPasswordHash;

    $ip = admin_ip();
    $key = 'admin_login:' . $ip;

    if ($rateLimiter->countHits($key, ADMIN_LOGIN_WINDOW_MIN) >= ADMIN_LOGIN_LIMIT) {
        render_login('Too many failed attempts. Please wait and try again later.');
        return;
    }

    // CSRF applies to the login POST too. The token is issued on the login page.
    csrf_check();

    $password = (string) ($_POST['password'] ?? '');

    if (password_verify($password, $adminPasswordHash)) {
        // Prevent session fixation.
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_last_activity'] = time();
        redirect_admin();
        return;
    }

    $rateLimiter->recordKeyHit($key);
    render_login('Incorrect password.');
}

function handle_add(): void
{
    global $db;

    csrf_check();

    $email = trim((string) ($_POST['email'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? '')) ?: null;
    $comment = trim((string) ($_POST['comment'] ?? '')) ?: null;
    $publicNote = trim((string) ($_POST['public_note'] ?? '')) ?: null;
    $tags = trim((string) ($_POST['tags'] ?? '')) ?: null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        render_form('Please enter a valid email address.');
        return;
    }

    if ($db->findRecipientByEmail($email) !== null) {
        render_form('A recipient with that email already exists.');
        return;
    }

    // Create as approved, then apply the extra fields.
    $db->createApprovedRecipient($email, $name);
    $created = $db->findRecipientByEmail($email);
    if ($created !== null && ($comment !== null || $publicNote !== null || $tags !== null)) {
        $db->updateRecipient((int) $created['id'], [
            'comment'     => $comment,
            'public_note' => $publicNote,
            'tags'        => $tags,
        ]);
    }

    redirect_admin('msg=added');
}

function handle_edit(): void
{
    global $db, $mailer;

    csrf_check();

    $id = (int) ($_POST['id'] ?? 0);
    $recipient = $db->findRecipientById($id);
    if ($recipient === null) {
        redirect_admin('msg=notfound');
        return;
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        render_form('Please enter a valid email address.', $recipient);
        return;
    }

    $newStatus = (string) ($_POST['status'] ?? $recipient['status']);
    $allowedStatuses = ['pending', 'approved', 'rejected'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        $newStatus = $recipient['status'];
    }

    $fields = [
        'email'       => $email,
        'name'        => trim((string) ($_POST['name'] ?? '')) ?: null,
        'comment'     => trim((string) ($_POST['comment'] ?? '')) ?: null,
        'public_note' => trim((string) ($_POST['public_note'] ?? '')) ?: null,
        'tags'        => trim((string) ($_POST['tags'] ?? '')) ?: null,
        'status'      => $newStatus,
    ];

    $wasApproved = $recipient['status'] === 'approved';
    $nowApproved = $newStatus === 'approved';

    try {
        $db->updateRecipient($id, $fields);
    } catch (DuplicateEmailException $e) {
        render_form('That email address is already in use by another recipient.', $recipient);
        return;
    }

    // Send the confirmation email when transitioning into approved (ADR-003 #11).
    if ($nowApproved && !$wasApproved) {
        $mailer->sendApprovalConfirmation($email, $fields['name']);
    }

    redirect_admin('msg=saved');
}

function handle_status(): void
{
    global $db, $mailer;

    csrf_check();

    $id = (int) ($_POST['id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');
    $allowedStatuses = ['pending', 'approved', 'rejected'];

    $recipient = $db->findRecipientById($id);
    if ($recipient === null || !in_array($newStatus, $allowedStatuses, true)) {
        redirect_admin('msg=notfound');
        return;
    }

    $wasApproved = $recipient['status'] === 'approved';
    $db->updateRecipient($id, ['status' => $newStatus]);

    if ($newStatus === 'approved' && !$wasApproved) {
        $mailer->sendApprovalConfirmation($recipient['email'], $recipient['name']);
    }

    redirect_admin('msg=status');
}

function handle_delete(): void
{
    global $db;

    csrf_check();

    $id = (int) ($_POST['id'] ?? 0);
    $db->deleteRecipientById($id);
    redirect_admin('msg=deleted');
}

// --- Views ---

function render_login(?string $error = null): void
{
    $errorHtml = $error ? '<p style="color:#c0392b;">' . e($error) . '</p>' : '';
    $token = e(csrf_token());

    $body = <<<HTML
<article>
    <header><strong>Admin Login</strong></header>
    {$errorHtml}
    <form method="post" action="admin.php?action=login">
        <input type="hidden" name="csrf_token" value="{$token}">
        <label for="password">
            Password
            <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
        </label>
        <button type="submit">Log in</button>
    </form>
</article>
HTML;

    render_page('Admin Login', $body);
}

function render_list(): void
{
    global $db;

    $recipients = $db->getAllRecipients();
    $token = e(csrf_token());

    $flash = flash_message();

    $rows = '';
    if ($recipients === []) {
        $rows = '<tr><td colspan="6"><em>No recipients yet.</em></td></tr>';
    } else {
        foreach ($recipients as $r) {
            $id = (int) $r['id'];
            $statusControls = status_controls($id, (string) $r['status'], $token);
            $rows .= '<tr>'
                . '<td>' . e($r['email']) . '</td>'
                . '<td>' . e($r['name']) . '</td>'
                . '<td>' . status_badge((string) $r['status']) . '</td>'
                . '<td>' . e($r['public_note']) . '</td>'
                . '<td>' . e($r['tags']) . '</td>'
                . '<td><div class="row-actions">'
                    . '<a href="admin.php?action=edit&id=' . $id . '" role="button" class="secondary outline">Edit</a> '
                    . $statusControls
                    . '<form method="get" action="admin.php">'
                        . '<input type="hidden" name="action" value="delete">'
                        . '<input type="hidden" name="id" value="' . $id . '">'
                        . '<button type="submit" class="secondary outline">Delete</button>'
                    . '</form>'
                . '</div></td>'
                . '</tr>';
        }
    }

    $body = <<<HTML
{$flash}
<div style="display:flex; justify-content:space-between; align-items:center;">
    <h2 style="margin:0;">Recipients</h2>
    <div>
        <a href="admin.php?action=add" role="button">Add recipient</a>
        <a href="admin.php?action=logout" role="button" class="secondary">Log out</a>
    </div>
</div>
<figure>
<table class="admin-table">
    <thead>
        <tr><th>Email</th><th>Name</th><th>Status</th><th>Public note</th><th>Tags</th><th>Actions</th></tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
</figure>
HTML;

    render_page('Admin', $body);
}

function status_badge(string $status): string
{
    $colors = ['approved' => '#2e7d32', 'pending' => '#b26a00', 'rejected' => '#c0392b'];
    $color = $colors[$status] ?? '#555';
    return '<span style="color:' . $color . '; font-weight:600;">' . e($status) . '</span>';
}

function status_controls(int $id, string $current, string $token): string
{
    $buttons = '';
    $targets = ['approved' => 'Approve', 'rejected' => 'Reject', 'pending' => 'Set pending'];
    foreach ($targets as $value => $label) {
        if ($value === $current) {
            continue;
        }
        $buttons .= '<form method="post" action="admin.php?action=status">'
            . '<input type="hidden" name="csrf_token" value="' . $token . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="status" value="' . $value . '">'
            . '<button type="submit" class="secondary outline">' . $label . '</button>'
            . '</form> ';
    }
    return $buttons;
}

function render_form(?string $error = null, ?array $recipient = null): void
{
    global $db;

    // On edit GET, load the recipient from the id.
    if ($recipient === null && ($_GET['action'] ?? '') === 'edit') {
        $recipient = $db->findRecipientById((int) ($_GET['id'] ?? 0));
        if ($recipient === null) {
            redirect_admin('msg=notfound');
            return;
        }
    }

    $isEdit = $recipient !== null;
    $token = e(csrf_token());
    $errorHtml = $error ? '<p style="color:#c0392b;">' . e($error) . '</p>' : '';

    $formAction = $isEdit ? 'admin.php?action=edit' : 'admin.php?action=add';
    $heading = $isEdit ? 'Edit recipient' : 'Add recipient';

    $email = e($recipient['email'] ?? '');
    $name = e($recipient['name'] ?? '');
    $comment = e($recipient['comment'] ?? '');
    $publicNote = e($recipient['public_note'] ?? '');
    $tags = e($recipient['tags'] ?? '');
    $idField = $isEdit ? '<input type="hidden" name="id" value="' . (int) $recipient['id'] . '">' : '';

    $statusField = '';
    if ($isEdit) {
        $current = (string) $recipient['status'];
        $options = '';
        foreach (['pending', 'approved', 'rejected'] as $s) {
            $sel = $s === $current ? ' selected' : '';
            $options .= '<option value="' . $s . '"' . $sel . '>' . ucfirst($s) . '</option>';
        }
        $statusField = <<<HTML
        <label for="status">Status
            <select id="status" name="status">{$options}</select>
        </label>
        <small>Changing status to "approved" sends the recipient a confirmation email.</small>
HTML;
    }

    $body = <<<HTML
<h2>{$heading}</h2>
{$errorHtml}
<form method="post" action="{$formAction}">
    <input type="hidden" name="csrf_token" value="{$token}">
    {$idField}
    <label for="email">Email
        <input type="email" id="email" name="email" value="{$email}" required>
    </label>
    <label for="name">Name
        <input type="text" id="name" name="name" value="{$name}">
    </label>
    <label for="comment">Comment (private, from registration)
        <textarea id="comment" name="comment" rows="2">{$comment}</textarea>
    </label>
    <label for="public_note">Public note (shown to list recipients)
        <textarea id="public_note" name="public_note" rows="2">{$publicNote}</textarea>
    </label>
    <label for="tags">Tags (free text)
        <input type="text" id="tags" name="tags" value="{$tags}">
    </label>
    {$statusField}
    <button type="submit">Save</button>
    <a href="admin.php" role="button" class="secondary">Cancel</a>
</form>
HTML;

    render_page($heading, $body);
}

function render_delete_confirm(): void
{
    global $db;

    $id = (int) ($_GET['id'] ?? 0);
    $recipient = $db->findRecipientById($id);
    if ($recipient === null) {
        redirect_admin('msg=notfound');
        return;
    }

    $token = e(csrf_token());
    $email = e($recipient['email']);

    $body = <<<HTML
<article>
    <header><strong>Delete recipient</strong></header>
    <p>Permanently delete <strong>{$email}</strong>? This cannot be undone.</p>
    <form method="post" action="admin.php?action=delete">
        <input type="hidden" name="csrf_token" value="{$token}">
        <input type="hidden" name="id" value="{$id}">
        <button type="submit" style="background:#c0392b; border-color:#c0392b;">Delete</button>
        <a href="admin.php" role="button" class="secondary">Cancel</a>
    </form>
</article>
HTML;

    render_page('Delete recipient', $body);
}

function flash_message(): string
{
    $msg = $_GET['msg'] ?? '';
    $map = [
        'added'    => 'Recipient added.',
        'saved'    => 'Changes saved.',
        'deleted'  => 'Recipient deleted.',
        'status'   => 'Status updated.',
        'notfound' => 'Recipient not found.',
    ];
    if (!isset($map[$msg])) {
        return '';
    }
    return '<p style="color:#2e7d32; font-weight:600;">' . e($map[$msg]) . '</p>';
}

function render_page(string $title, string $content): void
{
    global $appName, $appUrl;

    $pageTitle = e($appName) . ' — ' . e($title);
    $appNameHtml = e($appName);
    $version = e(app_version());
    $homeUrl = e($appUrl);

    echo <<<HTML
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{$pageTitle}</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <style>
        /*
         * Pico styles buttons and form controls as full-width block elements. In the
         * recipients table each action is its own <form>, so without this the buttons
         * stretch to fill the cell and overflow. Constrain everything inside an action
         * cell to compact, auto-width inline controls laid out in a flex row.
         */
        .container { max-width: 1140px; }

        .admin-table td, .admin-table th { vertical-align: middle; }

        .row-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            align-items: center;
        }

        .row-actions form { margin: 0; }

        .row-actions a[role="button"],
        .row-actions button {
            width: auto;
            display: inline-block;
            margin: 0;
            padding: 0.25rem 0.6rem;
            font-size: 0.8rem;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <main class="container">
        <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <h1 style="margin:0; color:#0159a3;">{$appNameHtml} <small style="font-size:0.6em;">admin</small></h1>
            <small><a href="https://github.com/LordOfTheSnow/phpcirclebook" target="_blank">PHPCircleBook v{$version}</a></small>
        </header>
        <p><a href="{$homeUrl}">&larr; Back to {$appNameHtml}</a></p>
        {$content}
    </main>
</body>
</html>
HTML;
}
