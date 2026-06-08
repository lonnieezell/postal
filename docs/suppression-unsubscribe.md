# Suppression & Unsubscribe

Two opt-out contracts you can wire into every send: a **suppression list** that silently removes recipients before the transport runs, and an **unsubscribe URL provider** that auto-injects the `List-Unsubscribe` header for single-recipient messages. Both are optional — if you don't bind them, Postal behaves exactly as before.

## Suppression list

A suppression list answers one question: "should this address receive this email right now?" Any address for which the answer is "no" is removed from `to`, `cc`, and `bcc` before the transport is ever called.

### Implementing the interface

Create a class that implements `SuppressionListInterface`:

```php
<?php

namespace App\Mail;

use Myth\Postal\Address;
use Myth\Postal\SuppressionListInterface;

class DatabaseSuppressionList implements SuppressionListInterface
{
    public function isSuppressed(Address $recipient): bool
    {
        return db_connect()
            ->table('email_suppressions')
            ->where('email', $recipient->email)
            ->countAllResults() > 0;
    }
}
```

### Wiring it up

Point `$suppressionList` at your class in `app/Config/Email.php`:

```php
<?php

use App\Mail\DatabaseSuppressionList;

public ?string $suppressionList = DatabaseSuppressionList::class;
```

That's it. `MailerManager` instantiates the class once per mailer and passes it in automatically.

### What happens on a send

Postal calls `isSuppressed()` for every address in `to`, `cc`, and `bcc`. Addresses that return `true` are dropped silently. If every recipient is suppressed, the transport is never called and `send()` returns a cancelled result:

```php
<?php

$result = service('mailer')->send($email);

if ($result->cancelled) {
    // all recipients were suppressed — nothing was sent
    // $result->error carries the reason: 'All recipients are suppressed'
}
```

### The `email.suppressed` event

Each removed address fires an `email.suppressed` event so you can log or audit it:

```php
<?php

use CodeIgniter\Events\Events;
use Myth\Postal\Address;

Events::on('email.suppressed', static function (Address $address): void {
    log_message('info', 'Suppressed send to {email}', ['email' => $address->email]);
});
```

Register this in your `app/Config/Events.php`. The event fires before `email.sending`, so it won't interfere with any cancellation logic you have there.

!!! note "Suppression runs after `email.composing`"
    If a `composing` listener adds recipients dynamically, those new addresses are also checked against your suppression list. The filter always sees the final recipient set after composing completes.

---

## Unsubscribe headers

When a subscriber hits "unsubscribe" in their mail client, the client usually follows a `List-Unsubscribe` header on the message. Postal can inject this header automatically for single-recipient sends.

### Implementing the interface

Create a class that implements `UnsubscribeUrlInterface`:

```php
<?php

namespace App\Mail;

use Myth\Postal\Address;
use Myth\Postal\UnsubscribeUrlInterface;

class TokenUnsubscribeUrl implements UnsubscribeUrlInterface
{
    public function urlFor(Address $recipient): string
    {
        $token = hash_hmac('sha256', $recipient->email, env('APP_KEY'));

        return site_url("email/unsubscribe/{$token}");
    }

    public function isOneClick(): bool
    {
        return true; // emit List-Unsubscribe-Post per RFC 8058
    }
}
```

### Wiring it up

Point `$unsubscribeUrl` at your class in `app/Config/Email.php`:

```php
<?php

use App\Mail\TokenUnsubscribeUrl;

public ?string $unsubscribeUrl = TokenUnsubscribeUrl::class;
```

### What gets injected

For a message with exactly one `To` recipient and no existing `List-Unsubscribe` header, Postal calls `urlFor()` and writes:

```
List-Unsubscribe: <https://example.com/email/unsubscribe/abc123>
```

If `isOneClick()` returns `true`, it also adds the RFC 8058 one-click header:

```
List-Unsubscribe: <https://example.com/email/unsubscribe/abc123>
List-Unsubscribe-Post: List-Unsubscribe=One-Click
```

The one-click header signals to mail clients (and Gmail in particular) that a `POST` to your URL is enough to unsubscribe — no confirmation page required.

!!! warning "Multi-recipient messages are skipped"
    Auto-injection only applies when there's exactly one `To` recipient. A newsletter blast to 500 addresses doesn't get a header, because one URL can't represent all of them. If you need unsubscribe headers on bulk sends, set them explicitly (see below).

---

## Setting the header explicitly

Call `listUnsubscribe()` on the message to set the header yourself. This always wins over auto-injection:

```php
<?php

$email = (new Email())
    ->from('newsletters@example.com')
    ->to('user@example.com')
    ->subject('Your weekly digest')
    ->html($body)
    ->listUnsubscribe('https://example.com/unsubscribe/weekly?id=42');
```

This is the right approach for pre-rendered bulk mail where you've already built a per-recipient URL before constructing the message.

!!! tip "Wrapping is handled for you"
    `listUnsubscribe()` wraps the URL in angle brackets (`<…>`) as the RFC requires. Pass the raw URL — don't add the brackets yourself.

---

## Next steps

- [Events](events.md) — listen to `email.suppressed` and the rest of the lifecycle
- [Installation](installation.md) — wiring up `Config\Email` for your environment
