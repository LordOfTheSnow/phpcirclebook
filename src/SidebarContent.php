<?php

declare(strict_types=1);

namespace App;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\MarkdownConverter;
use Throwable;

/**
 * Renders operator-authored Markdown content files (the sidebar's Events and
 * Links cards) to HTML.
 *
 * The converter is configured once, in safe mode: raw HTML is stripped and
 * unsafe links are disallowed. Although the content files are operator-authored
 * today, safe mode keeps a future admin-editable / DB-backed source (see
 * ADR-004) from becoming a stored-XSS vector.
 *
 * External links are opened in a new tab: the ExternalLinkExtension adds
 * target="_blank" and, for external hosts, rel="noopener" automatically, so
 * operators never write raw HTML to achieve this.
 *
 * Rendering is fail-safe: a file that is missing, empty, unreadable, or fails
 * to parse yields an empty string, so a broken card simply does not render and
 * never takes down the public form page.
 */
final class SidebarContent
{
    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'external_link' => [
                // Treat every link in the sidebar content as external and open
                // it in a new tab. noopener/noreferrer default to "external",
                // so they are applied to these links automatically.
                'open_in_new_window' => true,
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new ExternalLinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Render a Markdown content file to HTML.
     *
     * Returns an empty string when the file does not exist, is empty (after
     * trimming), cannot be read, or fails to convert. The caller treats an
     * empty result as "card not configured".
     */
    public function renderFile(string $path): string
    {
        try {
            if (!is_file($path) || !is_readable($path)) {
                return '';
            }

            $markdown = file_get_contents($path);
            if ($markdown === false || trim($markdown) === '') {
                return '';
            }

            $html = trim((string) $this->converter->convert($markdown));

            return $html;
        } catch (Throwable $e) {
            // Decorative content must never break the page. Log for the operator
            // and degrade to "not configured".
            error_log('SidebarContent: failed to render ' . $path . ': ' . $e->getMessage());

            return '';
        }
    }
}
