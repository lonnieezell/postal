# The Resend Mailer

[Resend](https://resend.com) is an email API: you POST a JSON message over HTTPS and it handles delivery. Postal's `resend` transport talks to that API directly, so all you need is an API key — no SMTP relay, no extra SDK.

Reach for it when Resend is your provider and you'd rather send over its API than its SMTP endpoint: you get the Resend message id back on every send, and Resend's own error messages when a send is rejected. For Amazon's API see [the SES mailer](ses-mailer.md); for a plain SMTP server see [the SMTP mailer](smtp-mailer.md).

## Configuring a mailer

Create an API key in the Resend dashboard (a "Sending access" key is enough), then add a named mailer that points at the `resend` transport:

```php
<?php

public array $mailers = [
    'resend' => [
        'transport' => 'resend',
        'key'       => env('RESEND_KEY'),
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

$result = service('mailer')->mailer('resend')->send($email);

$result->messageId; // the Resend email id on success
```

The `from` address must be on a domain you have verified in Resend.

### Configuration reference

| Setting | Default | What it does |
|---------|---------|--------------|
| `key` | *(required)* | Your Resend API key. Resolving the mailer without one throws a `PostalException`. |

## What gets sent

Postal maps the message onto Resend's API fields:

- **from, to, cc, bcc, reply-to** — display names are quoted (or RFC 2047-encoded when non-ASCII).
- **subject, html, text** — if you send HTML without a text body, Resend generates the plain-text version itself.
- **Custom headers** — anything set with `Email::header()`, including `List-Unsubscribe`.
- **Attachments and inline images** — sent base64-encoded. Inline images keep their Content-ID, so `<img src="cid:logo">` resolves. Images that [the renderer auto-embeds](mime-rendering.md#automatic-inline-images) (`data:` URIs and local image paths) are turned into inline attachments the same way.

Resend can't carry two things, so Postal **drops them and logs at `debug`** rather than failing the send:

- a **return path** (`Email::returnPath()`) — Resend manages the bounce address itself;
- a **non-default priority** (`Email::priority()`).

## Tagging messages with metadata

Attach key/value metadata with `Email::metadata()`. Each pair becomes a Resend **tag**, which you can filter on in the dashboard and in webhooks:

```php
<?php

$email->metadata('campaign', 'spring_2026')
      ->metadata('tier', 'gold');
```

Resend restricts tag names and values to ASCII letters, digits, underscores and dashes (`A-Z a-z 0-9 _ -`), up to 256 characters. A pair that breaks those rules is **dropped and logged at `debug`**, so a stray space never bounces a good message. Only the key is logged, never the value.

## Error handling

A rejected send never throws. When Resend answers with an error status, the transport returns `SendResult::fail()` carrying Resend's error message, with the decoded response body (including Resend's error `name`, such as `validation_error`) as the raw payload. A network failure (DNS, connection refused, timeout) is also returned as a failure, with the exception as the raw payload:

```php
<?php

$result = service('mailer')->mailer('resend')->send($email);

if (! $result->success) {
    log_message('error', 'Resend rejected the message: ' . $result->error);
}
```

Wrap the mailer in a [failover chain](failover-mailer.md) to fall back to another provider when Resend is down.

## DKIM

Resend signs your mail with the DKIM keys of your verified domain. It receives JSON rather than raw MIME, so Postal's [DKIM decorator](dkim-signing.md) can't sign for it — adding a `dkim` block to a `resend` mailer throws a configuration error.

## HTTP clients

The transport sends its request through `Myth\Postal\Transport\HttpClientInterface`. It takes it from the `postalHttpClient` service, which by default returns `CurlHttpClient`: it needs PHP's `curl` extension and gives up after 30 seconds.

To use an HTTP client your application already has, override that service in `app/Config/Services.php` and wrap any PSR-18 client (with PSR-17 factories) in `Psr18HttpClient`. Every API mailer then sends through it:

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Myth\Postal\Transport\HttpClientInterface;
use Myth\Postal\Transport\Psr18HttpClient;

class Services extends BaseService
{
    public static function postalHttpClient(bool $getShared = true): HttpClientInterface
    {
        if ($getShared) {
            return static::getSharedInstance('postalHttpClient');
        }

        $factory = new HttpFactory();

        return new Psr18HttpClient(new Client(), $factory, $factory);
    }
}
```

## Testing without calling Resend

The transport also accepts an `HttpClientInterface` as its second constructor argument (or you can `Services::injectMock('postalHttpClient', ...)`). Pass your own client that records the request and returns a canned `HttpResponse`:

```php
<?php

use Myth\Postal\Transport\HttpClientInterface;
use Myth\Postal\Transport\HttpResponse;
use Myth\Postal\Transport\ResendTransport;

$http = new class () implements HttpClientInterface {
    public string $body = '';

    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $this->body = $body;

        return new HttpResponse(200, '{"id":"test-id"}');
    }
};

$result = (new ResendTransport(['key' => 're_test'], $http))->send($email);

json_decode($http->body, true); // the JSON Resend would have received
```

Return a non-2xx response such as `new HttpResponse(422, '{"message":"Invalid `to` field."}')` to exercise the failure path. To assert on mail your application sends, without touching any transport, use [`Mailer::fake()`](testing.md).

## Security

!!! note "Keys and headers"
    - **Keep the key out of source control.** Read it from the environment (`env('RESEND_KEY')`), and use a sending-only key so a leaked key can't read or manage your account.
    - **No header injection through fields.** Addresses, subject and bodies travel as JSON fields, not concatenated header text.
    - **Tag values stay out of the log.** Dropped metadata is logged by key only.

## Next steps

- [The SES Mailer](ses-mailer.md) — Amazon's API, with IAM credentials
- [The Failover Mailer](failover-mailer.md) — fall back when a provider is down
- [Testing Mail](testing.md) — assert on sent mail without hitting a provider
