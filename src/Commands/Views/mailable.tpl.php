<@php

namespace {namespace};

use Myth\Postal\Mailable;
use Myth\Postal\Previewable;

class {class} extends Mailable implements Previewable
{
    /**
     * Returns an instance built from sample data for the browser preview.
     */
    public static function previewInstance(): static
    {
        // TODO: supply sample data, e.g. return new static($sampleUser);
        return new static();
    }

    /**
     * Composes the message. Runs lazily when the mailable is sent.
     */
    protected function build(): void
    {
        $this->subject('')
            ->html('');
    }
}
