<?php

declare(strict_types=1);

use App\Translator;

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
