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

namespace Myth\Postal\Markdown\Node;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * AST node for a `<mail-{tag}>` component tag. Multi-line tags carry their
 * slot content as normal child block nodes (parsed by the standard block
 * engine, so nested markdown/components work); single-line self-closing
 * tags carry their already-inline-converted slot content in $inlineSlot
 * instead, since they never receive children.
 */
final class MailComponentNode extends AbstractBlock
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $tag,
        public readonly array $attributes,
        public readonly ?string $inlineSlot = null,
    ) {
        parent::__construct();
    }
}
