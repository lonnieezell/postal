# Migrating from CI4's Email

This guide moves an existing CodeIgniter 4 app from the framework's `Email` class onto Postal. It works in three stages. Each one leaves your app sending mail, so you can stop after any of them.

| Stage | What changes | Code changes |
|-------|--------------|--------------|
| 1. Install | `service('email')` is now Postal's adapter, still configured by `Config\Email` | None |
| 2. Move settings | Delivery is configured in `Config\Mailer` | Config only |
| 3. Rewrite call sites | Code uses Postal's `Email` or Mailables directly | Optional, file by file |

## Stage 1: Install

```bash
composer require myth/postal
```

That's the whole stage. `service('email')` now returns Postal's `LegacyEmailAdapter`, which accepts the same calls as CodeIgniter's `Email` class and reads your existing `app/Config/Email.php`. [CodeIgniter Shield](https://github.com/codeigniter4/shield)'s emails keep working too.

Check that mail still goes out by sending something real through `service('email')`, such as a Shield password reset.

Don't use `php spark email:test` yet. It sends through Postal's default mailer, which is `null` until Stage 2, so it reports success without sending anything.

## Stage 2: Move delivery settings into Config\Mailer

Publish the config:

```bash
php spark publish
```

!!! warning "Publishing switches the adapter over"
    Once `app/Config/Mailer.php` exists, `service('email')` sends through its **default mailer** and ignores the delivery keys in `Config\Email` (`protocol`, `SMTP*`, `mailPath`, `DSN`). The package default is `null`, which discards mail, so set `$default` in the same change.

Turn your `Config\Email` settings into a mailer. `LegacyConfigMapper` does the conversion with the same rules the adapter used in Stage 1, so mail goes out exactly as before:

```php
<?php

use Myth\Postal\LegacyConfigMapper;

print_r(LegacyConfigMapper::toMailer((array) config(\Config\Email::class)));
// ['transport' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, ...]
```

Copy the output into `app/Config/Mailer.php` and make it the default. Move the password into the constructor so it stays out of the committed file:

```php
<?php

namespace Config;

use Myth\Postal\Config\Mailer as PostalMailer;

class Mailer extends PostalMailer
{
    public string $default = 'smtp';

    public array $mailers = [
        'smtp' => [
            'transport'  => 'smtp',
            'host'       => 'smtp.example.com',
            'port'       => 587,
            'encryption' => 'tls',
            'authType'   => 'login',
            'timeout'    => 5,
        ],
        'log'  => ['transport' => 'log'],
        'null' => ['transport' => 'null'],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->mailers['smtp']['username'] = env('SMTP_USER');
        $this->mailers['smtp']['password'] = env('SMTP_PASS');
    }
}
```

Run `php spark email:test you@example.com` again. This time it goes through the same mailer as `service('email')`.

`fromEmail`, `fromName`, `mailType`, `wordWrap`, `priority` and the BCC batch settings stay in `Config\Email`: the adapter still reads them.

## Stage 3: Rewrite call sites (optional)

The adapter can stay forever. Rewrite call sites when you want what Postal adds: a [`SendResult`](reference/send-result.md) instead of a `bool`, named mailers, or [Mailables](mailables.md) you can preview and assert on by class.

Most calls map one to one:

| CodeIgniter `Email` | Postal `Email` |
|---------------------|----------------|
| `setFrom($email, $name)` | `from($email, $name)` |
| `setReplyTo($email, $name)` | `replyTo($email, $name)` |
| `setTo($to)` | `to($to)` |
| `setCC($cc)` / `setBCC($bcc)` | `cc($cc)` / `bcc($bcc)` |
| `setSubject($subject)` | `subject($subject)` |
| `setMailType('html')` + `setMessage($body)` | `html($body)` |
| `setMessage($body)` (text mail) | `text($body)` |
| `setAltMessage($text)` | `text($text)` |
| `setHeader($name, $value)` | `header($name, $value)` |
| `setPriority($level)` | `priority($level)` |
| `attach($path)` | `attach($path)` |
| `setAttachmentCID($path)` | `embedImage($path, $cid)` |
| `send()` → `bool` | `service('mailer')->send($email)` → `SendResult` |
| `printDebugger()` | `$result->error`, or the [log mailer](log-mailer.md) |

Before:

```php
<?php

$email = service('email');

$email->setFrom('hello@example.com', 'Acme')
    ->setTo($user->email)
    ->setSubject('Your receipt')
    ->setMailType('html')
    ->setMessage(view('emails/receipt', ['order' => $order]));

if (! $email->send()) {
    log_message('error', $email->printDebugger(['headers']));
}
```

After:

```php
<?php

use Myth\Postal\Email;

$email = (new Email())
    ->from('hello@example.com', 'Acme')
    ->to($user->email)
    ->subject('Your receipt')
    ->html(view('emails/receipt', ['order' => $order]));

$result = service('mailer')->send($email);

if (! $result->success) {
    log_message('error', 'Receipt failed: ' . $result->error);
}
```

Postal's `from()` and `to()` throw a `PostalException` for a malformed address, where the adapter quietly returned `false` from `send()`. Validate user-supplied addresses first, or catch the exception.

If the same message is sent from more than one place, make it a [Mailable](mailables.md) instead.

## Update your tests

`CIUnitTestCase` replaces `service('email')` with CodeIgniter's `MockEmail` before every test. Existing tests keep passing, but they never reach Postal.

To test through Postal, remove `mockEmail` from the test case's set-up methods and fake the mailer:

```php
<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Mailer;

final class ReceiptTest extends CIUnitTestCase
{
    protected $setUpMethods = ['resetFactories', 'mockCache', 'mockSession'];

    public function testReceiptIsSent(): void
    {
        $fake = Mailer::fake();

        // ...code that sends the receipt...

        $fake->assertSentTo('customer@example.com');
    }
}
```

!!! danger "Finish Stage 2 first"
    `Mailer::fake()` only catches adapter sends once `Config\Mailer` is published. Before that, the adapter builds its own transport from `Config\Email`, so a test without `mockEmail` sends **real mail**.

See [Testing](testing.md) for every assertion.

## What behaves differently

The adapter matches CodeIgniter's behaviour closely, but not exactly. The differences most likely to matter:

- **Bad addresses don't throw.** A malformed address is skipped and `send()` returns `false`, whatever your `validate` setting.
- **No in-memory attachments.** `attach()` with file contents instead of a path isn't supported. Write the contents to a file, or use `attachData()` after Stage 3.
- **Events fire.** Adapter sends fire Postal's [events](events.md), so an `email.sending` listener can cancel them.
- **Debugger output differs.** `printDebugger()` shows the same information, but not byte for byte.

[Legacy Compatibility](legacy-compatibility.md) lists everything the adapter does.
