# Writing a Custom Transport

When Postal doesn't support your provider, write a transport for it. A transport is one class; register it in `Config\Mailer` and it works with mailers, events, suppression, failover and `Mailer::fake()` like the built-in ones.

This guide builds two:

- **`EmlFileTransport`** writes each message to an `.eml` file, which is handy in development. It shows a transport that delivers the rendered email.
- **`AcmeTransport`** sends to an HTTP JSON API. Acme Mail is made up; swap in your provider's endpoint and fields.

## The contract

A transport implements `Myth\Postal\Transport\TransportInterface`:

| Method | What it must do |
|--------|-----------------|
| `send(Email $email): SendResult` | Deliver the message. Return [`SendResult::ok()`](reference/send-result.md#factories) or `SendResult::fail()`. |
| `ping(): bool` | Return `true` when the transport can deliver right now, for example when the server is reachable. |

Three rules keep it working with the rest of Postal:

- **Return `fail()`, don't throw, when the message is rejected.** Throw a `PostalException` only for configuration problems, such as a missing API key. A [failover mailer](failover-mailer.md) treats both as a failure and moves on.
- **Take the mailer entry as the first constructor argument.** Postal builds the transport with `new YourTransport($settings)`, where `$settings` is the whole mailer entry from `Config\Mailer`.
- **Take what you need to fake as an optional second argument**, such as an HTTP client or a directory. Postal never passes it, so it's free for tests.

## A transport that writes `.eml` files

```php
<?php

namespace App\Mail;

use Myth\Postal\Email;
use Myth\Postal\MessageRenderer;
use Myth\Postal\SendResult;
use Myth\Postal\Transport\RawMimeTransport;

final readonly class EmlFileTransport implements RawMimeTransport
{
    private string $directory;

    /**
     * @param array<string, mixed> $settings accepts an optional "directory" key
     */
    public function __construct(array $settings = [], ?string $directory = null)
    {
        $this->directory = $directory ?? (string) ($settings['directory'] ?? WRITEPATH . 'mail');
    }

    public function send(Email $email): SendResult
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0775, true)) {
            return SendResult::fail('Could not create ' . $this->directory);
        }

        $renderer = new MessageRenderer();

        // A DKIM-signed message arrives already rendered; send those bytes unchanged.
        $message = $email->rawMessage ?? $renderer->render($email);

        $file = $this->directory . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';

        if (file_put_contents($file, $message) === false) {
            return SendResult::fail('Could not write ' . $file);
        }

        return SendResult::ok($renderer->headers()['Message-ID'] ?? null, $file);
    }

    public function ping(): bool
    {
        return is_dir($this->directory) && is_writable($this->directory);
    }
}
```

`MessageRenderer` turns the `Email` into a complete MIME message: headers, the plain-text fallback, attachments and inline images. See [MIME Rendering](mime-rendering.md).

Implementing `RawMimeTransport` instead of `TransportInterface` tells Postal the transport delivers the rendered message unchanged. That makes [DKIM signing](dkim-signing.md) available to it: the signer renders and signs the message, then passes it in `$email->rawMessage`, which the transport must send as is. Only implement `RawMimeTransport` if you honour `rawMessage`.

## A transport for an HTTP API

For a JSON API, extend `Myth\Postal\Transport\AbstractApiTransport`. It sends the request, encodes the JSON, embeds inline images, and turns the response into a `SendResult`. You supply the provider-specific parts:

```php
<?php

namespace App\Mail;

use Myth\Postal\Address;
use Myth\Postal\Email;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Transport\AbstractApiTransport;
use Myth\Postal\Transport\HttpClientInterface;

final readonly class AcmeTransport extends AbstractApiTransport
{
    private string $token;

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings = [], ?HttpClientInterface $http = null)
    {
        $this->token = (string) ($settings['token'] ?? '');

        if ($this->token === '') {
            throw PostalException::forMissingSetting('token', 'acme');
        }

        parent::__construct($http);
    }

    protected function endpoint(): string
    {
        return 'https://api.acme-mail.example/v1/send';
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    protected function payload(Email $email): array
    {
        return [
            'from'    => $email->from instanceof Address ? $this->formatAddress($email->from) : '',
            'to'      => array_map($this->formatAddress(...), $email->to),
            'subject' => $email->subject,
            'html'    => $email->htmlBody,
            'text'    => $email->textBody,
            'tags'    => $this->supportedMetadata($email, '/^[a-z0-9_]{1,64}$/', 'lowercase letters, digits and underscores only'),
        ];
    }

    protected function messageId(array $data): ?string
    {
        return isset($data['id']) ? (string) $data['id'] : null;
    }

    protected function errorMessage(array $data): ?string
    {
        return isset($data['error']) ? (string) $data['error'] : null;
    }
}
```

| Method | Returns |
|--------|---------|
| `endpoint()` | The URL the message is POSTed to. |
| `headers()` | Request headers, usually authentication. `Content-Type` and `Accept` are added for you. |
| `payload(Email $email)` | The request body, as an array. |
| `messageId(array $data)` | The provider's message ID from a successful response. |
| `errorMessage(array $data)` | The provider's error from a failed response, or `null` to report the HTTP status. |

The base class also gives you three helpers:

- `formatAddress()` formats an `Address` as `"Name" <email>`, encoding non-ASCII names.
- `supportedMetadata()` keeps the `metadata()` tags that match your provider's rules, and drops and logs the rest.
- `dropUnsupported()` logs a feature the provider can't carry, such as a custom return path, instead of failing the send.

!!! warning "Keep the class `readonly`"
    `AbstractApiTransport` is a `readonly` class, and PHP requires its subclasses to be `readonly` too. Leaving it out is a fatal error.

Requests go through the `postalHttpClient` service, which uses cURL by default. To use your own HTTP client, see [HTTP clients](resend-mailer.md#http-clients).

## Register it

Add the class to `$transports` and point a mailer at it in `app/Config/Mailer.php`. Redeclaring `$transports` replaces the package's map, so copy the built-in entries from `Myth\Postal\Config\Mailer` as well:

```php
<?php

public array $transports = [
    // ...the built-in entries...
    'eml'  => \App\Mail\EmlFileTransport::class,
    'acme' => \App\Mail\AcmeTransport::class,
];

public array $mailers = [
    'eml'  => ['transport' => 'eml'],
    'acme' => ['transport' => 'acme'],
    // ...
];

public function __construct()
{
    parent::__construct();

    $this->mailers['acme']['token'] = env('ACME_TOKEN');
}
```

The new mailers work everywhere the built-in ones do: `service('mailer')->mailer('acme')`, `$default`, `Mailable::transport()`, and a failover `chain`.

## Test it

Pass the test double as the second constructor argument. For the file transport, a temporary directory:

```php
<?php

$dir    = sys_get_temp_dir() . '/eml-' . bin2hex(random_bytes(4));
$result = (new EmlFileTransport([], $dir))->send($email);

$this->assertTrue($result->success);
$this->assertFileExists($result->raw);
```

For the API transport, an `HttpClientInterface` that records the request and returns a canned response:

```php
<?php

use Myth\Postal\Transport\HttpClientInterface;
use Myth\Postal\Transport\HttpResponse;

$http = new class () implements HttpClientInterface {
    public string $body = '';

    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $this->body = $body;

        return new HttpResponse(200, '{"id":"msg-1"}');
    }
};

$result = (new AcmeTransport(['token' => 'test'], $http))->send($email);

$this->assertSame('msg-1', $result->messageId);
```

Return `new HttpResponse(422, '{"error":"Invalid recipient"}')` to test the failure path. To check what your *application* sends, rather than the transport, use [`Mailer::fake()`](testing.md).

## Next steps

- [How Postal Works](how-postal-works.md) — where a transport fits in a send
- [Config\Mailer reference](reference/config-mailer.md#transports) — the transport map
