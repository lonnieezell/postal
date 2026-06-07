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

## Attachments and inline images

Three builder methods add files to a message:

```php
$email = (new Email())
    ->from('you@example.com')
    ->to('user@example.com')
    ->subject('Your receipt')
    ->html('<p>Thanks! Your logo: <img src="cid:logo"></p>')
    ->attach('/path/to/receipt.pdf')                 // a file on disk
    ->attachData($bytes, 'report.csv', 'text/csv')   // raw bytes in memory
    ->embedImage('/path/to/logo.png', 'logo');       // inline image, referenced by CID
```

- **`attach($path, $name = '', $mime = '')`** — attaches a file by path. The display name defaults to the file's basename and the MIME type is detected from the file; pass `$name`/`$mime` to override either.
- **`attachData($data, $name, $mime = '')`** — attaches raw bytes you already have in memory. The MIME type defaults to `application/octet-stream` when omitted.
- **`embedImage($path, $cid, $name = '', $mime = '')`** — embeds an image as an *inline* part. Reference it from your HTML with a `cid:` URL whose value matches `$cid` (e.g. `<img src="cid:logo">`).

### What gets rendered

The renderer only adds the containers a message actually needs, nesting them like this:

```
multipart/mixed              ← present when there are attachments
└── multipart/related        ← present when there are inline images
    └── multipart/alternative ← present when there is HTML
        ├── text/plain
        └── text/html
```

Each level appears only when it has something to hold: a text-only message with one attachment is simply `multipart/mixed` wrapping a `text/plain` part. Attachments carry `Content-Disposition: attachment`; inline images carry `Content-Disposition: inline` plus a `Content-ID`, and the `multipart/related` container names its root part with a `type` parameter, as RFC 2387 expects. Binary parts are base64-encoded and wrapped at 76 columns (RFC 2045).

### Lazy reads

Path-based attachments (`attach()` and `embedImage()`) are **read from disk at render time**, not when you call the method — so the file only needs to exist when the message is sent. If the file can't be read at that point, `render()` throws a `PostalException`. Bytes passed to `attachData()` are carried as-is.

!!! warning "Attach only trusted paths"
    `attach()` and `embedImage()` read whatever path you give them. Never pass an unsanitised, user-controlled path, or a request could read arbitrary files off your server.

### Automatic inline images

You don't have to call `embedImage()` yourself for every image. By default the renderer scans the HTML body for `<img>` sources, turns the embeddable ones into inline parts, and rewrites the reference to a `cid:` URL — so the images travel with the message instead of being hot-linked. Two source kinds are embedded:

```php
$email->html('
    <img src="data:image/png;base64,iVBORw0KGgo...">   <!-- decoded in memory -->
    <img src="/var/www/assets/logo.png">               <!-- read from disk -->
');
```

- **`data:` URIs** with an image MIME type are decoded and embedded (many clients strip data URIs, so this also improves rendering).
- **Local file paths** are embedded when the file exists and is actually an image.
- **`http(s)://` URLs and existing `cid:` references are left untouched** — remote images are never fetched, and an explicit `embedImage()` still works exactly as before.

Identical sources are de-duplicated into a single part, and the generated `Content-ID` is content-derived. Turn the whole pass off per message when you'd rather ship the HTML verbatim:

```php
$email->autoEmbedImages = false;
```

!!! warning "Only images are read"
    Auto-embedding reads a referenced local file **only when it sniffs as an image**, so an `<img src="/etc/passwd">` in HTML is ignored rather than embedded. Even so, treat HTML you put into a message as trusted — don't build it from unsanitised user input.

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

Both the plain-text and HTML parts are encoded with `quoted-printable`, with every line ending normalised to CRLF. Quoted-printable soft-wraps long lines at 76 characters, so even a very long unbroken line — or a non-ASCII body — stays within the 998-octet SMTP limit and the whole message is 7-bit clean, without requiring an `8BITMIME`-capable server.

### Word wrap

Quoted-printable soft wrapping is invisible to the reader — it decodes away on delivery. When you want the plain-text part to *stay* wrapped in the recipient's client, set `Email::$wordWrap = true` (and `$wrapChars`, default 76). The renderer then hard-wraps the text at word boundaries before encoding, leaving long space-less tokens such as URLs intact. Word wrap is off by default; the legacy `setWordWrap()` adapter method drives it.

!!! note "Current limitations"
    The renderer is deliberately small for now. Very long non-ASCII header values aren't folded into multiple encoded-words, and non-ASCII attachment filenames are placed in the parameter as-is rather than RFC 2231-encoded (CR/LF and quotes are still stripped/escaped, so they remain safe). This is fine for the log mailer and typical messages; richer encoding will arrive with the SMTP transport.

## Next steps

- [The Log Mailer](log-mailer.md) — see a rendered message in your logs
