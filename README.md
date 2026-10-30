# Postal

[![PHPUnit](https://github.com/lonnieezell/postal/actions/workflows/phpunit.yml/badge.svg)](https://github.com/lonnieezell/postal/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/lonnieezell/postal/actions/workflows/phpstan.yml/badge.svg)](https://github.com/lonnieezell/postal/actions/workflows/phpstan.yml)
[![Latest Version](https://img.shields.io/packagist/v/myth/postal?include_prereleases)](https://packagist.org/packages/myth/postal)
[![License](https://img.shields.io/packagist/l/myth/postal)](LICENSE)

**A modern email library for CodeIgniter 4.**

Write a message once and send it through SMTP, Amazon SES, Resend, sendmail or a failover chain.
You choose the provider in config, not code. Mailables, Markdown emails, DKIM signing,
suppression lists and test fakes are built in, and existing `service('email')` calls keep
working, so you can adopt Postal one step at a time.

> **Status:** 1.0 beta. The API is stable and ready for use; any breaking change before the
> 1.0 release will be called out in the [changelog](https://lonnieezell.github.io/postal/changelog/).

## 📖 Documentation

**[lonnieezell.github.io/postal](https://lonnieezell.github.io/postal/)**

- [Quick Start](https://lonnieezell.github.io/postal/quick-start/): configure a mailer and send your first email
- [Migrating from CI4's Email](https://lonnieezell.github.io/postal/migrating-from-ci4/): move an existing app over in stages

## Installation

Requires PHP 8.2+ and CodeIgniter 4.7+.

```bash
composer require myth/postal
```

## Quick look

```php
<?php

use Myth\Postal\Email;

$email = (new Email())
    ->from('hello@example.com', 'Acme')
    ->to('user@example.com')
    ->subject('Welcome aboard')
    ->html('<p>Glad to have you with us.</p>');

$result = service('mailer')->send($email);

if (! $result->success) {
    log_message('error', 'Welcome email failed: ' . $result->error);
}
```

Out of the box the default mailer discards every message, so nothing is sent by accident. The
[Quick Start](https://lonnieezell.github.io/postal/quick-start/) sets up a real one.

## Contributing

Contributions are welcome. Run `composer ci` (style, static analysis and tests) before opening a
pull request, and cover new behavior with tests. Every Composer script has a Docker equivalent
prefixed with `docker:`, such as `composer docker:ci`.

## Support Postal

Postal is free and open source, built and maintained by
[Lonnie Ezell](https://github.com/lonnieezell). If it saves you time, please consider
[sponsoring its development on GitHub](https://github.com/sponsors/lonnieezell). Sponsorship
helps fund new features, fixes and the documentation.

## License

Released under the [MIT License](LICENSE).
