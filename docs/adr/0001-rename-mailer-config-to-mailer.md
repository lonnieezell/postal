# ADR 0001: Rename the mailer config class to `Mailer`

- **Status:** Accepted
- **Date:** 2026-08-06

## Context

Postal's mailer configuration class was `Myth\Postal\Config\Email`. Its short
name collided with CodeIgniter's own legacy `Config\Email`, which the framework
ships in every application and which this package still reads for the legacy
adapter and `spark email:test`.

Because of that collision, `MailerManager` resolved its config by fully-qualified
class name:

```php
$this->config = $config ?? config(EmailConfig::class);
```

`config('Email')` would have found the framework's legacy config instead, so the
FQCN form was the only way to get the right class. A comment on that line claimed
the FQCN form "still honours app-level overrides via Factories". It does not:
`Factories::locateClass()` prefers an application-namespace class only when the
requested alias is *not* namespaced.

The consequence was that an application could not configure Postal at all. The
installation guide told users to create `app/Config/Email.php` extending the
package config; that file was never consulted, and the package's own default
(`$default = 'null'`) silently stayed in force — every send disappearing into the
null transport.

## Decision

Rename the package's mailer config to `Myth\Postal\Config\Mailer` and resolve it
by short name (`config('Mailer')`), so the framework's application preference
applies. Ship a `Publishers\ConfigPublisher` that `spark publish` runs to write
`app/Config/Mailer.php` — a `Config\Mailer` extending the package class — without
overwriting an existing copy.

`Mailer` is free in the framework's `Config` namespace and matches the package's
existing vocabulary: named *mailers*, `MailerManager`, `service('mailer')`.

The published stub lives in `stubs/`, outside `src/`. It declares a class in the
application's `Config` namespace, so inside the package's PSR-4 root the
autoloader would resolve and include it — shadowing the application's own
published config for the rest of the request, or fataling on the redeclaration
when both are loaded (`Publisher::discover()` asks the autoloader about every
file under `Publishers/`).

The framework's legacy `Config\Email` is left alone. `LegacyEmailAdapter`,
`Config\Services::email()`, and `EmailTestCommand` continue to read it by FQCN,
which is correct for them: they *want* the framework's class, not an override.

## Alternatives rejected

- **Keep the name, register an alias with `Factories::define()`.** Papers over
  the collision at bootstrap and leaves two different `Config\Email` classes in
  play, which is exactly the confusion that caused the bug.
- **Keep the name, document `Factories::define()` as the user's job.** Pushes
  framework plumbing onto every consumer for something that should be a published
  file, and still leaves `app/Config/Email.php` ambiguous between the two
  meanings.
- **Fold the mailer settings into the existing `Config\Postal`.** Would have
  meant one config object for two unrelated concerns (mail delivery and the
  in-browser preview) and a larger, more disruptive migration for early adopters
  than a rename.
- **Name it `Config\Mail` or `Config\Mailers`.** Both are free, but neither
  matches the vocabulary already used throughout the package and its docs.

## Consequences

- Breaking change for beta consumers: `Myth\Postal\Config\Email` no longer
  exists. Applications rename their subclass to `Config\Mailer` and extend
  `Myth\Postal\Config\Mailer`. Recorded in `roave-bc-check.yaml`.
- The documented override path now works, and is exercised by
  `tests/Unit/MailerConfigResolutionTest.php` against the very file the publisher
  ships.
