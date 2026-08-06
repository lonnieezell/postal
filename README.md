# Postal

**A modern, driver-based email library for CodeIgniter 4.**

Postal replaces CodeIgniter 4's monolithic `Email` class with a clean transport-driver
architecture: compose a message once and send it through SMTP, Sendmail, PHP `mail()`, Amazon
SES, a log, or a failover chain — without changing your application code. It keeps the
familiar CI4 email API working for a painless migration, and it's fully testable without ever
sending a real message.

> **Status:** Public beta (`0.x`). The API is stable and ready for use; breaking changes
> before `1.0` will be rare and called out clearly in the changelog.

---

## Documentation

**[lonnieezell.github.io/postal](https://lonnieezell.github.io/postal/)** — full reference,
guides, and configuration options for every transport.

---

## Why Postal?

- **Driver-based transports** — swap how mail is delivered via config, not code.
- **Drop-in friendly** — existing `service('email')` calls keep working through a
  compatibility adapter.
- **Amazon SES** — deliver through SesV2 with SigV4 signing; no SMTP relay required.
- **DKIM signing** — sign outbound messages at the library level with a one-line config
  addition, no application code changes.
- **Failover chain** — wrap any number of mailers in a priority-ordered fallback so a
  provider outage doesn't drop mail.
- **Suppression lists & unsubscribe** — bind your own suppression list to silently filter
  recipients before send, and auto-inject `List-Unsubscribe` headers.
- **Inline image embedding** — local paths and `data:` URIs in HTML are automatically
  rewritten to `cid:` attachments.
- **Zero required dependencies** — PHP standard extensions only. No database, no queue, no
  view renderer needed to send mail.
- **Built for testing** — a fake transport and expressive assertions let you verify mail in
  PHPUnit without touching the network.
- **Mailable classes** — reusable, class-based emails with browser preview support in
  development.

---

## Requirements

- PHP 8.2+
- CodeIgniter 4.7+
- Extensions: `ext-openssl`, `ext-mbstring`
- `ext-curl` + `aws/aws-sdk-php` for the Amazon SES transport (optional)

---

## Installation

```bash
composer require myth/postal
```

CodeIgniter auto-discovers the package — no manual wiring required.

```bash
php spark email:test you@example.com   # send a test message via the default transport
```

---

## Quick Start

### Compose and send

```php
<?php

use Myth\Postal\Email;

$email = (new Email())
    ->from('you@example.com', 'Your Name')
    ->to('user@example.com')
    ->subject('Welcome aboard')
    ->html('<p>Glad to have you with us.</p>');

$result = service('mailer')->send($email);

if ($result->success) {
    log_message('info', 'Sent message ' . $result->messageId);
}
```

`send()` returns a `SendResult` carrying `success`, the provider `messageId`, any `error`,
and the raw provider response.

### Send through a named transport

```php
<?php

service('mailer')->mailer('ses')->send($email);   // use the 'ses' mailer for this send
```

### Mailable classes

For reusable, testable messages, extend `Mailable`:

```php
<?php

use Myth\Postal\Mailable;

class WelcomeEmail extends Mailable
{
    public function __construct(private User $user)
    {
        parent::__construct();
    }

    protected function build(): void
    {
        $this->to($this->user->email)
             ->subject('Welcome aboard')
             ->html(view('emails/welcome', ['user' => $this->user]));
    }
}

(new WelcomeEmail($user))->send();
```

Scaffold one with:

```bash
php spark make:mailable WelcomeEmail
```

### Backward-compatible API

Existing CodeIgniter email code keeps working unchanged via `service('email')`:

```php
<?php

$email = service('email');
$email->setTo('user@example.com');
$email->setSubject('Hello');
$email->setMessage('<p>Hi there.</p>');
$email->send();
```

---

## Transports

| Transport            | Config name | Available |
|----------------------|-------------|-----------|
| SMTP                 | `smtp`      | ✅        |
| Sendmail             | `sendmail`  | ✅        |
| PHP `mail()`         | `mail`      | ✅        |
| Log                  | `log`       | ✅        |
| Null                 | `null`      | ✅        |
| Fake (testing)       | —           | ✅        |
| Amazon SES           | `ses`       | ✅        |
| Failover             | `failover`  | ✅        |
| DKIM signing (decorator) | —       | ✅        |
| Mailgun              | `mailgun`   | Planned   |
| Postmark             | `postmark`  | Planned   |
| Resend               | `resend`    | Planned   |

Register your own transport by adding it to the `$transports` map in `Config\Mailer`.

---

## Configuration

Run `php spark publish` to write `app/Config/Mailer.php` into your application — a
`Config\Mailer` extending the package's config, which Postal resolves in preference to its
own default. Transports are defined there as named **mailers**, with a `$default` chosen per
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

See the [full documentation](https://lonnieezell.github.io/postal/) for every transport's
configuration reference, DKIM setup, SES credentials, and more.

---

## Events

Postal fires events through CodeIgniter's `Events` system at each stage of the send pipeline:

| Event              | Fired                                       | Cancellable |
|--------------------|---------------------------------------------|-------------|
| `email.composing`  | before defaults/suppression are applied     | No          |
| `email.sending`    | just before the transport sends             | **Yes**     |
| `email.sent`       | after a successful send                     | No          |
| `email.failed`     | after a failed send                         | No          |
| `email.suppressed` | per recipient removed by a suppression list | No          |

Returning `false` from an `email.sending` listener cancels the send. Event emission can be
disabled with `Config\Mailer::$fireEvents = false`.

---

## Testing

Swap in the fake transport and assert against what *would* have been sent — no network, no
real mail:

```php
<?php

use Myth\Postal\Mailer;

$fake = Mailer::fake();

(new WelcomeEmail($user))->send();

$fake->assertSent(WelcomeEmail::class);
$fake->assertSentTo($user->email);
$fake->assertSent(fn ($message) => str_contains($message->subject, 'Welcome'));
$fake->assertSentCount(1);
```

---

## Contributing

Contributions are welcome. The project ships with a full toolchain (PHPUnit, php-cs-fixer,
PHPStan, Rector) runnable locally or in Docker:

```bash
composer test        # run the test suite
composer ci          # style + static analysis + tests (run before opening a PR)
composer cs-fix      # auto-fix coding style
```

Docker equivalents are available for every command by prefixing `docker:`
(e.g. `composer docker:test`), and `docker compose up` starts a dev container with all
required extensions. A pre-commit hook (installed on `composer install`) lints and
auto-formats staged PHP files.

Please ensure `composer ci` passes and new behavior is covered by tests before submitting a
pull request.

---

## License

Released under the [MIT License](LICENSE).
