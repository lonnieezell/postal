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
  RFC 2047 header encoding and word-wrap; strips CR/LF to prevent header
  injection; exposes the rendered header set via `headers()`.
- `LogTransport` and the built-in `log` mailer, which render the message and
  write the full MIME to a PSR-3 log channel (default level `debug`) instead of
  delivering it.
