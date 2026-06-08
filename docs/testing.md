# Testing Mail

Postal ships an in-memory test double so you can assert on what your application _would have_ sent without ever touching a real transport or the network. Call `Mailer::fake()` at the top of a test; it swaps the bound `service('mailer')` for a recorder and hands that recorder back to you.

```php
<?php

use Myth\Postal\Mailer;

$fake = Mailer::fake();

// ...exercise the code under test, which sends as usual...
service('mailer')->send($email);

$fake->assertSentTo('user@example.com');
```

Every mailer resolved from `service('mailer')` — the default and any named mailer — routes through the same double, so you don't need to know which mailer your code reaches for.

## Faking sends

`Mailer::fake()` returns a `FakeTransport`. From that point on, no message leaves your application: each send is recorded in memory and reports success, so the code under test behaves as though delivery worked.

```php
<?php

$fake = Mailer::fake();

(new WelcomeEmailService())->sendTo($user);

$fake->assertSentCount(1);
```

!!! note "Reset between tests"
    The swap lives in the service container for the rest of the test. If your test case doesn't already reset services, call `Services::reset()` in `tearDown()` so one test's fake doesn't leak into the next.

## Inspecting what was sent

`sent()` returns the recorded messages as an array of `Email` objects, in the order they were sent:

```php
<?php

$messages = $fake->sent();

$this->assertSame('Welcome!', $messages[0]->subject);
```

Pass a content matcher — a closure that receives an `Email` and returns a bool — to filter:

```php
<?php

$welcomes = $fake->sent(static fn (Email $email): bool => $email->subject === 'Welcome!');
```

If you send [Mailables](mailables.md), pass the class name instead to get just the messages that came from that Mailable:

```php
<?php

$welcomes = $fake->sent(WelcomeEmail::class);
```

## Assertions

All assertions integrate with PHPUnit, so a failure fails the test with a readable message.

### `assertSent(Closure $callback)`

Passes when at least one recorded message satisfies the matcher:

```php
<?php

$fake->assertSent(static fn (Email $email): bool =>
    $email->subject === 'Welcome!'
    && str_contains((string) $email->htmlBody, 'Thanks for joining'));
```

### By Mailable class

When you send a [Mailable](mailables.md), `send()` tags the message with its class, so you can assert on the type instead of matching content. Pass the class name to `assertSent()`:

```php
<?php

$fake->assertSent(WelcomeEmail::class);
```

Add a closure as a second argument to narrow it further — both must match:

```php
<?php

$fake->assertSent(
    WelcomeEmail::class,
    static fn (Email $email): bool => $email->subject === 'Welcome aboard',
);
```

### `assertSentTo(string $email)`

Passes when at least one recorded message was addressed to the given email — in any of the `To`, `Cc`, or `Bcc` buckets. The match is case-insensitive:

```php
<?php

$fake->assertSentTo('user@example.com');
```

### `assertNotSent(Closure $callback)`

Passes when **no** recorded message satisfies the matcher:

```php
<?php

$fake->assertNotSent(static fn (Email $email): bool => $email->subject === 'Password reset');
```

### `assertNothingSent()`

Passes when no message was recorded at all:

```php
<?php

$fake->assertNothingSent();
```

### `assertSentCount(int $count)`

Passes when exactly the given number of messages were recorded:

```php
<?php

$fake->assertSentCount(3);
```

## Next steps

- [Events](events.md) — the lifecycle events that still fire through the faked mailer
- [The Log Mailer](log-mailer.md) — render messages to a log channel instead of delivering them
