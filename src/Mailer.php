<?php

declare(strict_types=1);

namespace App;

final class Mailer
{
    public function __construct(
        private readonly string $appName,
        private readonly string $appUrl,
        private readonly string $adminEmail,
        private readonly TokenService $tokenService,
    ) {}

    /**
     * Send the recipient list to an approved recipient.
     */
    public function sendList(string $toEmail, array $recipients): bool
    {
        $body = "Here is the current recipient list for {$this->appName}:\n\n";

        foreach ($recipients as $r) {
            if (!empty($r['name'])) {
                $body .= "{$r['name']} <{$r['email']}>\n";
            } else {
                $body .= "{$r['email']}\n";
            }
        }

        $body .= "\n---\n";
        $body .= "Total: " . count($recipients) . " recipient(s)\n\n";
        $body .= "To unsubscribe from this list:\n";
        $body .= $this->buildUnsubscribeUrl($toEmail) . "\n";

        $subject = "[{$this->appName}] Recipient List";
        return $this->send($toEmail, $subject, $body);
    }

    /**
     * Send approval request to the admin.
     */
    public function sendApprovalRequest(string $email, ?string $name, string $token): bool
    {
        $displayName = $name ? "{$name} ({$email})" : $email;

        $body = "A new registration request for {$this->appName}:\n\n";
        $body .= "Email: {$email}\n";
        if ($name) {
            $body .= "Name: {$name}\n";
        }
        $body .= "\n";
        $body .= "Approve:\n";
        $body .= "{$this->appUrl}?action=approve&token={$token}\n\n";
        $body .= "Reject:\n";
        $body .= "{$this->appUrl}?action=reject&token={$token}\n";

        $subject = "[{$this->appName}] Approval needed: {$displayName}";
        return $this->send($this->adminEmail, $subject, $body);
    }

    /**
     * Send confirmation to a newly approved recipient.
     */
    public function sendApprovalConfirmation(string $toEmail, ?string $name): bool
    {
        $greeting = $name ? "Hi {$name}" : "Hi";

        $body = "{$greeting},\n\n";
        $body .= "Your registration for {$this->appName} has been approved.\n\n";
        $body .= "You can now visit the following page and request the recipient list at any time:\n";
        $body .= "{$this->appUrl}\n\n";
        $body .= "To unsubscribe:\n";
        $body .= $this->buildUnsubscribeUrl($toEmail) . "\n";

        $subject = "[{$this->appName}] Registration approved";
        return $this->send($toEmail, $subject, $body);
    }

    private function buildUnsubscribeUrl(string $email): string
    {
        $token = $this->tokenService->generateUnsubscribeToken($email);
        return "{$this->appUrl}?action=unsubscribe&email=" . urlencode($email) . "&token={$token}";
    }

    private function send(string $to, string $subject, string $body): bool
    {
        $headers = [
            "From: {$this->appName} <{$this->adminEmail}>",
            "Reply-To: {$this->adminEmail}",
            "Content-Type: text/plain; charset=UTF-8",
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
