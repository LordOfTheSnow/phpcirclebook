# ADR-001: PHP Mailing List Application

**Status:** Accepted  
**Date:** 2026-08-24  
**Context:** Build a self-contained PHP application that manages a list of email recipients with admin-gated registration and self-service list retrieval.

---

## Summary

A single-page PHP application where:
- Approved recipients can request the full recipient list be emailed to them.
- New visitors can register their email; the admin is notified and must approve them via a link.
- Rejected addresses are silently blocked from re-registering.

---

## Decisions

### 1. Storage: SQLite
A single-file database. Zero external services, easy to back up, sufficient concurrency for this use case.

### 2. Email Sending: PHP `mail()`
Relies on the host's configured MTA (sendmail/postfix). Keeps dependencies minimal. Can be swapped to PHPMailer + SMTP later without architectural changes.

### 3. Admin Approval Flow: Email Links
The admin receives an email with **approve** and **reject** links containing secure tokens. No admin panel or login system.

### 4. Post-Approval: Confirmation Email
When the admin approves a recipient, that person receives a short confirmation email informing them they can now use the system.

### 5. List Format: Plain Text with Optional Names
The emailed list is plain text, one entry per line in RFC 5322 display-name format:  
`Joerg Fenin <joerg@fenin.de>` (or just `joerg@fenin.de` if no name was provided).

### 6. Name Collection: Optional Field at Sign-Up
The registration form has an optional "Name" field. Stored alongside the email.

### 7. Authentication for "Send Me the List": None
Entering a known, approved email address is sufficient. The list is sent to *that* address (not displayed on screen), so no information leaks to a third party.

### 8. Bot Protection: Honeypot + Rate Limiting
- A hidden form field that bots tend to fill in (honeypot).
- Per-IP rate limit: 5 requests / 15 minutes.
- Per-email rate limit: 2 requests / 10 minutes.
- No external CAPTCHA service.

### 9. Configuration: `.env` File (phpdotenv)
12-factor style configuration. Variables:
```
APP_NAME="My Mailing List"
APP_URL="https://list.example.com"
ADMIN_EMAIL="admin@example.com"
HMAC_SECRET="a-long-random-string"
DB_PATH="data/mailinglist.db"
```

### 10. Routing: Single `index.php` Front Controller
All requests route through one file. Actions dispatched via query parameter (`?action=...`). Supporting logic lives in included files.

### 11. Dependency Management: Composer
Manages phpdotenv (and future PHPMailer). Provides PSR-4 autoloading.

### 12. Rejection: Silent Deny
Admin gets both approve and reject links. Rejection removes the pending request silently — the registrant is never notified.

### 13. Re-Registration After Rejection: Silently Blocked
A rejected email that re-submits sees the same generic "thank you" message, but no admin email is sent. Prevents enumeration and admin spam.

### 14. User Feedback Messages
- **Known/approved recipient:** "The list has been sent to your email address."
- **Unknown or rejected address:** Generic "Thank you, if your address is eligible you'll receive an email shortly." (identical for new and blocked — prevents enumeration).

### 15. Unsubscribe: With Confirmation Page
Every list email includes an unsubscribe link. The link leads to a confirmation page ("Are you sure?") before removal. Prevents accidental unsubscribes from email link pre-fetchers.

### 16. PHP Version: 8.2+
Leverages modern type system, readonly properties, enums, and DNF types.

### 17. Approval Token: Random, DB-Stored, 7-Day Expiry
Generated with `random_bytes(32)`, stored alongside the pending recipient. Expires after 7 days. Invalidated on use.

### 18. Pending Re-Submission: Allowed
A pending recipient can re-submit at any time. This generates a fresh token and sends a new approval email to the admin (old token is invalidated). Rate limiting prevents abuse.

### 19. Unsubscribe Token: HMAC-Based (Stateless)
Derived from the recipient's email + a server-side secret (`HMAC_SECRET`). Always valid, no DB storage, no expiry. Appropriate because unsubscribing is a low-risk, self-directed action.

### 20. UI: Pico CSS via CDN
Classless CSS framework. Semantic HTML automatically looks polished. Single `<link>` tag, no build step.

### 21. App Name: Configurable via `APP_NAME` in `.env`
Displayed in page titles, headings, and email subjects.

---

## Consequences

- **Simple deployment:** Drop files on any PHP 8.2+ host with a working MTA and writable directory for SQLite.
- **No external services:** No database server, no CAPTCHA API, no mail API.
- **Future upgradability:** PHPMailer can replace `mail()` by adding one Composer package and updating the send function.
- **Limitations:** Rate limiting is in-process (SQLite-backed); won't scale to a distributed setup. Acceptable for a mailing list tool.
