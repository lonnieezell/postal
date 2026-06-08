# Events

Postal fires an event at each step of a send. They're hooks: a place to log delivery, tweak a message just before it goes out, audit failures, or stop a send entirely. If you've used CodeIgniter's event system before, this works exactly the same way.

## The four events

Every send walks through these in order:

| Event | When it fires | Listener receives |
|-------|---------------|-------------------|
| `email.composing` | At the very start, before anything else | `Email $message` |
| `email.sending` | Immediately before the transport runs | `Email $message` |
| `email.sent` | After a successful delivery | `Email $message`, `SendResult $result` |
| `email.failed` | After the transport reports a failure | `Email $message`, `SendResult $result` |

A send fires `composing`, then `sending`, then exactly one of `sent` or `failed`. The `$message` passed to your listeners is Postal's working copy — a clone made at the start of the send — so changes you make to the original after calling `send()` never sneak in.

## Listening for an event

Register a listener with CodeIgniter's `Events::on()`, usually in your app's `app/Config/Events.php`:

```php
<?php

use CodeIgniter\Events\Events;
use Myth\Postal\Email;
use Myth\Postal\SendResult;

Events::on('email.sent', static function (Email $message, SendResult $result): void {
    log_message('info', 'Mail sent to {to}, id {id}', [
        'to' => $message->to[0]->email,
        'id' => $result->messageId,
    ]);
});
```

The `sent` and `failed` listeners get the `SendResult` too, so you can record the provider message ID on success or the error on failure:

```php
<?php

Events::on('email.failed', static function (Email $message, SendResult $result): void {
    log_message('error', 'Mail failed: {error}', ['error' => $result->error]);
});
```

## Modifying the message

`composing` and `sending` listeners receive the live message object, so anything you change there goes out with the email. It's the clean place to enforce app-wide rules — add a compliance BCC, stamp a header, or set a default `from`:

```php
<?php

Events::on('email.composing', static function (Email $message): void {
    $message->bcc('archive@example.com');
    $message->header('X-App-Version', '2.4.0');
});
```

Use `composing` for broad defaults and `sending` for last-second changes — the message is identical in both, so reach for whichever reads better.

## Cancelling a send

Return `false` from an `email.sending` listener to abort delivery. The transport is never called, and `send()` returns a cancelled result:

```php
<?php

Events::on('email.sending', static function (Email $message): bool {
    if (str_ends_with($message->to[0]->email, '@blocked.example')) {
        return false; // stop the send
    }

    return true;
});
```

The caller sees this in the result:

```php
<?php

$result = service('mailer')->send($email);

if ($result->cancelled) {
    // a listener stopped it — $result->success is false, nothing was sent
}
```

!!! note "Only `sending` can cancel"
    Returning `false` from `composing`, `sent`, or `failed` does nothing — those events are notifications. Cancellation is deliberately limited to `sending`, the moment right before delivery.

## Turning events off

Events fire on every send by default. To silence all four, set `$fireEvents` to `false` in your `Config\Email`:

```php
<?php

public bool $fireEvents = false;
```

With events off, no listeners run — including any `sending` listener that would have cancelled — and the message goes straight to the transport. Reach for this when you want a guaranteed-clean send path (a bulk job, say) with zero listener overhead or interference.

## Next steps

- [The Log Mailer](log-mailer.md) — see a full message without sending it
- [MIME Rendering](mime-rendering.md) — what the message looks like on the wire
