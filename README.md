# PHPCircleBook

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![SQLite](https://img.shields.io/badge/SQLite-embedded-003B57.svg?logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![No build step](https://img.shields.io/badge/build-none-brightgreen.svg)](#requirements)
[![Version](https://img.shields.io/badge/version-0.9.0-blue.svg)](CHANGELOG.md)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

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

> **Email deliverability:** the whole app is email-driven (approval links, list delivery,
> unsubscribe), so mail actually reaching inboxes matters. It sends via PHP's built-in
> `mail()`, and messages from shared hosting often land in spam — or are dropped outright —
> without proper sender authentication. For reliable delivery, make sure the domain in
> `ADMIN_EMAIL` (the `From` address) has valid **SPF**, **DKIM**, and ideally **DMARC**
> DNS records covering your host's mail server, and that `ADMIN_EMAIL` uses your own
> domain rather than a free provider (Gmail, GMX, etc.), whose DMARC policies will cause
> such mail to be rejected. If your host offers SMTP relay or an email API, routing mail
> through that instead of raw `mail()` is more reliable still.

## Installation

There are two ways to install: cloning the repository and running Composer (needs
shell access), or downloading a ready-to-upload release zip and transferring it by FTP
(no shell access required). Both share the same `.env` configuration steps below.

### Installing with git and Composer

```bash
git clone https://github.com/LordOfTheSnow/phpcirclebook.git
cd phpcirclebook
composer install
```

### Installing via FTP (no shell access)

For shared hosting where you can't run Composer on the server, each
[release](https://github.com/LordOfTheSnow/phpcirclebook/releases) ships a
`phpcirclebook-ftp-<version>.zip` asset with all dependencies (`vendor/`) already
bundled — no Composer step needed.

1. Download `phpcirclebook-ftp-<version>.zip` from the
   [latest release](https://github.com/LordOfTheSnow/phpcirclebook/releases/latest).
2. Unzip it on your computer.
3. Upload the extracted files to your web space via FTP.
4. Point your hosting's document root (or the domain/subdomain) at the uploaded
   `public/` directory. **If your host doesn't let you change the document root** (many
   shared hosts have a fixed `public_html`), upload everything so the project root sits
   at the document root instead — a bundled root `.htaccess` and `index.php` fallback
   route all requests to the app while denying web access to `vendor/`, `src/`, `data/`,
   the SQLite database, and your `.env`. This requires Apache with `mod_rewrite`
   (standard on most shared PHP hosts).
5. Follow the `.env` configuration steps below (create `.env` from `.env.example` and
   fill in your values), and make sure the `data/` directory is writable by the web
   server.

> **Nginx (and other non-Apache servers):** the bundled `.htaccess` files are read only
> by Apache and are ignored elsewhere. On Nginx you **must point** the site root at the
> `public/` directory and route requests through `public/index.php` in your server
> config — the project-root fallback does not apply. A minimal server block looks like:
>
> ```nginx
> server {
>     server_name list.example.com;
>     root /var/www/phpcirclebook/public;
>     index index.php;
>
>     location / {
>         try_files $uri $uri/ /index.php?$query_string;
>     }
>
>     location ~ \.php$ {
>         include fastcgi_params;
>         fastcgi_pass unix:/run/php/php-fpm.sock;
>         fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
>     }
> }
> ```
>
> Because the root is `public/`, the non-public files (`vendor/`, `src/`, `data/`,
> `.env`, …) sit outside the served directory and are already unreachable — no extra
> deny rules needed.

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
| `APP_URL` | Public URL visitors use to reach the app, no trailing slash (e.g. `https://list.example.com`). Used to build the approval and unsubscribe links in emails, so it must be the externally reachable address — not `localhost` or a path ending in `/public`. Set it to the same URL regardless of whether the document root points at `public/` or at the project root. |
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
phpcirclebook/
├── public/
│   ├── index.php           # Front controller (routing + handlers)
│   └── .htaccess           # Front-controller rewrite (docroot = public/)
├── index.php               # Fallback front controller (docroot = project root)
├── .htaccess               # Root routing + denies web access to non-public files
├── src/
│   ├── Database.php        # SQLite persistence layer
│   ├── Mailer.php          # Email composition and sending
│   ├── RateLimiter.php     # Per-IP and per-email throttling
│   ├── TokenService.php    # Approval + unsubscribe token generation
│   ├── Translator.php      # Translation loader with fallback
│   └── helpers.php         # app_version(), __(), formatDate(), formatNumber(), obfuscateEmail()
├── templates/              # PHP templates (form, layout, message, unsubscribe)
├── lang/                   # Translation files (one PHP array per locale)
├── bin/
│   ├── import.php          # CLI: bulk-import members from a semicolon-separated file
│   └── reset-ratelimit.php # CLI: clear all rate limit entries
├── data/                   # SQLite database (auto-created on first use)
├── docs/                   # Architecture decision records and glossary
├── .github/
│   └── workflows/          # CI: auto release + FTP release-zip build
├── .env.example            # Template for the .env configuration file
└── CHANGELOG.md            # Release history (Keep a Changelog / SemVer)
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

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the release history. This project follows
[Semantic Versioning](https://semver.org/).

### Releasing

Releases are cut automatically by GitHub Actions. To publish a new version:

1. Bump the `version` field in `composer.json` (the app reads its displayed version from
   there, so this is the only place to change it).
2. Add the matching `## [x.y.z] - YYYY-MM-DD` section to `CHANGELOG.md`, using the date
   you actually push the release (the workflow matches the section by version number, so
   the date is only shown in the release notes — but keep it accurate).
3. Commit and push to `main`.

The [`Auto Release`](.github/workflows/release.yml) workflow detects the new version,
creates the `vx.y.z` tag and a GitHub Release (with notes taken from the CHANGELOG
section), then triggers [`Build FTP release zip`](.github/workflows/ftp-release-zip.yml),
which installs production dependencies and attaches the `phpcirclebook-ftp-<version>.zip`
asset to the release. If the version already has a tag, the push is a no-op.

## License

[MIT](LICENSE)
