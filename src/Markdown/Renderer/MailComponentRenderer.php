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

namespace Myth\Postal\Markdown\Renderer;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Myth\Postal\Markdown\Node\MailComponentNode;
use Myth\Postal\Markdown\PackageView;

/**
 * Renders a MailComponentNode by calling view() on its resolved component
 * view, passing tag attributes as $data and the (already markdown-converted)
 * slot content as the reserved $slot variable.
 */
final readonly class MailComponentRenderer implements NodeRendererInterface
{
    public function __construct(private string $componentViewPath)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        MailComponentNode::assertInstanceOf($node);
        assert($node instanceof MailComponentNode);

        $slot = $node->inlineSlot ?? $this->unwrapSingleParagraph($childRenderer->renderNodes($node->children()));

        $view = PackageView::resolve(trim($this->componentViewPath, '/') . '/' . $node->tag);

        return view($view, [...$node->attributes, 'slot' => $slot], ['debug' => false]);
    }

    /**
     * A slot whose converted content is a single paragraph is unwrapped so
     * inline usages (e.g. a button's label) aren't forced inside a <p>.
     * Multi-paragraph slots (e.g. a panel with several paragraphs) are left
     * as-is.
     */
    private function unwrapSingleParagraph(string $html): string
    {
        if (preg_match('/^<p>((?:(?!<\/?p(?:>| )).)*)<\/p>\n?$/s', $html, $match) === 1) {
            return $match[1];
        }

        return $html;
    }
}
