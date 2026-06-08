# The Log Mailer

Sometimes you don't want to send real email — you just want to *see* what would have gone out. The built-in `log` mailer renders each message to its full MIME form and writes it to your application log instead of delivering it. It's perfect for local development and testing.

## Sending through the log mailer

Postal ships a ready-to-use `log` mailer. Grab it by name and send as usual:

```php
<?php

use Myth\Postal\Email;

$email = (new Email())
    ->from('you@example.com', 'Your Name')
    ->to('user@example.com')
    ->subject('Welcome aboard')
    ->html('<p>Glad to have you with us.</p>');

service('mailer')->mailer('log')->send($email);
```

Nothing leaves your machine. The complete message — headers and body — lands in your log file, where you can confirm the recipients, subject, and rendered MIME are exactly what you expect.

## Making it the default

To route *all* mail to the log while developing, point the `default` mailer at `log` in your `Config\Email`:

```php
<?php

public string $default = 'log';
```

Now `service('mailer')->send($email)` logs instead of sends — no per-call changes needed.

## Choosing the log level

`LogTransport` writes at the `debug` level by default. That keeps full message dumps out of your production `info`-and-above logs. Pick a different level with the `level` key on the `log` mailer in your `Config\Email`:

```php
<?php

'log' => ['transport' => 'log', 'level' => 'info'],
```

Or set it when you construct the transport yourself — settings come first as an array:

```php
<?php

use Myth\Postal\Transport\LogTransport;

$transport = new LogTransport(['level' => 'info']);
```

By default the transport uses CodeIgniter's shared logger (`service('logger')`). Pass any PSR-3 `LoggerInterface` as the second argument — handy for directing mail to a dedicated channel or for asserting on it in tests.

```php
<?php

$transport = new LogTransport(['level' => 'debug'], $myPsr3Logger);
```

## What gets written

The log entry is the same raw MIME the real transports would send: the envelope and structural headers, any custom headers you added, and the encoded body (a `multipart/alternative` message whenever HTML is present). See [MIME Rendering](mime-rendering.md) for the full picture.

!!! danger "Logs contain the full message"
    The log mailer writes **every recipient and the entire body** to your logs. Treat those logs as sensitive, and don't enable the log mailer in production unless you understand where those entries go and who can read them.

## Next steps

- [MIME Rendering](mime-rendering.md) — exactly what the logged message looks like and why
