# Postal

A modern, driver-based email library for CodeIgniter 4.

Postal replaces CodeIgniter 4's monolithic `Email` class with a clean transport-driver
architecture: compose a message once and send it through SMTP, Sendmail, PHP `mail()`, a log,
or — in later phases — first-class HTTP API providers such as Amazon SES, Mailgun, Postmark,
and Resend. It keeps the familiar CodeIgniter email API working for a painless migration, and
it is fully testable without ever sending a real message.

!!! note "Early development"
    Postal is in `0.x`. The API is still settling and may change before `1.0`. Phase 1 — the
    core pipeline plus the SMTP-era transports — is the current focus.

## Highlights

- **Driver-based transports** — change how mail is delivered through configuration, not code.
- **Drop-in friendly** — existing `service('email')` calls keep working via a compatibility
  adapter.
- **Zero required dependencies** — PHP standard extensions only; no database, queue, or view
  renderer needed to send mail.
- **Built for testing** — a fake transport and expressive assertions verify mail in PHPUnit
  with no network access.
- **Extensible** — a clean `TransportInterface` plus optional contracts for companion
  packages (campaigns, tracking, templates).

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
