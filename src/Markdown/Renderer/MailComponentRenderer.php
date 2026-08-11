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
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Markdown\Node\MailComponentNode;
use Myth\Postal\Markdown\PackageView;

/**
 * Renders a MailComponentNode by calling view() on its resolved component
 * view, passing tag attributes as $data and the (already markdown-converted)
 * slot content as the reserved $slot variable.
 */
final readonly class MailComponentRenderer implements NodeRendererInterface
{
    /**
     * Attributes each Default Theme component requires. Checked before
     * view() is called - checking inside the view itself would throw from
     * within CodeIgniter's output-buffered include, leaving a dangling
     * output buffer open on the way out.
     *
     * @var array<string, list<string>>
     */
    private const REQUIRED_ATTRIBUTES = [
        'button' => ['url'],
    ];

    public function __construct(private string $componentViewPath)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        MailComponentNode::assertInstanceOf($node);
        assert($node instanceof MailComponentNode);

        foreach (self::REQUIRED_ATTRIBUTES[$node->tag] ?? [] as $attribute) {
            if (! array_key_exists($attribute, $node->attributes)) {
                throw PostalException::forMissingComponentAttribute($node->tag, $attribute);
            }
        }

        $slot = $node->inlineSlot ?? $this->unwrapSingleParagraph($childRenderer->renderNodes($node->children()));

        $view = PackageView::resolve(trim($this->componentViewPath, '/') . '/' . $node->tag);

        // saveData is off so each component renders against only its own
        // attributes: CodeIgniter's renderer otherwise carries data between
        // render() calls, letting an optional attribute on one component leak
        // into the next one that omits it.
        return view($view, [...$node->attributes, 'slot' => $slot], ['debug' => false, 'saveData' => false]);
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
