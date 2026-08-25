# ADR-002: Internationalisation (i18n)

**Status:** Accepted  
**Date:** 2026-08-25  
**Context:** PHPCircleBook is deployed in German on the author's hosting but published on GitHub for international use. All user-facing text must be translatable without code changes.

---

## Summary

Make the application locale-aware so that a single deployment speaks one language (configured via `.env`), while contributors can add new translations by copying a single file.

---

## Decisions

### 1. Translation Mechanism: Simple PHP Arrays

Each locale gets a single file (`lang/en.php`, `lang/de.php`, etc.) that returns a flat associative array. No external i18n library; the app has ~30 translatable strings and no complex pluralisation needs.

### 2. Locale Selection: Fixed per Instance via `.env`

A single `APP_LOCALE` setting (e.g. `de_DE`) determines the language for the entire instance. No per-visitor switching, no URL prefixes, no cookies. GitHub users clone and set their own locale.

### 3. Env Variable: Full ICU Locale (`APP_LOCALE=de_DE`)

The value is a full ICU locale code (language + region). Used directly by `IntlDateFormatter` and `NumberFormatter` for regional formatting. The language part (before `_`) is derived to resolve the language file (`lang/de.php`).

### 4. Fallback: English

If a translation key is missing in the configured locale file, the English value is used. If `lang/{locale}.php` does not exist at all, the app falls back entirely to `lang/en.php`. The application never shows raw key names to users.

### 5. Key Naming: Dot-Namespaced

Keys are grouped by context:
- `form.*` — form labels, buttons, placeholders, explanatory text
- `message.*` — controller flash messages shown via the message template
- `mail.*` — email subjects and body fragments

### 6. Placeholder Syntax: Curly-Brace Tokens

Dynamic values use `{name}` tokens interpolated with `strtr`:
```php
'mail.list_total' => 'Total: {count} recipient(s)',
```
Usage: `__('mail.list_total', ['count' => 5])`.

### 7. Translation Helper: Hybrid (Class + Global Function)

A `Translator` class loads the arrays, resolves fallback, and performs placeholder interpolation. A thin global `__($key, $params)` function delegates to a singleton `Translator` instance. Templates use `<?= __('form.submit') ?>`.

### 8. Scope: All User-Facing Text Including Emails

All strings are translated — form labels, controller messages, email subjects, email bodies (including admin-facing approval requests). The instance speaks one language consistently.

### 9. Date and Number Formatting: PHP `intl` Extension

`IntlDateFormatter` and `NumberFormatter` handle locale-aware date/time and number output. The `intl` extension is required (added to `composer.json` platform requirements). A helper function (e.g. `formatDate()`) wraps the formatter for convenient use in templates and emails.

### 10. HTML `lang` Attribute: Dynamic

`<html lang="...">` in `layout.php` is set from the short language code derived from `APP_LOCALE` (e.g. `de` from `de_DE`).

### 11. Languages Shipped: English + German

English (`lang/en.php`) serves as the reference file and fallback. German (`lang/de.php`) is the author's production locale. Contributors copy `lang/en.php` to add new languages.

### 12. File Structure: One File per Locale

```
lang/
  en.php   ← reference / fallback
  de.php   ← German translation
```
Each file returns a single flat `['dot.key' => 'translated string', ...]` array.

### 13. Mailer Uses `__()` Directly

The `Mailer` class calls the global `__()` function internally to build translated email subjects and bodies. No refactoring of method signatures required.

---

## Consequences

- **Simple contribution:** Adding a language means copying one file and translating ~30 values.
- **No new Composer dependency:** The translation layer is custom (~50 lines of code).
- **`intl` extension required:** Documented in `composer.json` and README. Available on the vast majority of PHP hosts.
- **No per-visitor language switching:** If needed later, it can be layered on (URL parameter + session) without changing the translation file structure.
- **Fixed locale per deployment:** Matches the single-tenant nature of the app.
