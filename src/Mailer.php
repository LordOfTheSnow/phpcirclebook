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
                $line = "{$r['name']} <{$r['email']}>";
            } else {
                $line = $r['email'];
            }
            // Keep this list clean (name + email only) so it can be copied
            // straight into an email client. Public notes go in a separate
            // section below.
            $body .= $line . "\n";
        }

        // Public notes as a separate list, so the recipient list above stays a
        // clean, copy-pasteable set of addresses. Only recipients that actually
        // have a note appear here.
        $noted = array_filter($recipients, static fn ($r) => !empty($r['public_note']));
        if ($noted !== []) {
            $body .= "\n---\n\n";
            $body .= __('mail.list_notes_heading') . "\n\n";
            foreach ($noted as $r) {
                $name = !empty($r['name']) ? $r['name'] : $r['email'];
                $body .= "{$name} ({$r['email']}): {$r['public_note']}\n";
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
        fputcsv($handle, [
            'Email',
            'Name',
            __('mail.csv_header_public_note'),
            __('mail.csv_header_tags'),
            __('mail.csv_header_registered'),
        ]);

        foreach ($recipients as $r) {
            $registeredAt = '';
            if (!empty($r['created_at'])) {
                $ts = strtotime($r['created_at']);
                $registeredAt = $ts !== false ? formatDate($ts) : $r['created_at'];
            }
            fputcsv($handle, [
                $this->escapeCsvFormula($r['email']),
                $this->escapeCsvFormula($r['name'] ?? ''),
                $this->escapeCsvFormula($r['public_note'] ?? ''),
                $this->escapeCsvFormula($r['tags'] ?? ''),
                $registeredAt,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Neutralise CSV/spreadsheet formula injection.
     *
     * Recipient fields (name, public_note, tags) can contain arbitrary
     * registrant-supplied text, and this CSV is opened by other recipients in
     * Excel/Sheets. A value starting with =, +, -, @, tab, or CR is interpreted
     * there as a formula (e.g. =HYPERLINK(...) or a legacy DDE payload), so we
     * prefix such values with a leading apostrophe, which spreadsheet
     * applications render as literal text instead of evaluating it.
     */
    private function escapeCsvFormula(string $value): string
    {
        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Send an email with a file attachment using multipart/mixed MIME.
     */
    private function sendWithAttachment(string $to, string $subject, string $body, string $attachmentContent, string $attachmentFilename): bool
    {
        $body = $this->appendFooter($body);
        $subject = $this->encodeHeaderValue($subject);
        $fromName = $this->encodeHeaderValue($this->appName);
        $boundary = md5(uniqid((string) time()));

        $headers = [
            "From: {$fromName} <{$this->adminEmail}>",
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
        $body = $this->appendFooter($body);
        $subject = $this->encodeHeaderValue($subject);
        $fromName = $this->encodeHeaderValue($this->appName);
        $headers = [
            "From: {$fromName} <{$this->adminEmail}>",
            "Reply-To: {$this->adminEmail}",
            "Content-Type: text/plain; charset=UTF-8",
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Append the standard footer (a separator line plus APP_NAME wrapping the
     * configured APP_URL) to a message body. Applied at the lowest level so it
     * covers every outgoing mail (list, approval request, confirmation)
     * regardless of caller.
     */
    private function appendFooter(string $body): string
    {
        $footer = __('mail.footer', ['appName' => $this->appName, 'url' => $this->appUrl]);

        return $body . "\n---\n" . $footer . "\n";
    }

    /**
     * Sanitise and MIME-encode a value for use in a mail header (Subject, or a
     * From display name).
     *
     * Strips CR/LF first to neutralise header injection: the value is placed in
     * the mail header block by mail(), so any CR/LF folded in from user-supplied
     * data (e.g. a registrant's name) could otherwise inject additional headers
     * (Bcc/Cc) or body content. Header values are single-line by definition, so
     * every CR and LF is removed outright.
     *
     * Then RFC 2047-encodes the result (mb_encode_mimeheader, base64 scheme).
     * Some receiving MTAs (observed: secure-mailgate.com) reject a Subject
     * containing raw non-ASCII bytes outright ("550 Subject contains invalid
     * characters"), even though headers are otherwise 8bit-clean; encoding
     * avoids relying on the recipient's tolerance for unencoded UTF-8 headers.
     * Pure-ASCII values pass through unchanged.
     */
    private function encodeHeaderValue(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);

        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }
}
