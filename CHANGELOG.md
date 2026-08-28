# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/). This is the
project's first tagged version — 0.9.0 covers everything built so far, pre-1.0.

## [Unreleased]

## [0.9.0] - 2026-08-28

First tagged release. Covers the complete mailing-list application built so far: an
admin-gated registration flow, self-service list retrieval, and the supporting CLI
tooling, all running on plain PHP hosting with a single-file SQLite database.

### Added

- Admin-gated registration: visitors submit their email (and optional name) and the
  administrator receives an approval request with one-click approve/reject links. Nothing
  is exposed to the applicant until an admin acts.
- Optional comment field on the registration form: a free-text field (max 500 characters,
  enforced both client-side and server-side) shown beneath the name field. The comment is
  stored in the database and included in the approval-request email to the admin, but is
  deliberately excluded from the recipient list and its CSV export.
- Self-service list retrieval: approved members can request the current contact list at any
  time, delivered by email with a CSV attachment of all members.
- Unsubscribe flow with a confirmation page, protected by an HMAC-signed token so links
  can't be forged.
- Bot and abuse protection: a hidden honeypot field plus per-IP and per-email rate limiting.
- Internationalisation: all user-facing strings live in per-locale PHP files (`lang/en.php`,
  `lang/de.php`), selected via `APP_LOCALE`, with English as the fallback for missing keys.
  Dates and numbers are formatted through the `intl` extension per locale.
- Configurable info card on the main page via `APP_DESCRIPTION`, and an obfuscated admin
  contact email to deter harvesters.
- CLI import tool (`bin/import.php`) for bulk-adding existing members from a
  semicolon-separated file. Entries are added as approved; the summary now reports which
  lines were actually imported alongside skipped duplicates and invalid lines.
- CLI rate-limit reset tool (`bin/reset-ratelimit.php`) to clear throttling during testing.
- MIT `LICENSE` file and project metadata (`license`, `authors`) in `composer.json`.
- README badges (license, PHP version, SQLite, no-build) and setup/usage documentation.

### Changed

- Registration form styling made more prominent, especially on mobile: bolder, slightly
  larger field labels and clearly bordered, subtly filled input fields with a visible focus
  ring. Implemented by retuning Pico CSS's own custom properties and matching its control
  selectors, rather than plain overrides that Pico's shorthand would reset.
