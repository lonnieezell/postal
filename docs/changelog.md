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
