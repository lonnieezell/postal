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
use Myth\Postal\Markdown\Parser\MailComponentStartParser;

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
     * A markdown image, replaced by its alt text. Runs before LINK, because
     * link syntax is a substring of image syntax.
     */
    private const IMAGE = '/!\[([^\]]*)\]\([^)]*\)/';

    /**
     * A markdown link, rewritten as "label (url)".
     */
    private const LINK = '/\[([^\]]*)\]\(([^)]*)\)/';

    /**
     * Whole-text markdown-syntax replacements, applied after lines are
     * rejoined and links are rewritten, in order: emphasis
     * longest-delimiter-first so "**" isn't consumed by the "*" pattern, code
     * fences before inline code spans, and the blank-line collapse last.
     *
     * The "/s" modifier on emphasis/strikethrough lets a pair span a
     * hand-wrapped line break; requiring a non-space on the inner edge of
     * each delimiter keeps "3 * 4 * 5" from being read as emphasis around
     * " 4 ".
     *
     * @var array<string, string>
     */
    private const TEXT_REPLACEMENTS = [
        '/(\*\*\*|___)(?!\s)(.+?)(?<!\s)\1/s'        => '$2',
        '/(\*\*|__)(?!\s)(.+?)(?<!\s)\1/s'           => '$2',
        '/(?<!\w)(\*|_)(?!\s)(.+?)(?<!\s)\1(?!\w)/s' => '$2',
        '/~~(?!\s)(.+?)(?<!\s)~~/s'                  => '$1',
        '/^```.*$/m'                                 => '',
        '/`([^`]*)`/'                                => '$1',
        '/\n{3,}/'                                   => "\n\n",
    ];

    /**
     * A `<mail-{tag} ...>slot</mail-{tag}>` component whose slot holds no
     * other component tag. The slot is matched possessively so a large slot
     * can't exhaust the PCRE JIT stack.
     */
    private const COMPONENT = '/' . MailComponentStartParser::OPEN_TAG_PATTERN . '((?:[^<]++|<(?!\/?mail-))*+)<\/mail-\1>/i';

    /**
     * A bare http(s) URL in the text. It stops at whitespace, parentheses and
     * angle brackets, and never ends on punctuation or an emphasis delimiter,
     * so "**https://example.com**" and "https://example.com." keep the URL out
     * of the surrounding markup.
     */
    private const URL = '/\bhttps?:\/\/[^\s()<>]*[^\s()<>*_~`.,;:!?\'"]/i';

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

        $environment->addExtension(new MailComponentExtension($config->componentViewPath, $config->markdownExtensions));

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
        // URLs are swapped for placeholders until the end, so the emphasis
        // and code rules can't strip "_", "*", "~" or "`" pairs out of them.
        $urls     = [];
        $markdown = $this->stripComponentTags($markdown, $urls);
        $lines    = [];

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
        $text = preg_replace(self::IMAGE, '$1', $text) ?? $text;
        $text = preg_replace_callback(
            self::LINK,
            static function (array $match) use (&$urls): string {
                return $match[1] . ' (' . self::placeholder($match[2], $urls) . ')';
            },
            $text,
        ) ?? $text;
        $text = preg_replace_callback(
            self::URL,
            static function (array $match) use (&$urls): string {
                return self::placeholder($match[0], $urls);
            },
            $text,
        ) ?? $text;

        foreach (self::TEXT_REPLACEMENTS as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return trim(strtr($text, $urls));
    }

    /**
     * Replaces each Mail Component with its slot text, plus its `url` in
     * parentheses like a markdown link. Nested components are unwrapped
     * innermost-first, one pass at a time.
     *
     * @param array<string, string> $urls
     */
    private function stripComponentTags(string $markdown, array &$urls): string
    {
        do {
            $markdown = preg_replace_callback(
                self::COMPONENT,
                static function (array $match) use (&$urls): string {
                    $slot = trim($match[3]);
                    $url  = MailComponentStartParser::parseAttributes($match[2])['url'] ?? '';

                    if ($url === '') {
                        return $slot;
                    }

                    $url = self::placeholder($url, $urls);

                    return $slot === '' ? $url : $slot . ' (' . $url . ')';
                },
                $markdown,
                -1,
                $count,
            ) ?? $markdown;
        } while ($count > 0);

        return $markdown;
    }

    /**
     * Stores the URL in $urls and returns the placeholder that stands in for
     * it until toText() swaps it back. "\x1A" is used because trim() keeps
     * it, unlike "\0".
     *
     * @param array<string, string> $urls
     */
    private static function placeholder(string $url, array &$urls): string
    {
        $placeholder        = "\x1A" . count($urls) . "\x1A";
        $urls[$placeholder] = $url;

        return $placeholder;
    }
}
