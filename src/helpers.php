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

    if ($value instanceof \DateTimeInterface) {
        return $formatter->format($value);
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
