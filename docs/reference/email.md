# Email

`Myth\Postal\Email` is the message builder. It is mutable and every builder method returns the same instance, so calls chain. The mailer clones the message when you send it, so changing your `Email` after `send()` doesn't affect the message that went out.

```php
<?php

use Myth\Postal\Email;

$email = (new Email())
    ->from('you@example.com', 'Your Name')
    ->to('user@example.com')
    ->subject('Welcome aboard')
    ->html('<p>Glad to have you with us.</p>');
```

## Builder methods

All builder methods return `static`.

### Addressing

| Method | Description |
|--------|-------------|
| `from(string $address, string $name = '')` | Sets the sender. |
| `replyTo(string $address, string $name = '')` | Sets the `Reply-To` address. |
| `to(array\|string $address, string $name = '')` | Adds a recipient. Call it again to add more. |
| `cc(array\|string $address, string $name = '')` | Adds a `Cc` recipient. |
| `bcc(array\|string $address, string $name = '')` | Adds a `Bcc` recipient. |

`to()`, `cc()` and `bcc()` accept:

- an address and an optional name: `->to('user@example.com', 'User')`
- a `"Name <email>"` string: `->to('User <user@example.com>')`
- a list of either form: `->to(['a@example.com', 'B <b@example.com>'])`

Every address is validated as it is added. A malformed or empty address throws a `Myth\Postal\Exceptions\PostalException` from the builder call, not later from the transport.

### Content

| Method | Description |
|--------|-------------|
| `subject(string $subject)` | Sets the subject line. |
| `html(string $html)` | Sets the HTML body. |
| `text(string $text)` | Sets the plain-text body. When you set HTML without text, the renderer generates the text part — see [MIME Rendering](../mime-rendering.md#text-html-and-the-automatic-fallback). |

### Headers and delivery

| Method | Description |
|--------|-------------|
| `header(string $name, string $value)` | Adds a custom header. Structural headers such as `From` can't be overridden. |
| `priority(int $level)` | Sets `X-Priority`, from `1` (highest) to `5` (lowest). `3` is normal and adds no header. |
| `returnPath(string $address)` | Sets the bounce address (`Return-Path` and `Sender`). |
| `listUnsubscribe(string $url)` | Sets `List-Unsubscribe`, wrapping the URL in `<…>`. Takes precedence over an auto-injected header — see [Suppression & Unsubscribe](../suppression-unsubscribe.md#setting-the-header-explicitly). |
| `metadata(string $key, string $value)` | Adds a provider tag. The SES and Resend transports send these as tags; other transports ignore them. |

### Attachments

| Method | Description |
|--------|-------------|
| `attach(string $path, string $name = '', string $mime = '')` | Attaches a file by path. The file is read when the message is rendered, not when you call this. |
| `attachData(string $data, string $name, string $mime = '')` | Attaches bytes already in memory. The MIME type defaults to `application/octet-stream`. |
| `embedImage(string $path, string $cid, string $name = '', string $mime = '')` | Embeds an inline image. Reference it from the HTML as `cid:<cid>`. |

An empty `$name` falls back to the file's basename, and an empty `$mime` is detected from the file. See [Attachments and inline images](../mime-rendering.md#attachments-and-inline-images).

## Properties

The builder methods write to public properties, which you can also read, for example in an [event listener](../events.md).

### Message fields

| Property | Type | Default | Set by |
|----------|------|---------|--------|
| `$from` | `?Address` | `null` | `from()` |
| `$replyTo` | `?Address` | `null` | `replyTo()` |
| `$to` | `list<Address>` | `[]` | `to()` |
| `$cc` | `list<Address>` | `[]` | `cc()` |
| `$bcc` | `list<Address>` | `[]` | `bcc()` |
| `$subject` | `string` | `''` | `subject()` |
| `$htmlBody` | `?string` | `null` | `html()` |
| `$textBody` | `?string` | `null` | `text()` |
| `$headers` | `array<string, string>` | `[]` | `header()`, `listUnsubscribe()` |
| `$metadata` | `array<string, string>` | `[]` | `metadata()` |
| `$attachments` | `list<Attachment>` | `[]` | `attach()`, `attachData()`, `embedImage()` |
| `$priority` | `int` | `3` | `priority()` |
| `$returnPath` | `?string` | `null` | `returnPath()` |

### Rendering options

These have no builder method. Set them directly.

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$autoEmbedImages` | `bool` | `true` | Embeds `data:` URIs and local image paths found in the HTML as inline parts. See [Automatic inline images](../mime-rendering.md#automatic-inline-images). |
| `$wordWrap` | `bool` | `false` | Hard-wraps the plain-text part so the wrapping survives delivery. |
| `$wrapChars` | `int` | `76` | The line length `$wordWrap` wraps at. |

### Set by Postal

| Property | Type | Description |
|----------|------|-------------|
| `$mailableClass` | `?string` | The class of the [Mailable](../mailables.md) that built the message, or `null`. Never written into the message. |
| `$rawMessage` | `?string` | Internal. The signed MIME message that [DKIM signing](../dkim-signing.md) hands to the transport. Don't set it yourself. |

## Address

`Myth\Postal\Address` is the read-only value object stored in `$from`, `$replyTo`, `$to`, `$cc` and `$bcc`.

| Member | Description |
|--------|-------------|
| `$email` | The address, e.g. `user@example.com`. |
| `$name` | The display name, or `''`. |
| `new Address(string $email, string $name = '')` | Creates an address. Doesn't validate. |
| `Address::fromString(string $address)` | Parses `"Name <email>"` or a bare address. Doesn't validate. |
| `toString()` | Returns `Name <email>`, or just the address when there's no name. |
| `Address::isValid(string $email)` | Checks an address. Internationalised domains pass when the `intl` extension is installed. |

```php
<?php

Events::on('email.sending', static function (Email $message): void {
    foreach ($message->to as $address) {
        log_message('info', 'Sending to {to}', ['to' => $address->toString()]);
    }
});
```

## Related

- [MIME Rendering](../mime-rendering.md) — how an `Email` becomes the message on the wire
- [Mailables](../mailables.md) — class-based emails that compose an `Email`
- [SendResult](send-result.md) — what sending returns
