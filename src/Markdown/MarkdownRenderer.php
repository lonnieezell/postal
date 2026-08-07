<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Postal.
 *
 * (c) Lonnie Ezell <lonnieje@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Postal\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Myth\Postal\Config\Postal as PostalConfig;

/**
 * Converts raw CommonMark markdown into HTML (for the message body) and into
 * plain text (for the multipart/alternative text fallback).
 */
class MarkdownRenderer
{
    /**
     * Per-line markdown-syntax replacements, applied before lines are
     * rejoined: heading marker, blockquote marker(s) (including nested), and
     * normalizing "*"/"+" list markers to "-".
     *
     * @var array<string, string>
     */
    private const LINE_REPLACEMENTS = [
        '/^#{1,6}\s+/'      => '',
        '/^(>\s?)+/'        => '',
        '/^(\s*)[*+](\s+)/' => '$1-$2',
    ];

    /**
     * Whole-text markdown-syntax replacements, applied after lines are
     * rejoined, in order: images before links (link syntax is a substring of
     * image syntax), emphasis longest-delimiter-first so "**" isn't consumed
     * by the "*" pattern, code fences before inline code spans, and the
     * blank-line collapse last.
     *
     * The "/s" modifier on emphasis/strikethrough lets a pair span a
     * hand-wrapped line break; requiring a non-space on the inner edge of
     * each delimiter keeps "3 * 4 * 5" from being read as emphasis around
     * " 4 ".
     *
     * @var array<string, string>
     */
    private const TEXT_REPLACEMENTS = [
        '/!\[([^\]]*)\]\([^)]*\)/'                   => '$1',
        '/\[([^\]]*)\]\(([^)]*)\)/'                  => '$1 ($2)',
        '/(\*\*\*|___)(?!\s)(.+?)(?<!\s)\1/s'        => '$2',
        '/(\*\*|__)(?!\s)(.+?)(?<!\s)\1/s'           => '$2',
        '/(?<!\w)(\*|_)(?!\s)(.+?)(?<!\s)\1(?!\w)/s' => '$2',
        '/~~(?!\s)(.+?)(?<!\s)~~/s'                  => '$1',
        '/^```.*$/m'                                 => '',
        '/`([^`]*)`/'                                => '$1',
        '/\n{3,}/'                                   => "\n\n",
    ];

    private readonly MarkdownConverter $converter;

    public function __construct(?PostalConfig $config = null)
    {
        // Resolved by short name so that an application's Config\Postal, if one
        // has been published, is preferred over the package's default.
        $config ??= config('Postal');

        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());

        foreach ($config->markdownExtensions as $extension) {
            $environment->addExtension(new $extension());
        }

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Converts markdown to HTML using CommonMark plus the configured
     * extensions.
     */
    public function toHtml(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }

    /**
     * Strips markdown syntax from the raw source into readable plain text,
     * for use as the multipart/alternative text part.
     */
    public function toText(string $markdown): string
    {
        $lines = [];

        foreach (explode("\n", $markdown) as $line) {
            // GFM table separator row, e.g. "| --- | --- |": drop entirely.
            // Requires a pipe so a bare "--" paragraph isn't mistaken for one.
            if (str_contains($line, '|') && preg_match('/^\s*\|?[\s:|-]+\|?\s*$/', $line) && str_contains($line, '-')) {
                continue;
            }

            // Horizontal rule, e.g. "---", "***", "___": drop entirely.
            if (preg_match('/^\s*([-*_])\s*(?:\1\s*){2,}$/', $line)) {
                continue;
            }

            // Table row: strip the outer pipes and rejoin cells with " | ".
            if (preg_match('/^\s*\|(.+)\|\s*$/', $line, $matches)) {
                $line = implode(' | ', array_map(trim(...), explode('|', $matches[1])));
            }

            foreach (self::LINE_REPLACEMENTS as $pattern => $replacement) {
                $line = preg_replace($pattern, $replacement, $line) ?? $line;
            }

            $lines[] = $line;
        }

        $text = implode("\n", $lines);

        foreach (self::TEXT_REPLACEMENTS as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return trim($text);
    }
}
