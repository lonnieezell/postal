# The Sendmail & Mail Mailers

Not every app talks SMTP directly. When your server already runs a mail transfer agent — Postfix, Exim, or a `sendmail` shim — the quickest path to the outbox is to hand the message to it and let it do the delivery. Postal offers two transports for exactly that: `sendmail` pipes the rendered message to a sendmail binary, and `mail` passes it through PHP's native `mail()` function.

Both are thin wrappers around the same rendered MIME the `smtp` transport produces. Reach for them when a local MTA is already configured; reach for [the SMTP mailer](smtp-mailer.md) when you need to authenticate to a remote server, use encryption, or keep a connection alive across a batch.

## The Sendmail mailer

The `sendmail` transport opens the configured binary and writes the complete message to it. It invokes the binary with `-t`, so sendmail reads the recipients straight from the message headers.

Add a named mailer pointing at the `sendmail` transport and tell it where the binary lives:

```php
<?php

public array $mailers = [
    'sendmail' => [
        'transport' => 'sendmail',
        'path'      => '/usr/sbin/sendmail',
    ],
];
```

Send through it by name, exactly like any other mailer:

```php
<?php

use Myth\Postal\Email;

$email = (new Email())
    ->from('you@example.com', 'Your Name')
    ->to('user@example.com')
    ->subject('Welcome aboard')
    ->html('<p>Glad to have you with us.</p>');

$result = service('mailer')->mailer('sendmail')->send($email);
```

### Configuration reference

| Setting | Default | What it does |
|---------|---------|--------------|
| `path` | `/usr/sbin/sendmail` | Absolute path to the sendmail binary. |

That's the only setting. The binary is run as `sendmail -oi -t`, with `-oi` so a lone `.` in the body never ends the message early, and `-t` so recipients come from the `To`, `Cc`, and `Bcc` headers.

## The Mail mailer

The `mail` transport delivers through PHP's built-in `mail()`. It renders the message, then splits it so the recipients and subject go in as `mail()`'s own arguments and the remaining headers and body follow — the shape `mail()` expects.

It takes no settings, so the mailer entry is just the transport name:

```php
<?php

public array $mailers = [
    'mail' => ['transport' => 'mail'],
];
```

```php
<?php

$result = service('mailer')->mailer('mail')->send($email);
```

Whether `mail()` itself reaches a real MTA is a matter of your PHP configuration (`sendmail_path` in `php.ini`). Postal hands the message off correctly; the rest is up to your server.

!!! note "Make either one the default"
    Set `public string $default = 'sendmail';` (or `'mail'`) in your `Config\Email` to route every `service('mailer')->send($email)` call through it without naming the mailer each time.

## Blind recipients (Bcc)

Both transports deliver to `Bcc` recipients. Because they let the MTA fan out the envelope, the blind addresses travel as a `Bcc` header that the MTA delivers to and then strips before the message goes on the wire — so the recipients each receive their copy without ever seeing one another.

```php
<?php

$email->bcc('audit@example.com');
```

Nothing extra to configure: add blind recipients to the message and they're handled.

## The envelope sender

Both transports set the envelope sender (the bounce address) with sendmail's `-f` flag. The address is your message's return path when you set one, falling back to the `From` address otherwise:

```php
<?php

$email->from('you@example.com')
    ->returnPath('bounces@example.com'); // -f bounces@example.com
```

The address is validated and shell-escaped before it reaches the command line, so it can't inject extra arguments. An address that isn't a valid email is dropped rather than passed through, and the MTA falls back to its own default sender.

## Testing without the system

Neither transport touches the real `popen`/`mail()` machinery directly — each talks through a small seam, so the delivery logic is fully unit-testable.

- **Sendmail** writes through `Myth\Postal\Transport\SendmailProcess`. The shipped `PopenProcess` opens the real binary; pass your own implementation as the transport's second argument to capture what would have been written.
- **Mail** calls through `Myth\Postal\Transport\MailFunction`. The shipped `NativeMailFunction` invokes `mail()`; pass your own to assert on the arguments.

```php
<?php

use Myth\Postal\Transport\MailTransport;
use Myth\Postal\Transport\SendmailTransport;

$sendmail = new SendmailTransport(['path' => '/usr/sbin/sendmail'], $myFakeProcess);
$mail     = new MailTransport([], $myFakeMail);
```

Postal's own suite uses doubles for exactly this. They live in the package's `tests/` directory — internal test plumbing, not public API, so don't rely on them from your application.

## Security

!!! danger "Trust the MTA, not the input"
    - **Shell injection is blocked.** The envelope sender is validated and escaped before it reaches the command line; only the configured binary `path` (which you control) is used verbatim. Keep `path` out of user input.
    - **Header injection is blocked.** CR/LF is stripped from every rendered header and recipient, so an address or subject can't smuggle extra headers. Still, validate addresses at your application boundary.
    - **`mail()` reaches a shell.** On most systems PHP's `mail()` ultimately runs sendmail. The same envelope-sender escaping applies, but treat the whole path as security-sensitive and never feed it unvalidated input.

## Next steps

- [The SMTP Mailer](smtp-mailer.md) — authenticate to a remote server with encryption and keep-alive
- [The Log Mailer](log-mailer.md) — inspect messages in development without sending them
- [MIME Rendering](mime-rendering.md) — what the message body looks like on the wire
