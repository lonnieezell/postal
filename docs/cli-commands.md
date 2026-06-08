# CLI Commands

Postal ships a couple of `spark` commands to speed up everyday work. This page covers `email:test`; the generator for [Mailables](mailables.md#generating-a-mailable) lives with the feature it scaffolds.

## `email:test`

Sends a real test message through a mailer so you can confirm a transport is configured and reachable. It runs the **full** pipeline — lifecycle [events](events.md), [suppression](suppression-unsubscribe.md), and the transport itself — then prints the provider message id on success or the error on failure.

```bash
php spark email:test you@example.com
```

```
Sent. Message ID: <abc123@mail.example.com>
```

!!! warning "This sends for real"
    Unlike [`Mailer::fake()`](testing.md), `email:test` hands the message to the configured transport. Point it at a safe mailer (the `log` or `null` mailer) while you're poking around, and switch to your live mailer only when you mean to deliver.

### Choosing a mailer

By default the message goes through your configured default mailer. Pass `--mailer` (or its alias `--transport`) to pick a [named mailer](smtp-mailer.md) from `Config\Email`:

```bash
php spark email:test you@example.com --mailer smtp
```

!!! note "Space, not `=`"
    CodeIgniter's spark options are space-separated. Use `--mailer smtp`, not `--mailer=smtp`.

### Prompting for the recipient

Leave the address off and the command asks for it:

```bash
php spark email:test
```

```
Recipient address: you@example.com
```

### Where the From address comes from

`email:test` reads the `From` address from the framework's `Config\Email` — `fromEmail` and `fromName`. If `fromEmail` is empty, it falls back to sending from the recipient address so the message always has a valid sender.

```php
<?php

// app/Config/Email.php
public string $fromEmail = 'postmaster@example.com';
public string $fromName  = 'Example App';
```

### On failure

When the transport reports a failure, the command prints the error and exits non-zero, so it plays nicely in scripts and CI:

```
Send failed: Connection refused (smtp.example.com:587)
```

## Next steps

- [Testing Mail](testing.md) — assert on what your app *would* send, without delivering
- [The SMTP Mailer](smtp-mailer.md) — configure the named mailer you're testing
- [Events](events.md) — the lifecycle hooks `email:test` fires on its way through the pipeline
