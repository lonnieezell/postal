# Postal

A modern email library for CodeIgniter 4.

Write a message once and send it through SMTP, Amazon SES, Resend, sendmail or a failover chain. You choose the provider in config, not code. Existing `service('email')` calls keep working, so you can adopt Postal one step at a time.

```bash
composer require myth/postal
```

Requires PHP 8.2+ and CodeIgniter 4.7+. See [Installation](installation.md) for extensions and configuration.

## See it in action

=== "Mailable"

    ```php
    <?php

    namespace App\Mails;

    use Myth\Postal\Mailable;

    class Welcome extends Mailable
    {
        public function __construct(private readonly string $address)
        {
            parent::__construct();
        }

        protected function build(): void
        {
            $this->from('hello@example.com', 'Acme')
                ->to($this->address)
                ->subject('Welcome aboard')
                ->markdown('emails/welcome');
        }
    }

    $result = (new Welcome('user@example.com'))->send();
    ```

=== "Email builder"

    ```php
    <?php

    use Myth\Postal\Email;

    $email = (new Email())
        ->from('hello@example.com', 'Acme')
        ->to('user@example.com')
        ->subject('Welcome aboard')
        ->html('<p>Glad to have you with us.</p>');

    $result = service('mailer')->send($email);
    ```

Either way, `send()` returns a [`SendResult`](reference/send-result.md) instead of a bare `bool`:

```php
<?php

if (! $result->success) {
    log_message('error', 'Welcome email failed: ' . $result->error);
}
```

Out of the box Postal's default mailer discards every message, so nothing is sent by accident. The [Quick Start](quick-start.md) sets up a mailer in a few lines.

## Why Postal

<div class="grid cards" markdown>

-   **Deliver anywhere**

    ---

    [SMTP](smtp-mailer.md), [sendmail](sendmail-mail-mailers.md), [Amazon SES](ses-mailer.md) and [Resend](resend-mailer.md), with [failover](failover-mailer.md) between them and [DKIM signing](dkim-signing.md). Or [write your own transport](custom-transports.md).

-   **Write mail as classes**

    ---

    [Mailables](mailables.md) keep each email in one class, written in HTML or [Markdown](markdown-mailables.md) with a shared layout, and [previewed in the browser](previewing.md).

-   **Send safely**

    ---

    A [suppression list](suppression-unsubscribe.md) filters recipients and `List-Unsubscribe` headers are added for you. [Events](events.md) let you change or stop any send.

-   **Test without sending**

    ---

    [`Mailer::fake()`](testing.md) records every message so your tests can assert on it, with no network access.

</div>

## Where to go next

<div class="grid cards" markdown>

-   **New to Postal?**

    ---

    Install, configure a mailer and send your first Mailable in the [Quick Start](quick-start.md).

-   **Already using CI4's `Email`?**

    ---

    Move over in stages, without rewriting your app, with [Migrating from CI4's Email](migrating-from-ci4.md).

-   **Looking something up?**

    ---

    Every method and setting is in the reference: [Email](reference/email.md), [SendResult](reference/send-result.md) and [Config\Mailer](reference/config-mailer.md).

-   **Want the big picture?**

    ---

    [How Postal Works](how-postal-works.md) explains mailers, transports and what happens during a send.

</div>

## Support Postal

Postal is free and open source, built and maintained by [Lonnie Ezell](https://github.com/lonnieezell). If it saves you time, please consider sponsoring its development on GitHub. Sponsorship helps fund new features, fixes and these docs.

[Sponsor on GitHub](https://github.com/sponsors/lonnieezell){ .md-button .md-button--primary }
