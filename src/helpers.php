<?php

declare(strict_types=1);

use App\Translator;

/**
 * Application version (Semantic Versioning).
 *
 * Single source of truth is the "version" field in composer.json — the same field the
 * release workflow reads to tag releases. Read once and cached for the request. Falls
 * back to 'dev' if composer.json is missing or unparseable.
 */
function app_version(): string
{
    static $version = null;

    if ($version !== null) {
        return $version;
    }

    $version = 'dev';
    $composerPath = dirname(__DIR__) . '/composer.json';

    if (is_file($composerPath)) {
        $data = json_decode((string) file_get_contents($composerPath), true);
        if (is_array($data) && !empty($data['version']) && is_string($data['version'])) {
            $version = $data['version'];
        }
    }

    return $version;
}

/**
 * Resolve the configured logo value (APP_LOGO) to an <img> src.
 *
 * Behaviour:
 *   - empty / unset  -> returns "" so the caller renders no logo
 *   - absolute URL   -> returned unchanged (e.g. a CDN-hosted logo)
 *   - anything else  -> a static asset served from the web root (e.g. "favicon.svg"
 *                       or "logo.png", served next to favicon.svg), with leading
 *                       slashes trimmed so it resolves under both supported docroot
 *                       layouts.
 *
 * The shipped .env.example defaults APP_LOGO to "favicon.svg", so a fresh install
 * shows the bundled favicon; operators can blank it to hide the logo or point it at
 * their own image.
 */
function logoSrc(string $logo): string
{
    $logo = trim($logo);

    if ($logo === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $logo) === 1) {
        return $logo;
    }

    return ltrim($logo, '/');
}

/**
 * Maximum length (in characters) for the optional comment field.
 */
const COMMENT_MAX_LENGTH = 500;

/**
 * Translate a key, optionally interpolating placeholders.
 *
 * Usage: __('form.submit')
 *        __('mail.list_total', ['count' => 5])
 */
function __(string $key, array $params = []): string
{
    return Translator::getInstance()->get($key, $params);
}

/**
 * Format a date/time value according to the configured locale.
 *
 * @param \DateTimeInterface|int $value  A DateTime object or Unix timestamp
 * @param int $dateStyle IntlDateFormatter constant (FULL, LONG, MEDIUM, SHORT, NONE)
 * @param int $timeStyle IntlDateFormatter constant (FULL, LONG, MEDIUM, SHORT, NONE)
 */
function formatDate(
    \DateTimeInterface|int $value,
    int $dateStyle = \IntlDateFormatter::MEDIUM,
    int $timeStyle = \IntlDateFormatter::NONE,
): string {
    $locale = Translator::getInstance()->getLocale();
    $formatter = new \IntlDateFormatter($locale, $dateStyle, $timeStyle);

    // IntlDateFormatter::format() ignores the timezone carried by a DateTime
    // object and uses the formatter's own timezone (PHP's default). To honour the
    // caller's intent — e.g. a value already converted to APP_TIMEZONE — set the
    // formatter's timezone from the object. A bare Unix timestamp carries no zone
    // intent, so it keeps the formatter default.
    if ($value instanceof \DateTimeInterface) {
        $formatter->setTimeZone($value->getTimezone());
    }

    return $formatter->format($value);
}

/**
 * Format a number according to the configured locale.
 *
 * @param int|float $value      The number to format
 * @param int       $style      NumberFormatter style constant (DECIMAL, CURRENCY, etc.)
 * @param int|null  $decimals   Number of fraction digits (null = locale default)
 */
function formatNumber(
    int|float $value,
    int $style = \NumberFormatter::DECIMAL,
    ?int $decimals = null,
): string {
    $locale = Translator::getInstance()->getLocale();
    $formatter = new \NumberFormatter($locale, $style);

    if ($decimals !== null) {
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
    }

    return $formatter->format($value);
}

/**
 * Resolve the timezone used to display stored timestamps.
 *
 * Timestamps are stored in UTC (SQLite datetime('now')). For display we convert
 * to the timezone named by the APP_TIMEZONE env var (e.g. "Europe/Berlin"). When
 * it is unset, empty, or not a valid identifier, we fall back to PHP's default
 * timezone (date_default_timezone_get()), i.e. the server's configured zone.
 */
function appTimezone(): \DateTimeZone
{
    static $tz = null;

    if ($tz !== null) {
        return $tz;
    }

    $configured = trim((string) ($_ENV['APP_TIMEZONE'] ?? ''));

    if ($configured !== '') {
        try {
            return $tz = new \DateTimeZone($configured);
        } catch (\Exception) {
            // Invalid identifier — fall through to the server default rather than
            // failing the whole page over a config typo.
        }
    }

    return $tz = new \DateTimeZone(date_default_timezone_get());
}

/**
 * Format a stored UTC timestamp string ('YYYY-MM-DD HH:MM:SS') for display in the
 * application timezone (see appTimezone()). Returns the raw input unchanged if it
 * cannot be parsed.
 */
function formatLocalTime(string $utcTimestamp, string $format = 'Y-m-d H:i'): string
{
    try {
        $dt = new \DateTimeImmutable($utcTimestamp, new \DateTimeZone('UTC'));
    } catch (\Exception) {
        return $utcTimestamp;
    }

    return $dt->setTimezone(appTimezone())->format($format);
}

/**
 * Format an actor ("who") for an activity log line: "Name <email>" when a name
 * is present, otherwise just the email address.
 */
function logActor(string $email, ?string $name = null): string
{
    $name = $name !== null ? trim($name) : '';
    return $name !== '' ? "{$name} <{$email}>" : $email;
}

/**
 * Obfuscate an email address by encoding each character as an HTML numeric entity.
 *
 * This deters simple regex-based email harvesters while remaining fully readable
 * and clickable for humans (browsers decode entities transparently).
 */
function obfuscateEmail(string $email): string
{
    $output = '';
    for ($i = 0, $len = strlen($email); $i < $len; $i++) {
        $output .= '&#' . ord($email[$i]) . ';';
    }
    return $output;
}
