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

namespace Myth\Postal;

/**
 * Marks a Mailable as previewable in the browser. The static factory returns an
 * instance composed with sample/fake data, keeping the sample colocated with the
 * Mailable so it stays discoverable and version-controlled.
 */
interface Previewable
{
    /**
     * Returns a Mailable instance built from sample data, ready to render in the
     * preview UI.
     */
    public static function previewInstance(): static;
}
