# Installation

## Requirements

- PHP 8.2+
- CodeIgniter 4.7+
- Extensions: `ext-openssl`, `ext-mbstring` (and `ext-curl` for the HTTP API transports)

## Install via Composer

```bash
composer require myth/postal
```

CodeIgniter auto-discovers the package through Composer — no manual registration is required.

## Verify the install

Send a test message through the default transport:

```bash
php spark email:test you@example.com
```

The command runs the full send pipeline and prints the result (provider message ID, or the
error if it failed).

## Configuration

Transports are defined as named **mailers** in `Config\Mailer`, with a `$default` chosen per
environment:

```php
<?php

public string $default = match (ENVIRONMENT) {
    'production' => 'ses',
    'testing'    => 'null',
    default      => 'log',
};

public array $mailers = [
    'smtp' => [
        'transport'  => 'smtp',
        'host'       => 'localhost',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   => '',
        'password'   => '',
    ],
    'log'  => ['transport' => 'log'],
    'null' => ['transport' => 'null'],
];
```

To override the defaults in your application, publish the config file:

```bash
php spark publish
```

That writes `app/Config/Mailer.php` — a `Config\Mailer` class extending the package's
`Myth\Postal\Config\Mailer`. Redeclare only the properties you want to change; everything else
is inherited. Postal resolves the application's copy in preference to its own, so the settings
you put there are the ones that take effect.

Publishing never overwrites an existing `app/Config/Mailer.php`, so it is safe to re-run after
upgrading the package.

The same `php spark publish` run also copies the default email Layout and Mail Components —
`app/Views/mail/layouts/default.php` and `app/Views/mail/components/{button,panel}.php` — so you
can restyle them to match your brand. As with the config file, publishing never overwrites a copy
you've already customized. See [Markdown Mailables](markdown-mailables.md) for what those files
do.

The full configuration reference is covered in later sections of the documentation.
