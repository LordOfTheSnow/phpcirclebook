# PHPCircleBook

A self-contained PHP contact directory with admin-gated registration and self-service list retrieval. Built to run on simple PHP webhosting.

Members of a group (alumni, clubs, communities) can apply to join. Once approved by the admin, they can request the contact list of all other members. No admin panel, no login system — just email.

## Features

- Admin-gated registration via email approval links
- Self-service list retrieval for approved recipients (with CSV attachment)
- Configurable info card on the main page (via `APP_DESCRIPTION` in `.env`)
- Obfuscated admin contact email (anti-harvesting)
- CLI import tool for bulk-adding existing members
- Unsubscribe with confirmation page
- Bot protection (honeypot + rate limiting)
- Internationalised — ships with English and German, easy to add more
- Single-file SQLite database, no external services
- Pico CSS for a clean UI with zero build steps

## Requirements

- PHP 8.2+
- `intl` extension (for locale-aware date/number formatting)
- `pdo_sqlite` extension
- A working MTA (sendmail/postfix) for `mail()`
- Composer

## Installation

```bash
git clone https://github.com/your-user/phpcirclebook.git
cd phpcirclebook
composer install
```

Copy the example environment file and configure it:

```bash
cp .env.example .env
```

Edit `.env` with your values:

```
APP_NAME="My Mailing List"
APP_URL="https://list.example.com"
ADMIN_EMAIL="admin@example.com"
HMAC_SECRET="generate-a-long-random-string-here"
DB_PATH="data/mailinglist.db"
APP_LOCALE="en_US"
APP_DESCRIPTION="Describe your mailing list here. This text is shown to visitors on the main page."
```

Generate a secure HMAC secret:

```bash
php -r 'echo bin2hex(random_bytes(32)) . "\n";'
```

Ensure the `data/` directory is writable by the web server:

```bash
chmod 775 data/
```

Point your web server's document root to the `public/` directory.

## Configuration

| Variable | Description |
|----------|-------------|
| `APP_NAME` | Displayed in page titles, headings, and email subjects |
| `APP_URL` | Public URL of the application (no trailing slash) |
| `ADMIN_EMAIL` | Receives approval requests, used as From address |
| `HMAC_SECRET` | Secret key for unsubscribe token generation |
| `DB_PATH` | Path to SQLite database file (relative to project root) |
| `APP_LOCALE` | ICU locale code (e.g. `de_DE`, `en_US`, `fr_FR`) |
| `APP_DESCRIPTION` | Custom text shown on the info card (optional, can be left empty) |

## Localisation

The application language is fixed per instance via `APP_LOCALE`. Set it to a full ICU locale code like `de_DE` or `en_US`.

Translation files live in `lang/`:

```
lang/
  en.php   ← English (reference / fallback)
  de.php   ← German
```

### Adding a new language

1. Copy `lang/en.php` to `lang/xx.php` (where `xx` is your language code)
2. Translate the string values (keep the keys unchanged)
3. Set `APP_LOCALE="xx_XX"` in `.env`

The app falls back to English for any missing translation keys, so partial translations work fine.

### Date and number formatting

Dates and numbers are formatted using PHP's `intl` extension according to the configured locale. No manual format strings needed — `de_DE` gives you `25.08.2026` and `1.234,5`, while `en_US` gives `Aug 25, 2026` and `1,234.5`.

## Project Structure

```
public/index.php      Front controller (routing + handlers)
src/Database.php      SQLite persistence layer
src/Mailer.php        Email composition and sending
src/RateLimiter.php   Per-IP and per-email throttling
src/TokenService.php  Approval + unsubscribe token generation
src/Translator.php    Translation loader with fallback
src/helpers.php       Global __(), formatDate(), formatNumber(), obfuscateEmail()
templates/            PHP templates (form, layout, message, unsubscribe)
lang/                 Translation files (one PHP array per locale)
bin/import.php        CLI: bulk-import members from a semicolon-separated file
bin/reset-ratelimit.php  CLI: clear all rate limit entries
data/                 SQLite database (auto-created on first use)
```

## CLI Tools

### Import members

Bulk-import an existing member list:

```bash
php bin/import.php members.txt
```

File format (one entry per line, `#` lines are comments):

```
alice@example.com;Alice Müller
bob@example.com;Bob
carol@example.com;
```

Imported members are set to "approved" status. Duplicates are skipped and reported.

### Reset rate limits

If you lock yourself out during testing:

```bash
php bin/reset-ratelimit.php
```

## About the name

PHPCircleBook — a shared address book for your circle of people, running on plain PHP hosting.

## License

MIT
