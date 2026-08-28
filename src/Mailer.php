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

        // Build CSV attachment
        $csv = $this->buildRecipientCsv($recipients);

        $subject = __('mail.list_subject', ['appName' => $this->appName]);
        return $this->sendWithAttachment($toEmail, $subject, $body, $csv, 'members.csv');
    }

    /**
     * Send approval request to the admin.
     */
    public function sendApprovalRequest(string $email, ?string $name, ?string $comment, string $token): bool
    {
        $displayName = $name ? "{$name} ({$email})" : $email;

        $body = __('mail.approval_intro', ['appName' => $this->appName]) . "\n\n";
        $body .= __('mail.approval_email', ['email' => $email]) . "\n";
        if ($name) {
            $body .= __('mail.approval_name', ['name' => $name]) . "\n";
        }
        if ($comment !== null && $comment !== '') {
            $body .= __('mail.approval_comment', ['comment' => $comment]) . "\n";
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

    /**
     * Build a CSV string from the recipients list.
     */
    private function buildRecipientCsv(array $recipients): string
    {
        $handle = fopen('php://memory', 'r+');
        fputcsv($handle, ['Email', 'Name', __('mail.csv_header_registered')]);

        foreach ($recipients as $r) {
            $registeredAt = '';
            if (!empty($r['created_at'])) {
                $ts = strtotime($r['created_at']);
                $registeredAt = $ts !== false ? formatDate($ts) : $r['created_at'];
            }
            fputcsv($handle, [$r['email'], $r['name'] ?? '', $registeredAt]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Send an email with a file attachment using multipart/mixed MIME.
     */
    private function sendWithAttachment(string $to, string $subject, string $body, string $attachmentContent, string $attachmentFilename): bool
    {
        $boundary = md5(uniqid((string) time()));

        $headers = [
            "From: {$this->appName} <{$this->adminEmail}>",
            "Reply-To: {$this->adminEmail}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
        ];

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";

        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/csv; charset=UTF-8; name=\"{$attachmentFilename}\"\r\n";
        $message .= "Content-Disposition: attachment; filename=\"{$attachmentFilename}\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($attachmentContent)) . "\r\n";

        $message .= "--{$boundary}--\r\n";

        return mail($to, $subject, $message, implode("\r\n", $headers));
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
