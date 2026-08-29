# ADR-003: Admin Tool for Recipient Management

**Status:** Accepted  
**Date:** 2026-08-29  
**Context:** The recipient database can currently only be changed through the email token flow (approve/reject) or by hand-editing the SQLite file off-server. An operator needs a safe way to read, add, edit, and remove recipients, both on shell-capable hosts and on FTP-only shared hosting where downloading and re-uploading the database risks losing concurrent writes.

---

## Summary

Provide two front-ends over one shared data layer for managing recipients:

- A **web admin page** (`public/admin.php`), password-protected, for hosts without shell access.
- A **CLI tool** (`bin/admin.php`) for shell/cron use.

Both operate on the same `recipients` table through new methods on `App\Database`, so no query logic is duplicated. This iteration also adds two new recipient columns (`public_note`, `tags`) and the password-provisioning helper, without yet publishing `public_note` to list recipients.

---

## Decisions

### 1. Two Interfaces, One Data Layer
A web admin controller and a CLI tool are both provided. All recipient reads and writes go through shared methods on `App\Database`; only bootstrap code is duplicated between entry points. Rationale: the project supports both shell-capable and FTP-only hosts, and the web tool is what actually solves the "no shell, don't want to round-trip the DB file" problem.

### 2. Web Entry Point: Standalone `public/admin.php`
The web admin is a separate front controller with its own bootstrap, login gate, and small router, rather than new actions folded into `public/index.php`. Keeps the public request path unchanged (no risk of a router typo exposing admin actions) and isolates the auth gate in one file. Stays procedural to match the existing code style.

### 3. Web Authentication: Shared Password + Session Login
A single shared password gates the web admin. On successful login a PHP session is established; a logout action clears it. No user accounts, no roles — this is a single-operator tool. Full user accounts were rejected as overkill.

When `ADMIN_PASSWORD_HASH` is missing or empty, `public/admin.php` does **not** raise a fatal error. It responds with HTTP 503 and a plain "Admin tool not configured" page pointing the operator at `php bin/hash-password.php`. `ADMIN_PASSWORD_HASH` is therefore deliberately excluded from the dotenv `required()` assertion list (the other core variables remain required), so an unconfigured admin degrades gracefully instead of throwing an uncaught `ValidationException` with a stack trace.

### 4. Password Storage: bcrypt Hash in `.env`
The password is stored as a bcrypt (`$2y$`) hash in `ADMIN_PASSWORD_HASH` and verified with PHP's `password_verify()`, which auto-detects the algorithm from the hash prefix. bcrypt was chosen over Argon2id for portability (widely available in external tools and all PHP builds) and over raw SHA-256, which is unsalted, fast, and unsuitable for password storage.

### 5. Password Provisioning: `bin/hash-password.php`
A CLI helper reads the password from a hidden stdin prompt (never via `$argv`, so it stays out of shell history) and prints the ready-to-paste `ADMIN_PASSWORD_HASH=` line. The README additionally documents portable ways to generate a compatible bcrypt hash (`htpasswd -bnBC`, Python `bcrypt`, Node) for operators who prefer their own tooling or have no server shell.

### 6. Session Hardening
The admin session sets `cookie_httponly` and `cookie_samesite=Lax` unconditionally. The `cookie_secure` flag is **not** hardcoded: it is derived per request from whether the connection is actually HTTPS (checking `$_SERVER['HTTPS']`, port 443, and the `X-Forwarded-Proto` header for TLS-terminating proxies). On the production HTTPS host this resolves to secure; on a plain-HTTP local dev server (`composer start` over `http://localhost`) it resolves to not-secure, so the session cookie is still sent and the admin is testable locally without certificates. An optional `.env` override (`ADMIN_FORCE_SECURE_COOKIE`) forces the secure flag on for hosts behind a proxy where auto-detection sees only plain HTTP. An idle timeout of ~30 minutes auto-logs-out inactive sessions.

Rationale: a flat `cookie_secure=1` would make the browser withhold the session cookie over local HTTP, locking the operator out of local testing. Auto-detection keeps production fully hardened (production is HTTPS) while allowing certificate-free local testing, and cannot silently ship insecure to production because production requests are HTTPS. The alternative of local HTTPS via mkcert/Caddy was considered but rejected as unnecessary setup for this project.

### 7. Login Throttling: Reuse `RateLimiter`
Failed admin logins are throttled by IP using the existing DB-backed `App\RateLimiter`, rather than introducing a new mechanism.

### 8. CSRF Protection on Admin POSTs
Every state-changing admin POST (login, logout, add, edit, status change, delete) carries a per-session CSRF token verified on submission. This is new to the app — the public form relies on a honeypot plus HMAC tokens instead — and is required because the admin is an authenticated, state-changing surface.

### 9. Serve From Behind `public/`
The admin tool, the SQLite database, and `.env` must never be directly reachable over HTTP. The document root must be `public/` (or, on FTP-only hosts, the root `.htaccess` fallback that blocks the non-public directories). This is called out explicitly in the README.

### 10. Editable Field Set
The admin may: list all recipients (any status); add a recipient (created directly as *approved*, like the import tool); edit `name`, `email`, `comment`, `public_note`, and `tags`; change `status`; and delete. The web tool requires a confirmation step before delete. `token`, `token_expires_at`, and `created_at` are never editable; `updated_at` is set automatically on every change. Editing `email` (a `UNIQUE COLLATE NOCASE` column) surfaces uniqueness collisions as a catchable exception rather than a fatal error.

### 11. Manual Approval Sends the Confirmation Email
Setting a recipient's `status` to *approved* through either front-end sends the same approval-confirmation email as the token flow, keeping behaviour consistent across all approval paths. The CLI attempts the send and prints a warning if mail is not configured (common on shared-host CLI), rather than silently skipping it.

### 12. CLI Shape: One Script, Subcommands, Flag-Driven
`bin/admin.php` is a single entry point with subcommands (`list`, `add`, `edit`, `status`, `delete`) and a help block, matching the "one tool" model. Editing is non-interactive and flag-driven (`--name=`, `--email=`, `--comment=`, `--public-note=`, `--tags=`, `--status=`); omitted flags leave the corresponding field unchanged. Recipients are addressed by `id`, which is stable even when the email is edited.

### 13. New `Database` Methods
Added: `getAllRecipients()`, `findRecipientById()`, `updateRecipient(int $id, array $fields)` (a guarded partial update over the allowed set, auto-setting `updated_at` and rejecting unknown fields), and `deleteRecipientById()`. The existing `createApprovedRecipient()` and `approveRecipient()` are reused. All admin operations key on `id`.

### 14. New Column: `public_note` (Published in the List)
`public_note TEXT DEFAULT NULL` is a publicly visible annotation about a recipient (e.g. "left the school one year before graduation"), distinct from the private `comment` a registrant leaves for the admin. It is editable in the admin tool and is included in the list output sent to approved recipients: `getApprovedRecipients()` selects it, the plain-text member list appends it in brackets after each entry (`Name <email> (public note)`), and it appears as its own column in the CSV attachment.

**Supersedes an earlier decision.** This column was originally introduced as "deferred publish" — editable but intentionally kept out of the list output so operators could populate it privately first. That was later reversed in favour of feature completeness: `public_note` is now published wherever the recipient list is shared. Operators should treat anything entered in `public_note` as visible to every approved member who requests the list.

### 15. New Column: `tags` (Free-Text Taxonomy)
`tags TEXT DEFAULT NULL` is a single free-text taxonomy column. A relational many-to-many tag model and a JSON column were both considered and rejected as too heavy for this single-table, no-build-step app. `tags` appears as a column in the CSV attachment of the shared list but is intentionally kept out of the plain-text email body (which shows only name, email, and public note), keeping the readable list uncluttered while still exporting tags for anyone who wants to work with the data.

### 16. Schema Migration Style
Both new columns are added through the existing idempotent `ALTER TABLE ... ADD COLUMN` pattern in `Database::migrate()`, consistent with how the `comment` column was introduced.

### 17. i18n Scope: Admin Tool Is English-Only
The web admin UI and the CLI output are not translated. ADR-002's translatability requirement targets visitor-facing text; the admin panel is a single-operator tool. Translated `public_note` content is data typed by the operator, not UI chrome.

---

## Consequences

- **Two hosting models covered:** shell hosts use the CLI; FTP-only hosts use the password-protected web page without ever round-tripping the SQLite file.
- **First authenticated surface:** the app now has sessions, a password secret, CSRF, and login throttling — concentrated in `public/admin.php` and kept out of the public front controller.
- **New deployment requirement:** an `ADMIN_PASSWORD_HASH` must be set in `.env` to use the web admin, and the app must be served from behind `public/` over HTTPS for the admin tool to be safe. When the hash is absent the admin page reports "not configured" (HTTP 503) rather than failing fatally. An optional `ADMIN_FORCE_SECURE_COOKIE` may be set on hosts behind a TLS-terminating proxy where HTTPS auto-detection would otherwise see plain HTTP.
- **Certificate-free local testing:** because `cookie_secure` is auto-detected, the admin tool works over `http://localhost` (via `composer start`) with no local TLS setup.
- **Schema grows by two columns** that existing databases pick up automatically via the idempotent migration. Both are shared with approved recipients: `public_note` appears in the plain-text list (in brackets) and the CSV, and `tags` appears in the CSV only.
- **Consistent approval behaviour:** approving via the token link, the web tool, or the CLI all send the same confirmation email.
- **Limitations:** single shared password (no per-admin accounts or audit trail); `tags` is unvalidated free text, so a future move to a constrained vocabulary or relational tags would be a follow-up migration.
