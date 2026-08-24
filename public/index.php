<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Mailer;
use App\RateLimiter;
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
    renderPage('form.php');
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
        renderMessage("Thank you. If your address is eligible you'll receive an email shortly.");
        return;
    }

    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '') ?: null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        renderMessage("Please enter a valid email address.");
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Rate limiting
    if ($rateLimiter->isIpLimited($ip) || $rateLimiter->isEmailLimited($email)) {
        renderMessage("Too many requests. Please try again later.");
        return;
    }

    $rateLimiter->recordHit($ip, $email);

    $recipient = $db->findRecipientByEmail($email);

    if ($recipient === null) {
        // New registration
        $token = $tokenService->generateApprovalToken();
        $expires = $tokenService->approvalTokenExpiry();
        $db->createRecipient($email, $name, $token, $expires);
        $mailer->sendApprovalRequest($email, $name, $token);
        renderMessage("Thank you. If your address is eligible you'll receive an email shortly.");
        return;
    }

    match ($recipient['status']) {
        'approved' => handleSendList($email),
        'rejected' => renderMessage("Thank you. If your address is eligible you'll receive an email shortly."),
        'pending' => handleResubmit($email, $name, $recipient),
        default => renderMessage("Thank you. If your address is eligible you'll receive an email shortly."),
    };
}

function handleSendList(string $email): void
{
    global $db, $mailer;

    $recipients = $db->getApprovedRecipients();
    $mailer->sendList($email, $recipients);
    renderMessage("The list has been sent to your email address.");
}

function handleResubmit(string $email, ?string $name, array $existing): void
{
    global $db, $mailer, $tokenService;

    // Generate fresh token and resend approval request
    $token = $tokenService->generateApprovalToken();
    $expires = $tokenService->approvalTokenExpiry();
    $db->updateRecipientToken($email, $token, $expires);
    $mailer->sendApprovalRequest($email, $name ?? $existing['name'], $token);
    renderMessage("Thank you. If your address is eligible you'll receive an email shortly.");
}

function handleApprove(): void
{
    global $db, $mailer;

    $token = $_GET['token'] ?? '';
    if ($token === '') {
        renderMessage("Invalid or expired link.");
        return;
    }

    $recipient = $db->findRecipientByToken($token);
    if ($recipient === null) {
        renderMessage("Invalid or expired link.");
        return;
    }

    $db->approveRecipient((int) $recipient['id']);
    $mailer->sendApprovalConfirmation($recipient['email'], $recipient['name']);
    renderMessage("Approved! {$recipient['email']} has been added to the list and notified.");
}

function handleReject(): void
{
    global $db;

    $token = $_GET['token'] ?? '';
    if ($token === '') {
        renderMessage("Invalid or expired link.");
        return;
    }

    $recipient = $db->findRecipientByToken($token);
    if ($recipient === null) {
        renderMessage("Invalid or expired link.");
        return;
    }

    $db->rejectRecipient((int) $recipient['id']);
    renderMessage("Rejected. {$recipient['email']} has been silently denied.");
}

function handleUnsubscribe(): void
{
    global $tokenService;

    $email = $_GET['email'] ?? '';
    $token = $_GET['token'] ?? '';

    if ($email === '' || $token === '' || !$tokenService->verifyUnsubscribeToken($email, $token)) {
        renderMessage("Invalid unsubscribe link.");
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
        renderMessage("Invalid unsubscribe link.");
        return;
    }

    $db->deleteRecipientByEmail($email);
    renderMessage("You have been unsubscribed. You can re-register at any time.");
}

// --- Helpers ---

function renderPage(string $template, array $vars = []): void
{
    global $appName, $appUrl;

    $vars['appName'] = $appName;
    $vars['appUrl'] = $appUrl;
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
