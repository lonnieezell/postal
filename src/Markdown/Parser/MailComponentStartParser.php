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

namespace Myth\Postal\Markdown\Parser;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\InlinesOnly\InlinesOnlyExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use Myth\Postal\Markdown\Node\MailComponentNode;

/**
 * Recognises `<mail-{tag} attr="...">` as the start of a MailComponentNode.
 * When the matching `</mail-{tag}>` closing tag is found on the very same
 * line, the tag is treated as a self-closing, single-line component and its
 * slot text is converted as inline-only markdown immediately (no children).
 * Otherwise the tag opens a multi-line container, closed later by
 * MailComponentParser once a line with only the closing tag is reached.
 */
final class MailComponentStartParser implements BlockStartParserInterface
{
    private const OPEN_TAG  = '/^<mail-([a-z][a-z0-9-]*)((?:\s+[a-zA-Z_:][-a-zA-Z0-9_:.]*(?:\s*=\s*"[^"]*")?)*)\s*>/i';
    private const ATTRIBUTE = '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*"([^"]*)"/';

    private ?MarkdownConverter $inlineConverter = null;

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        $tmpCursor = clone $cursor;
        $tmpCursor->advanceToNextNonSpaceOrTab();
        $remainder = $tmpCursor->getRemainder();

        if (preg_match(self::OPEN_TAG, $remainder, $match) !== 1) {
            return BlockStart::none();
        }

        $tag        = strtolower($match[1]);
        $attributes = $this->parseAttributes($match[2]);
        $afterOpen  = substr($remainder, strlen($match[0]));
        $closeTag   = '</mail-' . $tag . '>';
        $closePos   = stripos($afterOpen, $closeTag);

        $cursor->advanceToNextNonSpaceOrTab();

        if ($closePos !== false && trim(substr($afterOpen, $closePos + strlen($closeTag))) === '') {
            $inner = substr($afterOpen, 0, $closePos);
            $node  = new MailComponentNode($tag, $attributes, $this->convertInline($inner));
            $cursor->advanceBy(strlen($match[0]) + $closePos + strlen($closeTag));

            return BlockStart::of(new MailComponentParser($node, true))->at($cursor);
        }

        $node = new MailComponentNode($tag, $attributes);
        $cursor->advanceBy(strlen($match[0]));

        return BlockStart::of(new MailComponentParser($node, false))->at($cursor);
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $raw): array
    {
        preg_match_all(self::ATTRIBUTE, $raw, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as $match) {
            $attributes[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);
        }

        return $attributes;
    }

    /**
     * Converts a single-line component's slot text as inline-only markdown
     * (emphasis, links, code spans, etc.) with no block-level wrapping -
     * nested `<mail-*>` tags are not supported here, only in the multi-line
     * container form.
     */
    private function convertInline(string $text): string
    {
        $this->inlineConverter ??= new MarkdownConverter(
            (new Environment())->addExtension(new InlinesOnlyExtension()),
        );

        return trim((string) $this->inlineConverter->convert($text));
    }
}
