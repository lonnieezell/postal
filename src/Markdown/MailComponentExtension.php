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

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use Myth\Postal\Markdown\Node\MailComponentNode;
use Myth\Postal\Markdown\Parser\MailComponentStartParser;
use Myth\Postal\Markdown\Renderer\MailComponentRenderer;

/**
 * Parses `mail-*`-prefixed tags (e.g. <mail-button>, <mail-panel>) as real
 * CommonMark AST nodes and renders each via a view() call, rather than
 * regex pre-processing or post-hoc DOM manipulation. Registered at a higher
 * priority than the core HTML block/heading/blockquote parsers so it claims
 * `<mail-*>` tags before they're swallowed as raw HTML passthrough.
 */
final readonly class MailComponentExtension implements ExtensionInterface
{
    /**
     * @param array<int, class-string<ExtensionInterface>> $markdownExtensions
     */
    public function __construct(
        private string $componentViewPath,
        private array $markdownExtensions = [],
    ) {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addBlockStartParser(new MailComponentStartParser($this->markdownExtensions), 100)
            ->addRenderer(MailComponentNode::class, new MailComponentRenderer($this->componentViewPath));
    }
}
