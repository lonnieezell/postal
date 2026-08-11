# Changelog

## [Unreleased]

### Fixed

- Mail Components: `<mail-button>` emitted its `url` through the `attr` escaper
  context, which is meant for unquoted attribute values and encodes every
  non-alphanumeric character. The `href` came out as
  `https&#x3A;&#x2F;&#x2F;example.com` — which email clients still follow, but
  which hides the link from anything post-processing the rendered HTML by
  matching `href="http..."`, such as click tracking or link rewriting. The href
  is double-quoted, so the `html` context is the correct one: it neutralises the
  characters that could end the attribute and leaves the URL readable.
- Mail Components: `<mail-panel>`'s `color` never reached the email. It was
  emitted through the `css` escaper, which encodes `#` as `\23 `, producing
  `background-color: \23 eff6ff` — an ident token where a hash token is
  required, so the declaration was invalid and dropped. Panels rendered with no
  background at all, including at the default colour. The value is now validated
  as a hex colour or colour keyword, falling back to the default when it is
  neither: for a value that lands in a CSS property position, escaping and
  rendering are mutually exclusive, so validation is the only workable approach.
- Mail Components: each component now renders with `saveData` off. CodeIgniter's
  renderer carries view data between `render()` calls, so an optional attribute
  set on one component leaked into the next component that omitted it — a
  `<mail-panel>` with no `color` picked up the colour of an earlier panel in the
  same email.

### Changed

- **BREAKING:** the mailer configuration class is now `Myth\Postal\Config\Mailer`
  (was `Myth\Postal\Config\Email`), and `MailerManager` resolves it by its short
  name. The old name collided with the framework's legacy `Config\Email`, which
  forced resolution by fully-qualified class name — and that form never consults
  an application subclass, so the documented override path could not work.
  Applications that published a `Config\Email` extending the package config
  should rename it to `Config\Mailer` and extend `Myth\Postal\Config\Mailer`.
  The framework's own legacy `Config\Email`, used by `LegacyEmailAdapter` and
  `spark email:test`, is unaffected. See ADR 0001.

### Added

- Markdown Mailables: author an email body as CommonMark markdown via the
  `markdown()` global helper or `Mailable::markdown($view, $data)`, which
  resolves a view to raw markdown, converts it (`service('markdown')`, a
  `MarkdownRenderer` built from a `League\CommonMark` `Environment`), wraps
  the result in a shared `Layout` (`LayoutRenderer`, single-slot, applied
  after conversion — falls back to `Config\Postal::$defaultLayout`, or
  overridden per-Mailable with `Mailable::layout()`), and inlines the
  Layout's CSS for email-client compatibility. Also derives a plain-text
  fallback directly from the markdown source (`MarkdownString::text()`)
  rather than reverse-engineering it from HTML. Ships two pre-styled Mail
  Components — `<mail-button url="...">` and `<mail-panel color="...">` —
  resolved by a real CommonMark parser/renderer extension
  (`MailComponentExtension`), not regex or DOM post-processing. Adds
  `league/commonmark` and `tijsverkoyen/css-to-inline-styles` as hard
  dependencies. `ViewPublisher`, discovered and run by `spark publish`
  alongside `ConfigPublisher`, copies the default Layout and Mail Component
  views into `app/Views/mail/`, never overwriting existing files, so host
  apps can restyle them freely. Raw-HTML
  passthrough stays at its default throughout, so markdown views inherit
  CI4's usual `esc()`-it-yourself trust
  model; see [Markdown Mailables](markdown-mailables.md#security-raw-html-passthrough).
  `make:mailable` gains a `--markdown` flag that scaffolds a starter markdown
  view alongside a `build()` calling `->markdown()` instead of `->html()`.
- `spark publish` writes `app/Config/Mailer.php`, a `Config\Mailer` extending
  the package's config, which Postal then resolves in preference to its own
  default. Re-running publish never overwrites an existing file.

- Failover transport (`failover` mailer): a composite that tries an ordered list
  of child mailers (named under a `chain` key) and falls through to the next on
  failure — returning the first success and reporting failure only when every
  child fails. A child that throws is treated as a failure so the chain advances.
  `MailerManager` resolves the child mailers by name and hands the already-built
  transports to the composite. A failover mailer with no children throws a
  `PostalException`.
- Automatic inline images: the renderer scans the HTML body for `<img>`
  sources and embeds the embeddable ones — `data:` image URIs (decoded in
  memory) and local image file paths (read at render) — rewriting each
  reference to a `cid:` URL and de-duplicating identical sources. Remote
  (`http(s)://`) and existing `cid:` sources are left untouched, and only files
  that sniff as images are read. On by default; disable per message with
  `Email::$autoEmbedImages = false`. Adds `Attachment::embedData()`.
- Amazon SES transport (`ses` mailer): delivers through the official
  `aws/aws-sdk-php` SesV2 client (SigV4 signing and HTTP owned by the SDK).
  Sends structured Simple content by default and switches to raw MIME for
  attachments/inline images, HTML without an explicit text body, or when
  `forceRaw` is set. Maps `Email::metadata()` onto SES EmailTags (dropping and
  debug-logging tags that break SES's character rules), applies an optional
  `configurationSet`, and returns the SES message id. `aws/aws-sdk-php` is an
  optional dependency (install it with `composer require aws/aws-sdk-php`).
- `Email::metadata(key, value)` builder for provider tags, mapped by API
  transports onto their tagging feature and ignored by the others.
- Attachments and inline images: `Email::attach()` (file by path, read lazily at
  render), `Email::attachData()` (raw bytes), and `Email::embedImage()` (inline
  CID image referenceable from HTML), backed by the `Attachment` value object. The
  renderer nests `multipart/mixed` (attachments) around `multipart/related`
  (inline images) around the existing `multipart/alternative`, with base64 parts
  chunked at 76 columns and part headers sanitised against filename injection.
- Core send pipeline: compose an `Email` and send it through a transport via
  `service('mailer')`, receiving a `SendResult`.
- `Email` message builder with `from`/`replyTo`/`to`/`cc`/`bcc`/`subject`/`html`/`text`/
  `header`/`priority`/`returnPath` (mutable, chainable).
- `Address` value object that parses and renders `"Name <email>"`.
- `SendResult` with `ok()`/`fail()`/`cancelled()` factories.
- `TransportInterface` and the `NullTransport` implementation.
- `MailerManager` (resolves the default and named mailers from `Config\Mailer` via an
  extensible transport map, lazily and cached) and a minimal `Mailer` that clones the
  message at the dispatch boundary.
- `Config\Mailer` configuration and the `service('mailer')` service.
- `MessageRenderer` that serialises an `Email` into a raw RFC 5322 / MIME string:
  `text/plain`, or `multipart/alternative` whenever HTML is present (with an
  automatically generated HTML→text fallback when no text body is set). Emits
  custom headers, `Return-Path`/`Sender`, and non-default `X-Priority`; applies
  RFC 2047 header encoding and quoted-printable body encoding (7-bit clean,
  within the 998-octet SMTP limit); strips CR/LF to prevent header injection;
  exposes the rendered header set via `headers()`.
- `LogTransport` and the built-in `log` mailer, which render the message and
  write the full MIME to a PSR-3 log channel (default level `debug`) instead of
  delivering it.
- Email lifecycle events fired around every send: `email.composing` (at the
  start), `email.sending` (immediately before the transport — returning `false`
  cancels the send and yields `SendResult::cancelled()`), and `email.sent` /
  `email.failed` afterwards (each receiving the `Email` and `SendResult`). All
  emission is gated behind `Config\Mailer::$fireEvents` (default `true`).
- `SendmailTransport`/`MailTransport` and the `sendmail` and `mail` mailers,
  which hand the rendered MIME to a local MTA: `sendmail` pipes it to the
  configured binary (`path`) with `-oi -t`, and `mail` splits it across PHP's
  native `mail()`. Both deliver `Bcc` via a header the MTA strips, set the
  envelope sender (`-f`) from the return path or `From` (validated and
  shell-escaped), and talk through the `SendmailProcess`/`MailFunction` seams
  for testing.
- `LegacyEmailAdapter`, a drop-in replacement for CodeIgniter 4's email service.
  `service('email')` now returns the adapter, which exposes the full legacy
  fluent API (`setFrom`/`setTo`/`setCC`/`setBCC`/`setSubject`/`setMessage`/
  `setAltMessage`/`setMailType`/`setHeader`/`setPriority`/`setWordWrap`/
  `setProtocol`/`attach`/`send`/`printDebugger`/`batchBCCSend`/validation
  helpers) on top of the new `Email` builder and `Mailer`. Honors the legacy
  flat `Config\Email` keys (`protocol`, `SMTP*`, `mailPath`, `mailType`,
  `wordWrap`/`wrapChars`, `priority`, `BCCBatch*`, `DSN`) so existing apps need
  no configuration changes. Invalid addresses are swallowed (`send()` returns
  `false` and never throws). For CI4 parity, `Return-Path` and `Reply-To`
  default to `From`, and sends flow through the event pipeline (a
  `email.sending` listener can cancel). See
  [Legacy Compatibility](legacy-compatibility.md).
- Optional hard word-wrap for the plain-text part: set `Email::$wordWrap` (and
  `$wrapChars`, default 76) and `MessageRenderer` wraps at word boundaries,
  leaving long space-less tokens (e.g. URLs) intact.
- `Mailer::fake()` and the `FakeTransport` test double: swaps the bound
  `service('mailer')` for an in-memory recorder (no network, no real transport)
  and returns it. Exposes content-matcher assertions — `assertSent()`,
  `assertSentTo()`, `assertNotSent()`, `assertNothingSent()`, `assertSentCount()`
  — and `sent()` for inspecting the recorded messages. See
  [Testing Mail](testing.md).
- `Mailable`, an abstract class-based email definition: compose the message in
  `build()` (which runs lazily at send time) via protected `from`/`to`/`subject`/
  `html`/`text` helpers, pick a named mailer with `transport()`, and `send()` to
  route through `service('mailer')`. Each send tags the message with the
  Mailable's class. The `make:mailable` Spark command scaffolds a class into
  `app/Mails/` (`--force` overwrites). `FakeTransport::sent()` and `assertSent()`
  now also accept a Mailable class-string (with an optional closure filter) so
  tests can assert by type. See [Mailables](mailables.md).
