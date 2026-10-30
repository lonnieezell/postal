# Config\Mailer

`Config\Mailer` defines your named mailers and how sends behave. Publish it with `php spark publish` to get `app/Config/Mailer.php`, a `Config\Mailer` class that extends `Myth\Postal\Config\Mailer`, and redeclare only the properties you want to change. See [Installation](../installation.md#configuration).

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$default` | `string` | `'null'` | The mailer used when you don't name one. The `null` mailer discards every message. |
| `$fireEvents` | `bool` | `true` | Fires the email [events](../events.md), including `email.suppressed`. `false` silences all of them. |
| `$suppressionList` | `?class-string` | `null` | A class implementing `SuppressionListInterface`. See [Suppression list](../suppression-unsubscribe.md#suppression-list). |
| `$unsubscribeUrl` | `?class-string` | `null` | A class implementing `UnsubscribeUrlInterface`. See [Unsubscribe headers](../suppression-unsubscribe.md#unsubscribe-headers). |
| `$mailers` | `array` | `null` and `log` mailers | Named mailers, keyed by name. See [Mailer entries](#mailer-entries). |
| `$transports` | `array` | The built-in transports | Transport names mapped to classes. See [Transports](#transports). |

!!! warning "Redeclaring `$mailers` replaces the list"
    The property in your class replaces the package's, so list every mailer you use, including `null` or `log` if you still want them.

## Mailer entries

Each entry in `$mailers` is an array with a `transport` key, naming an entry in `$transports`, plus that transport's settings. The whole entry is passed to the transport, and keys it doesn't use are ignored.

```php
<?php

public array $mailers = [
    'smtp' => [
        'transport' => 'smtp',
        'host'      => 'smtp.example.com',
        'port'      => 587,
    ],
];
```

Naming a mailer that isn't in `$mailers`, or a transport that isn't in `$transports`, throws a `PostalException` when the mailer is first used.

### `null`

No settings. Discards every message and reports success.

### `log`

| Key | Default | Description |
|-----|---------|-------------|
| `level` | `'debug'` | The PSR-3 log level the message is written at. |

See [Log](../log-mailer.md).

### `smtp`

| Key | Default | Description |
|-----|---------|-------------|
| `host` | `'localhost'` | Server hostname or IP. |
| `port` | `25` | Server port. `465` uses implicit TLS automatically. |
| `encryption` | `''` | `'tls'` for STARTTLS, `'ssl'` for implicit TLS. |
| `authType` | none | `'login'`, `'plain'` or `'xoauth2'`. **Without it, Postal doesn't authenticate**, even when `username` and `password` are set. |
| `username` | none | Auth username. |
| `password` | none | Auth password, or the OAuth2 access token for `xoauth2`. |
| `timeout` | `30` | Connection timeout in seconds. |
| `keepAlive` | `false` | Reuse one connection across sends. |
| `helo` | `gethostname()` | The hostname sent in `EHLO`. |
| `dsn` | none | `['notify' => …, 'ret' => …, 'envid' => …]` delivery status notification options. |

See [SMTP](../smtp-mailer.md).

### `sendmail`

| Key | Default | Description |
|-----|---------|-------------|
| `path` | `'/usr/sbin/sendmail'` | Path to the sendmail binary. |

### `mail`

No settings. Delivers through PHP's `mail()`. See [Sendmail & Mail](../sendmail-mail-mailers.md).

### `ses`

Requires `composer require aws/aws-sdk-php`.

| Key | Default | Description |
|-----|---------|-------------|
| `region` | `'us-east-1'` | AWS region. |
| `key` | none | Access key ID. Leave out with `secret` to use the AWS default credential chain. |
| `secret` | none | Secret access key. Used only when `key` is set too. |
| `token` | none | Session token for temporary credentials. |
| `configurationSet` | none | SES configuration set applied to every send. |
| `forceRaw` | `false` | Always send raw MIME. |

See [Amazon SES](../ses-mailer.md).

### `resend`

| Key | Default | Description |
|-----|---------|-------------|
| `key` | **required** | Resend API key. Missing or empty throws a `PostalException`. |

See [Resend](../resend-mailer.md).

### `failover`

| Key | Default | Description |
|-----|---------|-------------|
| `chain` | **required** | Mailer names to try, in order. Missing or empty throws a `PostalException`. |

See [Failover](../failover-mailer.md).

### `dkim`

Add a `dkim` array to an `smtp` or `sendmail` mailer to sign its messages. On any other transport it throws a `PostalException`.

| Key | Description |
|-----|-------------|
| `domain` | The signing domain. |
| `selector` | The DNS selector for the public key. |
| `privateKey` | A path to a PEM file, or the PEM text. |

See [DKIM Signing](../dkim-signing.md).

## Secrets and `.env`

PHP doesn't allow function calls in property defaults, so read secrets in the constructor:

```php
<?php

public function __construct()
{
    parent::__construct();

    $this->mailers['smtp']['username'] = env('SMTP_USER');
    $this->mailers['smtp']['password'] = env('SMTP_PASS');
}
```

Like any CodeIgniter config, values can also be overridden from `.env` with the `mailer.` prefix. For a key inside `$mailers`, the key must already exist in your config:

```ini
mailer.default = smtp
mailer.mailers.smtp.host = smtp.example.com
```

## Transports

`$transports` maps a transport name to a class implementing `Myth\Postal\Transport\TransportInterface`. The defaults are `null`, `log`, `smtp`, `sendmail`, `mail`, `ses`, `resend` and `failover`.

To add your own, register the class and point a mailer at it. Postal constructs it with the mailer entry as its only argument:

```php
<?php

public array $transports = [
    // ...the built-in transports...
    'postmark' => \App\Mail\PostmarkTransport::class,
];

public array $mailers = [
    'postmark' => ['transport' => 'postmark', 'token' => ''],
];
```

Redeclaring `$transports` replaces the package's map too, so copy the built-in entries from `Myth\Postal\Config\Mailer`.

A transport implements two methods:

| Method | Description |
|--------|-------------|
| `send(Email $email): SendResult` | Delivers the message. Return [`SendResult::fail()`](send-result.md#factories) when it's rejected; throw only for configuration or connection errors. |
| `ping(): bool` | Returns `true` when the transport has a usable connection. |

## Related

- [Installation](../installation.md) — publishing the config
- [Config\Postal](config-postal.md) — preview and markdown settings
