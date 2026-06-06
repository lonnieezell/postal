<@php

namespace {namespace};

use Myth\Postal\Mailable;

class {class} extends Mailable
{
    /**
     * Composes the message. Runs lazily when the mailable is sent.
     */
    protected function build(): void
    {
        $this->subject('')
            ->html('');
    }
}
