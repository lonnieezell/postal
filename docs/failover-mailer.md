# The Failover Mailer

A single provider is a single point of failure. When your primary mail service has a bad five minutes — an API outage, a throttle, a connection that won't open — the messages queued behind it don't care whose fault it is; they just don't go out. The `failover` transport is the answer: it wraps an ordered list of other mailers and tries them one after another, handing the message to the next as soon as one fails. The first success wins, and only an across-the-board failure is reported as a failure.

It composes mailers you've already defined, so each child keeps its own transport and settings. Point it at your SES mailer first and an SMTP relay second, and a primary outage falls through to the backup without your application noticing.

## Configuration

Define the child mailers as usual, then add a `failover` mailer that lists them by name, in priority order, under a `chain` key:

```php
public array $mailers = [
    'ses' => [
        'transport' => 'ses',
        'region'    => 'us-east-1',
    ],
    'backup-smtp' => [
        'transport' => 'smtp',
        'host'      => 'smtp.example.com',
        'port'      => 587,
        'username'  => env('SMTP_USER'),
        'password'  => env('SMTP_PASS'),
    ],
    'primary' => [
        'transport' => 'failover',
        'chain'     => ['ses', 'backup-smtp'],
    ],
];
```

Send through it by name, exactly like any other mailer:

```php
use Myth\Postal\Email;

$email = (new Email())
    ->from('you@example.com', 'Your Name')
    ->to('user@example.com')
    ->subject('Welcome aboard')
    ->html('<p>Glad to have you with us.</p>');

$result = service('mailer')->mailer('primary')->send($email);
```

The children are resolved by name, so each one is built with its own settings — including any decoration it would normally receive. A failover mailer is just a coordinator; it never renders or delivers a message itself.

!!! note "Make it the default"
    Set `public string $default = 'primary';` in your `Config\Email` to route every `service('mailer')->send($email)` call through the failover chain without naming it each time.

## How it works

Sending walks the `chain` list in order:

1. The message is handed to the first child.
2. If that child reports success, the result is returned immediately — later children are never tried.
3. If the child reports failure **or throws** (a connection that won't open, a misconfigured client), the failover catches it and advances to the next child.
4. Only when every child has failed does the failover itself report failure.

That a thrown error is treated as just another failure is deliberate: a provider whose client blows up mid-send is exactly the case you want to fall through, not a crash that takes the whole send down.

## When the whole chain fails

If no child succeeds, you get a single failed `SendResult` whose error rolls up what each child reported, so you can see why every attempt fell through:

```php
$result = service('mailer')->mailer('primary')->send($email);

if (! $result->success) {
    log_message('error', $result->error);
    // e.g. "All failover transports failed: ses timed out; smtp auth rejected"
}
```

A failover mailer must list at least one child. An entry with a missing or empty `chain` key is a configuration error and throws a `PostalException` the first time the mailer is resolved, rather than silently sending nothing.

## Testing

The transport composes plain `TransportInterface` instances, so in a unit test you can construct it directly with whatever children you need — including doubles that simulate a primary outage:

```php
use Myth\Postal\Transport\FailoverTransport;

$failover = new FailoverTransport([
    $aTransportThatFails,    // primary is down
    $aTransportThatSucceeds, // backup takes over
]);

$result = $failover->send($email);
$this->assertTrue($result->success);
```

The constructor takes only the ordered children; the failover renders and delivers nothing itself, so it needs no settings of its own.

## Next steps

- [The SES Mailer](ses-mailer.md) — a natural primary for the chain
- [The SMTP Mailer](smtp-mailer.md) — a natural backup relay
- [Testing Mail](testing.md) — assert on sent messages without a live provider
