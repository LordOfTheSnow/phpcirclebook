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
        $body = __('mail.list_intro', ['appName' => $this->appName]) . "\n\n";

        foreach ($recipients as $r) {
            if (!empty($r['name'])) {
                $body .= "{$r['name']} <{$r['email']}>\n";
            } else {
                $body .= "{$r['email']}\n";
            }
        }

        $body .= "\n---\n";
        $body .= __('mail.list_total', ['count' => count($recipients)]) . "\n\n";
        $body .= __('mail.list_unsubscribe') . "\n";
        $body .= $this->buildUnsubscribeUrl($toEmail) . "\n";

        $subject = __('mail.list_subject', ['appName' => $this->appName]);
        return $this->send($toEmail, $subject, $body);
    }

    /**
     * Send approval request to the admin.
     */
    public function sendApprovalRequest(string $email, ?string $name, string $token): bool
    {
        $displayName = $name ? "{$name} ({$email})" : $email;

        $body = __('mail.approval_intro', ['appName' => $this->appName]) . "\n\n";
        $body .= __('mail.approval_email', ['email' => $email]) . "\n";
        if ($name) {
            $body .= __('mail.approval_name', ['name' => $name]) . "\n";
        }
        $body .= "\n";
        $body .= __('mail.approval_approve') . "\n";
        $body .= "{$this->appUrl}?action=approve&token={$token}\n\n";
        $body .= __('mail.approval_reject') . "\n";
        $body .= "{$this->appUrl}?action=reject&token={$token}\n";

        $subject = __('mail.approval_subject', ['appName' => $this->appName, 'displayName' => $displayName]);
        return $this->send($this->adminEmail, $subject, $body);
    }

    /**
     * Send confirmation to a newly approved recipient.
     */
    public function sendApprovalConfirmation(string $toEmail, ?string $name): bool
    {
        $greeting = $name
            ? __('mail.confirm_greeting', ['name' => $name])
            : __('mail.confirm_greeting_anon');

        $body = "{$greeting},\n\n";
        $body .= __('mail.confirm_body', ['appName' => $this->appName]) . "\n\n";
        $body .= __('mail.confirm_instructions') . "\n";
        $body .= "{$this->appUrl}\n\n";
        $body .= __('mail.confirm_unsub') . "\n";
        $body .= $this->buildUnsubscribeUrl($toEmail) . "\n";

        $subject = __('mail.confirm_subject', ['appName' => $this->appName]);
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
