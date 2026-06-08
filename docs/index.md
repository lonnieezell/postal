# Postal

A modern, driver-based email library for CodeIgniter 4.

Postal replaces CodeIgniter 4's monolithic `Email` class with a clean transport-driver
architecture: compose a message once and send it through SMTP, Sendmail, PHP `mail()`,
Amazon SES, or a failover chain — without changing your application code. It keeps the
familiar CI4 email API working for a painless migration, and it's fully testable without ever
sending a real message.

## Highlights

- **Driver-based transports** — change how mail is delivered through configuration, not code.
- **Amazon SES** — deliver through SesV2 with SigV4 signing; no SMTP relay required.
- **DKIM signing** — sign outbound messages at the library level with a one-line config
  addition.
- **Failover chain** — wrap mailers in a priority-ordered fallback so a provider outage
  doesn't drop mail.
- **Suppression lists & unsubscribe** — filter recipients before send and auto-inject
  `List-Unsubscribe` headers.
- **Inline image embedding** — local paths and `data:` URIs in HTML are automatically
  rewritten to `cid:` attachments.
- **Mailable classes** — reusable, class-based emails with browser preview in development.
- **Drop-in friendly** — existing `service('email')` calls keep working via a compatibility
  adapter.
- **Zero required dependencies** — PHP standard extensions only; no database, queue, or view
  renderer needed to send mail.
- **Built for testing** — a fake transport and expressive assertions verify mail in PHPUnit
  with no network access.

## A quick taste

```php
<?php

use Myth\Postal\Email;

$email = (new Email())
    ->from('you@example.com', 'Your Name')
    ->to('user@example.com')
    ->subject('Welcome aboard')
    ->html('<p>Glad to have you with us.</p>');

$result = service('mailer')->send($email);
```

Continue to [Quick Start](quick-start.md) to get started.
