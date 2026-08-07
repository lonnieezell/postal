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

namespace Myth\Postal\Config;

use CodeIgniter\Config\BaseConfig;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;

/**
 * Configuration for the Postal package features that live outside the mailer
 * itself, starting with the in-browser Mailable preview.
 */
class Postal extends BaseConfig
{
    /**
     * Master switch for the in-browser Mailable preview. Off by default so that
     * installing the package exposes nothing until the host app opts in. Even
     * when true, previews are never served in the production environment. May be
     * overridden from .env via "postal.enablePreview".
     */
    public bool $enablePreview = false;

    /**
     * The URI prefix the preview routes are mounted under.
     */
    public string $previewPath = 'postal/preview';

    /**
     * Namespace => filesystem-path pairs scanned for Mailables implementing
     * Previewable. Each path is globbed for classes in the paired namespace.
     *
     * @var array<string, string>
     */
    public array $mailableNamespaces = [
        'App\Mails' => APPPATH . 'Mails',
    ];

    /**
     * CommonMark extensions loaded by service('markdown'), in addition to the
     * always-on CommonMarkCoreExtension.
     *
     * @var array<int, class-string>
     */
    public array $markdownExtensions = [
        GithubFlavoredMarkdownExtension::class,
    ];

    /**
     * Whether the preview is reachable in the given environment. Two independent
     * locks must both pass: the environment must not be production AND the
     * enablePreview flag must be set.
     */
    public function previewEnabled(string $environment): bool
    {
        return $environment !== 'production' && $this->enablePreview;
    }
}
