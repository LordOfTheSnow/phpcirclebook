# Glossary

Domain terms used throughout the mailing list application.

---

**Recipient**  
A person (identified by email address + optional display name) who is part of the mailing list. A recipient has one of three statuses: *pending*, *approved*, or *rejected*.

**Approved Recipient**  
A recipient whose registration has been confirmed by the admin. They can request the list and appear in it.

**Pending Recipient**  
A recipient who has submitted the registration form but has not yet been approved or rejected by the admin.

**Rejected Recipient**  
A recipient whose registration was explicitly denied by the admin. Their email is silently blocked from future registration attempts.

**Admin**  
The single operator of the mailing list, identified by the `ADMIN_EMAIL` in configuration. Receives approval requests and can approve or reject pending recipients.

**Registration**  
The act of submitting an email address (and optional name) via the form. Triggers an approval request email to the admin.

**Approval Token**  
A cryptographically random, single-use token stored in the database alongside a pending recipient. Embedded in the approve/reject links sent to the admin. Expires after 7 days.

**Approval Link**  
A URL sent to the admin that, when clicked, marks the pending recipient as approved and sends them a confirmation email.

**Reject Link**  
A URL sent to the admin that, when clicked, marks the pending recipient as rejected. No notification is sent to the registrant.

**List Request ("Send Me the List")**  
The action an approved recipient takes to receive the full recipient list via email. Triggered by entering their registered email in the form.

**Recipient List**  
The complete set of approved recipients, formatted as plain text with one entry per line in `Display Name <email>` format (or just the email if no name is stored).

**Unsubscribe**  
The act of an approved recipient removing themselves from the list. Requires confirmation via a dedicated page.

**Unsubscribe Token**  
An HMAC-based, stateless token derived from the recipient's email and the server's `HMAC_SECRET`. Embedded in unsubscribe links. Never expires; valid as long as the secret and the recipient record exist.

**Honeypot**  
A hidden form field invisible to human users but typically filled in by automated bots. Submissions with this field populated are silently discarded.

**Rate Limit**  
A throttle on form submissions to prevent abuse. Applied per IP address (5 requests / 15 minutes) and per email address (2 requests / 10 minutes).

**Front Controller**  
The single `index.php` entry point through which all HTTP requests are routed. Dispatches to the appropriate action handler based on a query parameter.

**Action**  
A discrete operation the front controller can perform, identified by the `?action=` query parameter. Examples: `approve`, `reject`, `unsubscribe`, `confirm-unsubscribe`.

**Confirmation Email**  
An email sent to a recipient after admin approval, informing them that their registration was accepted and they can now use the system.

**HMAC Secret**  
A server-side secret key (`HMAC_SECRET` in `.env`) used to generate and verify unsubscribe tokens. Must be kept confidential; rotating it invalidates all existing unsubscribe links.
