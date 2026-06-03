# Postal

**A modern, driver-based email library for CodeIgniter 4.**

Postal replaces CodeIgniter 4's monolithic `Email` class with a clean transport-driver
architecture: compose a message once, send it through SMTP, Sendmail, PHP `mail()`, a log,
or (soon) first-class HTTP API providers like SES, Mailgun, Postmark, and Resend — without
changing your application code. It keeps the familiar CI4 email API working for a painless
migration, and it's fully testable without ever sending a real message.

> **Status:** Early development (`0.x`). The API is settling and may change before `1.0`.
> Phase 1 (the core spine + SMTP-era transports) is the current focus — see the
> [roadmap](#roadmap).

---

## Why Postal?

- **Driver-based transports** — swap how mail is delivered via config, not code.
- **Drop-in friendly** — existing `service('email')` calls keep working through a
  compatibility adapter.
- **API providers as first-class transports** *(Phase 2)* — SES, Mailgun, Postmark, Resend
  over their HTTP APIs, no SMTP relay required.
- **Zero required dependencies** — PHP standard extensions only. No database, no queue, no
  view renderer needed to send mail.
- **Built for testing** — a fake transport and expressive assertions let you verify mail in
  PHPUnit without touching the network.
- **Extensible** — clean `TransportInterface` and optional contracts that companion packages
  (campaigns, tracking, templates) plug into.

---

## Requirements

- PHP 8.2+
- CodeIgniter 4.7+
- Extensions: `ext-openssl`, `ext-mbstring` (and `ext-curl` for the API transports in Phase 2)

---

## Installation

```bash
composer require myth/postal
```

CodeIgniter auto-discovers the package — no manual wiring required. Publish a local config
to customize transports:

```bash
php spark email:test you@example.com   # send a test message via the default transport
```

---

## Quick Start

### Compose and send

```php
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
service('mailer')->mailer('ses')->send($email);   // use the 'ses' mailer for this send
```

### Mailable classes

For reusable, testable messages, extend `Mailable`:

```php
use Myth\Postal\Mailable;

class WelcomeEmail extends Mailable
{
    public function __construct(private User $user) {}

    public function build(): void
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
$email = service('email');
$email->setTo('user@example.com');
$email->setSubject('Hello');
$email->setMessage('<p>Hi there.</p>');
$email->send();
```

---

## Transports

| Transport  | Config name | Status      |
|------------|-------------|-------------|
| SMTP       | `smtp`      | Phase 1     |
| Sendmail   | `sendmail`  | Phase 1     |
| PHP `mail()` | `mail`    | Phase 1     |
| Log        | `log`       | Phase 1     |
| Null       | `null`      | Phase 1     |
| Fake (testing) | —       | Phase 1     |
| Amazon SES | `ses`       | Phase 2     |
| Mailgun    | `mailgun`   | Phase 2     |
| Postmark   | `postmark`  | Phase 2     |
| Resend     | `resend`    | Phase 2     |
| Failover   | `failover`  | Phase 3     |
| DKIM signing (decorator) | — | Phase 3 |

Register your own transport by adding it to the `$transports` map in `Config\Email`.

---

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

See the [documentation](#documentation) for the full configuration reference, including
global message defaults, validation, and per-mailer DKIM settings.

---

## Events

Postal fires events through CodeIgniter's `Events` system at each stage of the send pipeline:

| Event             | Fired                                   | Cancellable |
|-------------------|-----------------------------------------|-------------|
| `email.composing` | before defaults/suppression are applied | No          |
| `email.sending`   | just before the transport sends         | **Yes**     |
| `email.sent`      | after a successful send                 | No          |
| `email.failed`    | after a failed send                     | No          |
| `email.suppressed`| per recipient removed by a suppression list | No      |

Returning `false` from an `email.sending` listener cancels the send. Event emission can be
disabled with `Config\Email::$fireEvents = false`.

---

## Testing

Swap in the fake transport and assert against what *would* have been sent — no network, no
real mail:

```php
use Myth\Postal\Mailer;

$fake = Mailer::fake();

(new WelcomeEmail($user))->send();

$fake->assertSent(WelcomeEmail::class);
$fake->assertSentTo($user->email);
$fake->assertSent(fn ($message) => str_contains($message->subject, 'Welcome'));
$fake->assertSentCount(1);
```

---

## Roadmap

Postal ships in phases, each independently usable:

- **Phase 1 — Core + SMTP-era transports** *(current)*: message composition, MIME rendering,
  the `Mailer`/`MailerManager` pipeline, events, the SMTP/Sendmail/Mail/Log/Null transports,
  the fake transport, and the backward-compatible adapter.
- **Phase 2 — API transports**: SES, Mailgun, Postmark, and Resend over HTTP.
- **Phase 3 — Composition & signing**: failover transport and local DKIM signing.
- **Phase 4 — Developer experience**: `Mailable` assertions, `make:mailable`, and `email:test`.

The full design proposal lives in
[issue #1](https://github.com/lonnieezell/postal/issues/1).

---

## Documentation

Full documentation ships with the project and is built with
[Material for MkDocs](https://squidfunk.github.io/mkdocs-material/) from the [`docs/`](docs/)
directory.

Preview locally (requires Python 3):

```bash
pip3 install mkdocs mkdocs-material
mkdocs serve   # http://127.0.0.1:8000
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
