# ADR 0002: `LegacyEmailAdapter` extends the framework's `Email`

- **Status:** Accepted
- **Date:** 2026-10-28

## Context

Postal overrides `service('email')` to return `LegacyEmailAdapter`. The adapter
was a standalone class with the same fluent API as `CodeIgniter\Email\Email`, but
not the same type.

Shield's `emailer()` helper declares a `CodeIgniter\Email\Email` return type and
returns `service('email')->initialize(...)`. In an app with both Shield and
Postal, every Shield email (activation, 2FA, magic link) threw a `TypeError`.
Any other code that type-hints the framework class fails the same way (#52).

## Decision

`LegacyEmailAdapter` extends `CodeIgniter\Email\Email`, so it passes any
`instanceof` check or type declaration for the framework class.

The parent is used only for its type:

- The adapter's constructor does not call `parent::__construct()`, so none of the
  framework's config or transport setup runs.
- Every public method that builds or sends a message is overridden and routes
  through Postal's `Email`, `Mailer`, transports, and events. The only public
  methods inherited are `wordWrap()` (a pure string helper) and `__destruct()`
  (acts only on an open SMTP socket, which the adapter never opens). The
  adapter also reuses the protected `stringToArray()` address splitter.
- `tests/Unit/ShieldEmailerTest.php` fails if a future CodeIgniter release adds a
  public method the adapter does not override.

PHP forces two changes to the adapter's own shape:

- Overrides cannot narrow the parent's untyped parameters, so the native
  parameter types moved into docblocks. Return types stay.
- Properties that share a name with the parent's (`$protocol`, `$mailType`,
  `$body`, `$debugMessage`, …) cannot be redeclared as private or typed. The
  adapter now uses the inherited properties, which are public as in CI4. Its
  attachment records have a different shape from the parent's `$attachments`,
  so they moved to a private `$attachmentRecords`.

`initialize()` now matches the parent: it accepts a `Config\Email` object, and
it clears the message first. Shield relies on that reset, because it reuses the
shared `email` service and does not call `clear()` after a failed send.

## Alternatives rejected

- **Ship a replacement `emailer()` helper.** Shield guards its helper with
  `function_exists()`, so Postal's could win, but only if it loads first. It
  fixes Shield alone, not other code that type-hints the framework class.
- **Document a replacement `emailer()` for Shield apps.** Smallest change, but
  every Shield app hits the `TypeError` until someone reads the docs.

## Consequences

- Shield's `emailer()` works with no app changes.
- Breaking change for beta consumers who subclass `LegacyEmailAdapter` and
  override a method with typed parameters: those overrides must drop the
  parameter types to stay compatible with the framework's signatures.
- The adapter inherits the parent's public properties (`$mailType`,
  `$protocol`, `$SMTPHost`, `$fromEmail`, …). Writing them directly is not
  supported: some are ignored, and others skip the setters' normalisation. Use
  the setters or `initialize()`.
