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

Transports are defined as named **mailers** in `Config\Email`, with a `$default` chosen per
environment:

```php
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

To override the defaults in your application, copy the config into `app/Config/Email.php` (in
your application's namespace) and adjust the values. The full configuration reference is
covered in later sections of the documentation.
