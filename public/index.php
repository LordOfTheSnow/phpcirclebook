<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

use App\Database;
use App\Mailer;
use App\RateLimiter;
use App\SidebarContent;
use App\TokenService;
use App\Translator;
use Dotenv\Dotenv;

// --- Bootstrap ---

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
$dotenv->required(['APP_NAME', 'APP_URL', 'ADMIN_EMAIL', 'HMAC_SECRET', 'DB_PATH', 'APP_LOCALE']);

$appName = $_ENV['APP_NAME'];
$appUrl = rtrim($_ENV['APP_URL'], '/');
$adminEmail = $_ENV['ADMIN_EMAIL'];
$hmacSecret = $_ENV['HMAC_SECRET'];
$dbPath = dirname(__DIR__) . '/' . $_ENV['DB_PATH'];
$appLocale = $_ENV['APP_LOCALE'];
$appDescription = $_ENV['APP_DESCRIPTION'] ?? '';
$appFooter = $_ENV['APP_FOOTER'] ?? '';
$sidebarSide = strtolower(trim($_ENV['SIDEBAR_SIDE'] ?? '')) === 'left' ? 'left' : 'right';
$appLogo = trim($_ENV['APP_LOGO'] ?? '');

// Initialise translator
Translator::init($appLocale, dirname(__DIR__) . '/lang');

$db = new Database($dbPath);
$tokenService = new TokenService($hmacSecret);
$rateLimiter = new RateLimiter($db);
$mailer = new Mailer($appName, $appUrl, $adminEmail, $tokenService);

// Cleanup old rate limit entries occasionally (1 in 50 chance per request)
if (random_int(1, 50) === 1) {
    $rateLimiter->cleanup();
}

// --- Routing ---

$action = $_GET['action'] ?? 'home';

match ($action) {
    'home' => handleHome(),
    'submit' => handleSubmit(),
    'approve' => handleApprove(),
    'reject' => handleReject(),
    'unsubscribe' => handleUnsubscribe(),
    'confirm-unsubscribe' => handleConfirmUnsubscribe(),
    default => handleHome(),
};

// --- Action Handlers ---

function handleHome(): void
{
    $contentDir = dirname(__DIR__) . '/content';
    $renderer = new SidebarContent();

    // Each card renders only if its content file exists and is non-empty.
    $cards = [];

    $eventsHtml = $renderer->renderFile($contentDir . '/events.md');
    if ($eventsHtml !== '') {
        $cards[] = ['title' => __('sidebar.events_title'), 'body' => $eventsHtml];
    }

    $linksHtml = $renderer->renderFile($contentDir . '/links.md');
    if ($linksHtml !== '') {
        $cards[] = ['title' => __('sidebar.links_title'), 'body' => $linksHtml];
    }

    renderPage('form.php', ['sidebarCards' => $cards]);
}

function handleSubmit(): void
{
    global $db, $mailer, $rateLimiter, $tokenService, $appUrl;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect($appUrl);
        return;
    }

    // Honeypot check
    if (!empty($_POST['website'] ?? '')) {
        // Bot detected — show generic message
        renderMessage(__('message.generic_thanks'));
        return;
    }

    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '') ?: null;
    $comment = mb_substr(trim($_POST['comment'] ?? ''), 0, COMMENT_MAX_LENGTH) ?: null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        renderMessage(__('message.invalid_email'));
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Rate limiting
    if ($rateLimiter->isIpLimited($ip) || $rateLimiter->isEmailLimited($email)) {
        renderMessage(__('message.rate_limited'));
        return;
    }

    $rateLimiter->recordHit($ip, $email);

    $recipient = $db->findRecipientByEmail($email);

    if ($recipient === null) {
        // New registration
        $token = $tokenService->generateApprovalToken();
        $expires = $tokenService->approvalTokenExpiry();
        $db->createRecipient($email, $name, $comment, $token, $expires);
        $mailer->sendApprovalRequest($email, $name, $comment, $token);
        renderMessage(__('message.generic_thanks'));
        return;
    }

    match ($recipient['status']) {
        'approved' => handleSendList($email),
        'rejected' => renderMessage(__('message.generic_thanks')),
        'pending' => handleResubmit($email, $name, $comment, $recipient),
        default => renderMessage(__('message.generic_thanks')),
    };
}

function handleSendList(string $email): void
{
    global $db, $mailer;

    $recipients = $db->getApprovedRecipients();
    $mailer->sendList($email, $recipients);
    renderMessage(__('message.list_sent'));
}

function handleResubmit(string $email, ?string $name, ?string $comment, array $existing): void
{
    global $db, $mailer, $tokenService;

    // Generate fresh token and resend approval request
    $token = $tokenService->generateApprovalToken();
    $expires = $tokenService->approvalTokenExpiry();
    $db->updateRecipientToken($email, $token, $expires);
    $mailer->sendApprovalRequest($email, $name ?? $existing['name'], $comment ?? ($existing['comment'] ?? null), $token);
    renderMessage(__('message.generic_thanks'));
}

function handleApprove(): void
{
    global $db, $mailer;

    $token = $_GET['token'] ?? '';
    if ($token === '') {
        renderMessage(__('message.invalid_link'));
        return;
    }

    $recipient = $db->findRecipientByToken($token);
    if ($recipient === null) {
        renderMessage(__('message.invalid_link'));
        return;
    }

    $db->approveRecipient((int) $recipient['id']);
    $mailer->sendApprovalConfirmation($recipient['email'], $recipient['name']);
    renderMessage(__('message.approved', ['email' => $recipient['email']]));
}

function handleReject(): void
{
    global $db;

    $token = $_GET['token'] ?? '';
    if ($token === '') {
        renderMessage(__('message.invalid_link'));
        return;
    }

    $recipient = $db->findRecipientByToken($token);
    if ($recipient === null) {
        renderMessage(__('message.invalid_link'));
        return;
    }

    $db->rejectRecipient((int) $recipient['id']);
    renderMessage(__('message.rejected', ['email' => $recipient['email']]));
}

function handleUnsubscribe(): void
{
    global $tokenService;

    $email = $_GET['email'] ?? '';
    $token = $_GET['token'] ?? '';

    if ($email === '' || $token === '' || !$tokenService->verifyUnsubscribeToken($email, $token)) {
        renderMessage(__('message.invalid_unsub'));
        return;
    }

    renderPage('unsubscribe.php', ['email' => $email, 'token' => $token]);
}

function handleConfirmUnsubscribe(): void
{
    global $db, $tokenService, $appUrl;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect($appUrl);
        return;
    }

    $email = $_POST['email'] ?? '';
    $token = $_POST['token'] ?? '';

    if ($email === '' || $token === '' || !$tokenService->verifyUnsubscribeToken($email, $token)) {
        renderMessage(__('message.invalid_unsub'));
        return;
    }

    $db->deleteRecipientByEmail($email);
    renderMessage(__('message.unsubscribed'));
}

// --- Helpers ---

function renderPage(string $template, array $vars = []): void
{
    global $appName, $appUrl, $appDescription, $appFooter, $adminEmail, $sidebarSide, $appLogo;

    $vars['appName'] = $appName;
    $vars['appUrl'] = $appUrl;
    $vars['appDescription'] = $appDescription;
    $vars['appFooter'] = $appFooter;
    $vars['adminEmail'] = $adminEmail;
    $vars['sidebarSide'] = $sidebarSide;
    $vars['appLogo'] = $appLogo;
    // Pages other than the form don't provide sidebar cards; default to none so
    // they render single-column.
    $vars['sidebarCards'] = $vars['sidebarCards'] ?? [];
    extract($vars);

    ob_start();
    require __DIR__ . '/../templates/' . $template;
    $content = ob_get_clean();

    require __DIR__ . '/../templates/layout.php';
}

function renderMessage(string $message): void
{
    global $appName, $appUrl;

    $vars = ['message' => $message, 'appName' => $appName, 'appUrl' => $appUrl];
    extract($vars);

    ob_start();
    require __DIR__ . '/../templates/message.php';
    $content = ob_get_clean();

    require __DIR__ . '/../templates/layout.php';
}

function redirect(string $url): void
{
    header("Location: {$url}");
    exit;
}
