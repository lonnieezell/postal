# MIME Rendering

`MessageRenderer` turns an `Email` into a complete, RFC 5322 / MIME message as a raw string. Transports that speak raw MIME — like the [log mailer](log-mailer.md) and the SMTP-era transports — hand their message to it. You rarely call it directly, but knowing what it produces helps you reason about what actually leaves your app.

```php
use Myth\Postal\Email;
use Myth\Postal\MessageRenderer;

$mime = (new MessageRenderer())->render($email);
```

## Text, HTML, and the automatic fallback

What the renderer produces depends on which bodies you set:

- **Text only** (`->text(...)`) → a single `text/plain` part.
- **Any HTML** (`->html(...)`) → a `multipart/alternative` message with both a `text/plain` and a `text/html` part, in that order.

Here's the important part: **whenever you set HTML but no text, the renderer generates a plain-text version for you** and ships it as the text part. So an HTML email is *always* multipart.

Why bother? A bare `text/html` message with no plain-text alternative is one of the strongest spam signals there is — filters like SpamAssassin flag it outright. Generating the fallback keeps your deliverability healthy without any extra work.

The generated text is a best-effort conversion of your HTML:

- `<script>`, `<style>`, and `<head>` blocks are removed whole, so their CSS, JS, and metadata never leak into the text
- `<a href="https://example.com">our site</a>` becomes `our site (https://example.com)`
- block-level tags (`<p>`, `<div>`, `<br>`, headings, list items…) become line breaks
- remaining tags are stripped and HTML entities decoded (`&amp;` → `&`)

!!! note "Your Email is never mutated"
    The fallback is built at render time only. Your original `Email` object still has `textBody === null` afterward — nothing is written back.

If you want full control over the plain-text version, just set it yourself with `->text(...)` and the renderer uses yours verbatim.

## Headers

The renderer emits the standard envelope and structural headers — `From`, `To`, `Cc`, `Reply-To`, `Subject`, `Date`, `Message-ID`, `MIME-Version`, and the content headers — plus everything you added through the `Email` builder:

```php
$email = (new Email())
    ->from('you@example.com')
    ->to('user@example.com')
    ->subject('Receipt')
    ->header('X-Campaign', 'spring-2026')   // arbitrary custom header
    ->returnPath('bounces@example.com')     // -> Return-Path + Sender
    ->priority(1);                          // -> X-Priority: 1 (Highest)
```

- **Custom headers** from `->header()` are emitted as-is.
- **`->returnPath()`** sets both `Return-Path` and `Sender`.
- **`->priority()`** sets `X-Priority` — but only when it differs from the Normal (3) default, so ordinary mail stays clean.

!!! warning "Structural headers always win"
    A custom header can't overwrite a structural one. `->header('From', '...')` is ignored in favour of the real `From`. CR and LF are stripped from every header name and value, so untrusted input can't inject extra headers.

`render()` also records the header set it produced. Call `headers()` afterward to get it back as an array — used by the message debugger:

```php
$renderer = new MessageRenderer();
$renderer->render($email);
$headers = $renderer->headers(); // ['From' => '...', 'Subject' => '...', ...]
```

## Encoding

Headers with non-ASCII characters — an accented subject, a display name like `Café Owner` — are encoded with RFC 2047 "Q" encoding. The address itself stays literal; only the display name is encoded. Pure-ASCII headers are left untouched.

A pure-ASCII display name is wrapped in a quoted-string (`"Doe, John" <a@b.com>`), with quotes and backslashes escaped, so a comma or other special character can't split one recipient into two.

Plain-text parts are word-wrapped at 76 characters, and every line ending is normalised to CRLF. The HTML part is encoded with `quoted-printable`, so even very long HTML lines stay within the 998-octet SMTP limit without requiring an `8BITMIME`-capable server.

!!! note "Current limitations"
    The renderer is deliberately small for now. Very long non-ASCII header values aren't folded into multiple encoded-words. This is fine for the log mailer and typical messages; richer encoding will arrive with the SMTP transport.

## Next steps

- [The Log Mailer](log-mailer.md) — see a rendered message in your logs
