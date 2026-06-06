# Changelog

## [Unreleased]

### Added

- Core send pipeline: compose an `Email` and send it through a transport via
  `service('mailer')`, receiving a `SendResult`.
- `Email` message builder with `from`/`replyTo`/`to`/`cc`/`bcc`/`subject`/`html`/`text`/
  `header`/`priority`/`returnPath` (mutable, chainable).
- `Address` value object that parses and renders `"Name <email>"`.
- `SendResult` with `ok()`/`fail()`/`cancelled()` factories.
- `TransportInterface` and the `NullTransport` implementation.
- `MailerManager` (resolves the default and named mailers from `Config\Email` via an
  extensible transport map, lazily and cached) and a minimal `Mailer` that clones the
  message at the dispatch boundary.
- `Config\Email` configuration and the `service('mailer')` service.
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
  emission is gated behind `Config\Email::$fireEvents` (default `true`).
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
  `false` and never throws). See [Legacy Compatibility](legacy-compatibility.md).
- Optional hard word-wrap for the plain-text part: set `Email::$wordWrap` (and
  `$wrapChars`, default 76) and `MessageRenderer` wraps at word boundaries,
  leaving long space-less tokens (e.g. URLs) intact.
