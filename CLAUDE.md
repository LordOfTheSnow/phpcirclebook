# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

PHPCircleBook — a self-contained PHP contact directory with admin-gated registration and
self-service list retrieval. No framework, no build step, single-file SQLite database.
Designed to run on plain shared PHP hosting (with an FTP-only deployment path in addition
to git+Composer). PHP 8.2+, PSR-4 autoloading (`App\` → `src/`).

## Commands

```bash
composer install              # install dependencies
composer start                # run locally: php -S 0.0.0.0:8001 -t public/
php -l path/to/file.php       # syntax-check a single file (what CI's lint step does, per file)
composer validate --no-check-publish   # validate composer.json/composer.lock (as CI does)
php bin/hash-password.php     # generate bcrypt ADMIN_PASSWORD_HASH for .env
```

There is no automated test suite (no `tests/`, no PHPUnit/Pest in `composer.json`) and no
linter/formatter config (no PHPCS, PHP-CS-Fixer, PHPStan/Psalm). CI (`.github/workflows/ci.yml`)
only runs `php -l` over every `*.php` file (excluding `vendor/`) and `composer validate`. Verify
changes by running the app locally (`composer start`) and exercising the relevant flow —
`mail()` won't actually deliver from a local machine, so check status/DB state rather than
inbox delivery when testing email-triggering paths.

CLI tools for exercising the app manually:

```bash
php bin/admin.php list|add|edit|status|delete ...   # manage recipients from the shell
php bin/import.php members.txt                       # bulk import (see README for file format)
php bin/reset-ratelimit.php                           # clear rate-limit entries (e.g. if locked out while testing)
```

## Architecture

**Two independent front controllers, one shared data/service layer.** `public/index.php`
(public form + approve/reject/unsubscribe flow) and `public/admin.php` (password-gated recipient
management) each bootstrap their own Dotenv load and routing, but both operate on the same
`recipients` table via `App\Database`. There is no framework/router/DI container — each front
controller does `$action = $_GET['action']`, then a `match` dispatches to local handler functions
defined further down the same file. `index.php` at the project root is a thin fallback that
`require`s `public/index.php`, for hosts that can't point their document root at `public/`
(paired with the root `.htaccess`, which denies web access to everything non-public).

**Request flow (public side):** `public/index.php` handler → `App\Database` (SQLite via PDO,
migrations run inline in the constructor — `CREATE TABLE IF NOT EXISTS` plus idempotent
`addColumnIfMissing` calls, no migration files/framework) → `App\Mailer` for outgoing mail →
`renderPage()`/`renderMessage()`, which `ob_start()` a template from `templates/` and then wrap
it in `templates/layout.php`. Templates are plain PHP with `extract($vars)`, not a templating
engine.

**Recipient lifecycle** (see `docs/glossary.md` for full domain vocabulary): a submission creates
a `pending` recipient with a random approval token (`App\TokenService`), which emails the admin
approve/reject links. Clicking one flips status to `approved`/`rejected` and clears the token.
An `approved` recipient who resubmits their email gets the full list emailed back
(`getApprovedRecipients()`); unsubscribe uses a separate stateless HMAC token (derived from
email + `HMAC_SECRET`, never expires, no DB state) rather than the approval token.

**Security-relevant primitives**, each in its own single-purpose class in `src/`: `TokenService`
(random approval tokens with expiry, stateless HMAC unsubscribe tokens), `RateLimiter` (per-IP
and per-email throttling backed by the `rate_limits` table), a honeypot field checked directly
in `handleSubmit()`. The admin tool additionally uses a CSRF token per session (see
`docs/glossary.md`); the public form does not (it relies on the honeypot + HMAC tokens instead).

**i18n is a hard architectural line, not a nice-to-have.** All user-facing strings go through
`__('key', [params])` (`src/helpers.php` → `App\Translator`), keyed by dot-namespaced strings
grouped `form.*`/`message.*`/`mail.*`/`sidebar.*` and defined per-locale in `lang/*.php`
(`en.php` is the fallback for missing keys in any other locale). Never hardcode user-facing text
in templates, mailer code, or handlers — add a key to `lang/en.php` (and ideally `lang/de.php`)
instead. Locale also drives date/number formatting via the `intl` extension
(`formatDate()`/`formatNumber()` in `src/helpers.php`), not manual format strings.

**Timestamps are stored in UTC** (SQLite `datetime('now')`) and converted to `APP_TIMEZONE` only
at display time via `formatLocalTime()`/`appTimezone()` in `src/helpers.php`. Don't compare or
store timezone-converted values in the database.

**Two annotation fields on a recipient look similar but are not:** `comment` is private,
admin-only, provided by the registrant at signup. `public_note` is admin-authored and shown to
everyone who receives the list. Don't conflate them when touching recipient CRUD.

**Sidebar content** (`App\SidebarContent`) renders operator-authored Markdown
(`content/events.md`, `content/links.md`, gitignored — only `*.md.example` is committed) via
`league/commonmark` in a hardened safe mode (raw HTML stripped, external links forced to open in
a new tab). A card renders only if its file exists and is non-empty; there's no on/off flag or
caching.

**Version is read from `composer.json`'s `version` field** (`app_version()` in
`src/helpers.php`), which is also what the release automation tags — see "Releasing" in
README.md for the release flow (bump version → add CHANGELOG section → push to `main`; a GitHub
Actions workflow tags and releases automatically, including building the FTP zip asset). Bumping
that version is a deliberate release action, not a routine part of feature work.

**Pico CSS is loaded from a CDN pinned to an exact version with an SRI hash** (in
`templates/layout.php` and `public/admin.php`) rather than a floating `@2` tag, so it never
silently changes. Bumping it is a manual step (or a to-be-set-up Renovate custom manager) —
see "Updating the pinned CDN assets" in README.md for the exact process, including how to
regenerate the hash.

**Architecture decisions live in `docs/adr-*.md`** — check there before changing behavior around
the admin tool, i18n, the sidebar, or the header logo; each has a numbered ADR explaining the
reasoning, not just the mechanism. `docs/glossary.md` is the canonical definition of domain terms
(Recipient, Approval Token, Public Note, etc.) used throughout the code and docs.
