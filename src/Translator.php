<?php

declare(strict_types=1);

namespace App;

final class Translator
{
    private static ?self $instance = null;

    private array $messages = [];
    private array $fallback = [];
    private string $locale;

    public function __construct(string $locale, string $langDir)
    {
        $this->locale = $locale;
        $langCode = $this->extractLanguage($locale);

        // Load fallback (English) first
        $fallbackFile = $langDir . '/en.php';
        if (is_file($fallbackFile)) {
            $this->fallback = require $fallbackFile;
        }

        // Load configured locale (may be the same as fallback)
        $localeFile = $langDir . '/' . $langCode . '.php';
        if (is_file($localeFile)) {
            $this->messages = require $localeFile;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Translator has not been initialised. Call Translator::init() first.');
        }

        return self::$instance;
    }

    public static function init(string $locale, string $langDir): self
    {
        self::$instance = new self($locale, $langDir);

        return self::$instance;
    }

    /**
     * Translate a key, optionally interpolating placeholders.
     */
    public function get(string $key, array $params = []): string
    {
        $message = $this->messages[$key] ?? $this->fallback[$key] ?? $key;

        if ($params !== []) {
            $replacements = [];
            foreach ($params as $name => $value) {
                $replacements['{' . $name . '}'] = (string) $value;
            }
            $message = strtr($message, $replacements);
        }

        return $message;
    }

    /**
     * Get the full ICU locale string (e.g. "de_DE").
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Get the short language code (e.g. "de" from "de_DE").
     */
    public function getLanguage(): string
    {
        return $this->extractLanguage($this->locale);
    }

    private function extractLanguage(string $locale): string
    {
        // Handle both de_DE and de-DE formats
        $parts = preg_split('/[_\-]/', $locale, 2);

        return strtolower($parts[0]);
    }
}
