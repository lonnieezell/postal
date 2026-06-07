# The SES Mailer

When you send through Amazon Simple Email Service, you're talking to an HTTP API rather than an SMTP server. Postal's `ses` transport delivers through the official [`aws/aws-sdk-php`](https://github.com/aws/aws-sdk-php) SesV2 client, which signs every request with AWS SigV4 and owns the HTTP connection — so there are no credentials to put on the wire and no signing for you to get right.

Reach for it when you already run on AWS (or want SES's deliverability and tracking) and would rather authenticate with IAM than manage an SMTP password. For a local MTA see [the Sendmail & Mail mailers](sendmail-mail-mailers.md); for a remote SMTP server see [the SMTP mailer](smtp-mailer.md).

## Installing the SDK

The AWS SDK is an **optional** dependency — Postal doesn't pull it into every install. Add it when you want the SES transport:

```bash
composer require aws/aws-sdk-php
```

If the SES transport is used without the SDK present, it throws a `Myth\Postal\Exceptions\PostalException` telling you to install it.

## Configuring a mailer

Add a named mailer that points at the `ses` transport and carries its settings:

```php
public array $mailers = [
    'ses' => [
        'transport' => 'ses',
        'region'    => 'us-east-1',
        'key'       => env('SES_KEY'),
        'secret'    => env('SES_SECRET'),
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

$result = service('mailer')->mailer('ses')->send($email);

$result->messageId; // the SES message id on success
```

### Configuration reference

| Setting | Default | What it does |
|---------|---------|--------------|
| `region` | `us-east-1` | The AWS region your SES account sends from. |
| `key` | *(unset)* | AWS access key id. Omit to use the default credential chain (see below). |
| `secret` | *(unset)* | AWS secret access key. Required when `key` is set. |
| `token` | *(unset)* | Optional session token for temporary credentials. |
| `configurationSet` | *(unset)* | SES configuration set applied to every send (event publishing, dedicated IPs, etc.). |
| `forceRaw` | `false` | Always send raw MIME instead of letting Postal choose (see below). |

## Credentials

Set `key` and `secret` explicitly, or leave them unset to let the AWS SDK resolve credentials from its [default provider chain](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html) — environment variables, shared credentials files, or the IAM role attached to your EC2/ECS/Lambda runtime. On AWS infrastructure, **omitting `key`/`secret` and using an IAM role is the recommended setup**: there are no long-lived secrets to store or rotate.

```php
'ses' => [
    'transport' => 'ses',
    'region'    => 'us-east-1',
    // no key/secret — credentials come from the instance role
],
```

## Simple vs. raw content

SES accepts two body shapes, and Postal picks the right one for each message:

- **Simple** — structured fields (subject, HTML, text) that SES assembles into MIME. Used by default.
- **Raw** — the complete MIME message Postal renders itself.

Postal switches to **raw** automatically when:

- the message carries **attachments or inline images** (Simple content can't represent them),
- the HTML body contains an **auto-embeddable image** — a `data:` URI or local image path that [the renderer inlines](mime-rendering.md#automatic-inline-images) — since the resulting inline part can't ride in Simple content,
- the message is **HTML without an explicit text body** — only Postal's renderer generates the plain-text alternative that keeps HTML mail off the bare-HTML spam signal, or
- you set **`forceRaw => true`** (for example, when you sign messages with your own DKIM before handing them to SES).

Everything else — plain text, or HTML with a matching text part — goes as Simple. You don't choose; you just build the message and Postal does the right thing.

## Tagging messages with metadata

Attach key/value metadata to a message with `Email::metadata()`. The SES transport maps each pair onto an SES **EmailTag**, which you can use for filtering CloudWatch metrics and event streams:

```php
$email->metadata('campaign', 'spring_2026')
      ->metadata('tier', 'gold');
```

SES restricts tag names and values to ASCII letters, digits, underscores and dashes (`A-Z a-z 0-9 _ -`), 1–256 characters each. Any pair that breaks those rules is **dropped and logged at `debug`** rather than failing the send — so a stray space or colon in a tag never bounces an otherwise-good message. Only the offending key is logged; tag values are never written to the log.

Metadata is ignored by transports that have no tagging concept (SMTP, sendmail, mail, log).

## Configuration sets

Point the mailer at an SES configuration set to apply it to every message — useful for event publishing (deliveries, bounces, complaints), dedicated IP pools, or custom tracking:

```php
'ses' => [
    'transport'        => 'ses',
    'region'           => 'us-east-1',
    'configurationSet' => 'transactional',
],
```

## Testing without calling AWS

The transport takes a `SesV2Client` as its second constructor argument, so you can unit-test against a mocked client with no network access. Build a client with the AWS SDK's `MockHandler`:

```php
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Myth\Postal\Transport\SesTransport;

$mock = new MockHandler();
$mock->append(new Result(['MessageId' => 'test-message-id']));

$client = new SesV2Client([
    'region'      => 'us-east-1',
    'version'     => 'latest',
    'credentials' => ['key' => 'test', 'secret' => 'test'],
    'handler'     => $mock,
]);

$transport = new SesTransport([], $client);
$result    = $transport->send($email);
```

Append a closure instead of a `Result` to capture the parameters SES would have received, or append an `Aws\SesV2\Exception\SesV2Exception` to assert the failure path.

## Error handling

A rejected send never throws. An SES (or AWS) error is caught and returned as `SendResult::fail()`, carrying the AWS error message and the original exception as its raw payload:

```php
$result = service('mailer')->mailer('ses')->send($email);

if (! $result->success) {
    log_message('error', 'SES rejected the message: ' . $result->error);
}
```

## Security

!!! note "Credentials and headers"
    - **No secrets on the wire.** SigV4 signing means your secret key never leaves the SDK; prefer an IAM role over stored keys wherever you can.
    - **Header injection is blocked.** Simple content is sent as structured fields (never concatenated into headers), and raw content goes through the same renderer that strips CR/LF from every header. Display names with control characters are encoded, not passed through. Still, validate addresses at your application boundary.
    - **Tag values stay out of the log.** Dropped metadata is logged by key only, so message metadata can't leak sensitive values into your debug log.

## Next steps

- [The SMTP Mailer](smtp-mailer.md) — authenticate to a remote server with encryption and keep-alive
- [MIME Rendering](mime-rendering.md) — what the raw message looks like on the wire
- [Testing Mail](testing.md) — assert on sent mail without hitting a provider
