# Glossary

Domain terms used throughout PHPCircleBook.

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

---

## Internationalisation (i18n)

**Locale**  
A combination of language and region expressed as an ICU locale code (e.g. `de_DE`, `en_US`). Configured per instance via `APP_LOCALE` in `.env`. Determines both the translation file used and the formatting rules for dates and numbers.

**Language File**  
A PHP file in the `lang/` directory (e.g. `lang/de.php`) that returns an associative array mapping dot-namespaced translation keys to translated strings. One file per locale.

**Translation Key**  
A dot-namespaced identifier used to look up a translated string (e.g. `form.submit`, `mail.list_subject`). Grouped by context: `form.*` (UI labels), `message.*` (controller messages), `mail.*` (email content).

**Fallback Locale**  
English (`en`). When a key is missing from the configured locale's language file, the English value is used instead. Ensures the application always renders meaningful text.

**Translator**  
A PHP class (`App\Translator`) responsible for loading language files, resolving fallback, and interpolating placeholders. Exposed globally via the `__()` helper function.

**`__()` (Double-Underscore Function)**  
A global helper function that translates a key and optionally interpolates placeholder values. Signature: `__('key', ['placeholder' => 'value'])`. Delegates to the `Translator` singleton.

**Placeholder Token**  
A `{name}`-style token inside a translation string that gets replaced at runtime with a dynamic value using `strtr`. Example: `'Total: {count} recipient(s)'`.

**IntlDateFormatter / NumberFormatter**  
PHP `intl` extension classes used for locale-aware date and number formatting. Configured with the full `APP_LOCALE` value to respect regional conventions (e.g. `25.08.2026` in German vs `08/25/2026` in American English).

---

## Admin Tool

**Admin Tool**  
The pair of interfaces for managing recipients directly: a password-protected web page (`public/admin.php`) and a command-line tool (`bin/admin.php`). Both operate on the same `recipients` table through shared `App\Database` methods.

**Web Admin**  
The standalone `public/admin.php` front controller. Has its own bootstrap, login gate, and router, separate from the public `index.php`. Reached at `/admin.php` and intended for hosts without shell access.

**CLI Admin**  
The `bin/admin.php` command-line tool with `list`, `add`, `edit`, `status`, and `delete` subcommands. Non-interactive and flag-driven; recipients are addressed by their `id`. Intended for shell or cron use.

**Admin Password**  
A single shared secret that gates the web admin. Stored as a bcrypt hash in `ADMIN_PASSWORD_HASH` in `.env` and verified with `password_verify()`. There is one operator; no user accounts or roles.

**`ADMIN_PASSWORD_HASH`**  
The `.env` entry holding the bcrypt (`$2y$`) hash of the admin password. Generated with `bin/hash-password.php` or any tool that produces a compatible bcrypt hash (e.g. `htpasswd -bnBC`).

**Password Hash Helper**  
The `bin/hash-password.php` script. Reads a password from a hidden stdin prompt (never from command-line arguments, keeping it out of shell history) and prints the ready-to-paste `ADMIN_PASSWORD_HASH=` line.

**CSRF Token**  
A per-session token embedded as a hidden field in every state-changing admin form and verified on submission, preventing cross-site request forgery. Used only by the admin tool; the public form relies on a honeypot and HMAC tokens instead.

**Public Note**  
The `public_note` column: an annotation about a recipient that is intended to be visible to everyone who receives the list (e.g. "left the school one year before graduation"). Distinct from *Comment*, which is private to the admin. Editable in the admin tool and shared with approved recipients: shown in brackets after each entry in the plain-text list and as a column in the CSV attachment.

**Comment**  
The `comment` column: free text a registrant optionally provides for the admin during registration (e.g. "you might know me from the class of 1986"). Private — shown to the admin, never published in the list.

**Tags**  
The `tags` column: a single free-text field for ad-hoc taxonomy or categorisation of a recipient. Included as a column in the CSV attachment of the shared list, but kept out of the plain-text email body to avoid clutter.

---

## Sidebar

**Sidebar**  
An optional second column shown next to the registration form on the main form page only. It holds up to two cards — the *Events Card* and the *Links Card* — and appears only when at least one card is configured. Its side (left or right of the form) is set by `SIDEBAR_SIDE`. On narrow screens the sidebar stacks below the form.

**Events Card**  
The "Upcoming Events" card in the sidebar. Its body is rendered from `content/events.md`; its title comes from the translatable key `sidebar.events_title`. Renders only if `content/events.md` exists and is non-empty.

**Links Card**  
The "Links" card in the sidebar. Its body is rendered from `content/links.md`; its title comes from the translatable key `sidebar.links_title`. Renders only if `content/links.md` exists and is non-empty.

**Content File**  
An operator-authored Markdown file under `content/` (`events.md`, `links.md`) supplying a sidebar card's body. Treated as trusted, server-side content but rendered in safe mode (raw HTML stripped, unsafe links disallowed). Example templates ship as `content/*.md.example`; the real files are git-ignored and must not be reachable over HTTP.

**`SIDEBAR_SIDE`**  
The `.env` entry choosing which side of the form the sidebar appears on: `left` or `right`. Optional; unset or invalid values fall back to `right`. There is no separate on/off switch — the sidebar shows only when a card is configured.

**`APP_LOGO`**  
The optional `.env` entry for a header logo shown left of the app name on the main page, resolved to an `<img>` src by the `logoSrc()` helper. Empty or unset shows no logo; any value is used as the logo — either a filename served from `public/` (e.g. `favicon.svg`, `logo.png`) or an absolute `https` URL. The shipped `.env.example` defaults it to `favicon.svg`. The logo auto-scales to the header height and may exceed it by at most 40px; when taller than the title, the header row grows and its contents stay vertically centred. See [ADR-005](adr-005-header-logo.md).

**Sidebar Content Renderer**  
The `App\SidebarContent` class. Configures a `league/commonmark` converter once in safe mode and exposes `renderFile(string $path): string`, returning the card's HTML or an empty string when the file is absent, empty, or fails to read/parse (fail-safe). Links in the rendered output open in a new tab (`target="_blank"` plus `rel="noopener noreferrer"`) via CommonMark's `ExternalLinkExtension`. Rendering is done per request; there is no caching.
