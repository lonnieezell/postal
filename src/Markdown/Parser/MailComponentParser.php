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

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;
use Myth\Postal\Markdown\Node\MailComponentNode;

/**
 * Continues a MailComponentNode block. A single-line self-closing tag is
 * already finished at construction time (no children). A multi-line tag
 * stays open as a container - letting its slot content parse as normal
 * child blocks, including nested `<mail-*>` tags - until a line consisting
 * solely of its matching closing tag is reached.
 */
final class MailComponentParser extends AbstractBlockContinueParser
{
    private readonly string $closeTagPattern;

    public function __construct(
        private readonly MailComponentNode $block,
        private bool $finished,
    ) {
        $this->closeTagPattern = '/^\s*<\/mail-' . preg_quote($block->tag, '/') . '>\s*$/i';
    }

    public function getBlock(): MailComponentNode
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($this->finished) {
            return BlockContinue::none();
        }

        if (preg_match($this->closeTagPattern, $cursor->getLine()) === 1) {
            $this->finished = true;

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
