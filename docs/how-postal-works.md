# How Postal Works

You can send mail with Postal without reading this page. It's here for when config keys, events or failures don't behave the way you expect, and you want to know what's happening underneath.

## Messages, transports and mailers

Postal separates three things that CodeIgniter's `Email` class keeps in one object:

- An **`Email`** is *what* to send: addresses, subject, bodies, headers, attachments. It knows nothing about delivery.
- A **transport** is *how* a message is delivered: over SMTP, through a sendmail binary, as a JSON request to an API, into a log. Each transport is a class implementing `TransportInterface`.
- A **mailer** is a *named* transport with its settings, wrapped in the send pipeline described below. `smtp`, `marketing` and `backup` could all be mailers, and two of them could use the same transport with different servers.

That's why your config lists `$mailers` while the classes are called transports. You choose a mailer by name; the mailer decides which transport does the work.

## From config to a mailer

`service('mailer')` returns the `MailerManager`. When you ask it for a mailer, by name or the default, it:

1. Looks up the entry in `Config\Mailer::$mailers`.
2. Finds that entry's `transport` in `$transports` and builds the class, passing it the **whole mailer entry** as its settings. Each transport reads the keys it understands and ignores the rest.
3. Wraps the transport in a mailer, together with your `$fireEvents`, suppression list and unsubscribe settings.
4. Caches the mailer by name, so later calls get the same instance. That's what lets SMTP keep-alive reuse one connection across a batch.

Two kinds of entry are built differently:

- A **`failover`** mailer builds each mailer named in its `chain` the same way, and wraps them in a transport that tries them in order.
- A mailer with a **`dkim`** block has its transport wrapped in a signer, which signs the rendered message before handing it on.

Nothing is built until you first use a mailer, so a configuration mistake, such as an unknown transport or a missing API key, throws then rather than when your app boots.

## What happens during a send

Every send through a mailer follows the same steps:

```
send($email)
  │
  ├─ 1. clone the message
  ├─ 2. email.composing
  ├─ 3. suppression list ── email.suppressed per removed recipient
  │                       └─ nobody left? → cancelled
  ├─ 4. List-Unsubscribe header
  ├─ 5. email.sending ───── a listener returned false? → cancelled
  ├─ 6. transport: render → sign → deliver
  └─ 7. email.sent or email.failed → SendResult
```

1. **Clone.** Postal works on a copy, so changing your `Email` after `send()` can't affect what goes out, and a listener's changes never leak back into your object.
2. **`email.composing`.** The first [event](events.md), for app-wide defaults such as an archive Bcc.
3. **Suppression.** Runs after `composing`, so recipients a listener added are filtered too. If every recipient is removed, the send stops with a cancelled result and the transport is never called.
4. **Unsubscribe header.** Added only for a single `To` recipient and only when you haven't set one. It comes after suppression, so it's never generated for an address that won't receive the message.
5. **`email.sending`.** The last point to change or stop the message. It is the only event that can cancel, because it's the only one that runs when the message is final but not yet sent.
6. **Transport.** The transport turns the message into whatever it delivers and sends it. See below.
7. **Result.** `email.sent` or `email.failed` fires with the [`SendResult`](reference/send-result.md), which is then returned to you.

With `$fireEvents` off, steps 2, 5 and 7 fire nothing, so no listener can cancel or change the message. Suppression and the unsubscribe header still run.

## Two kinds of transport

Transports fall into two groups, and which group a transport is in decides what it can do.

**Raw-MIME transports**, such as `smtp`, `sendmail`, `mail` and `log`, turn the message into a complete email with [`MessageRenderer`](mime-rendering.md) and pass on those exact bytes. Postal controls every header and part, so:

- Postal generates the plain-text part when you only set HTML.
- [DKIM signing](dkim-signing.md) works on `smtp` and `sendmail`, because the signed bytes are the bytes delivered.
- Provider tags from `metadata()` have nowhere to go, so they're ignored.

**API transports**, such as `ses` and `resend`, send fields to a provider's HTTP API, and the provider builds the email. So:

- The provider signs the message with its own DKIM keys, and a `dkim` block is a configuration error.
- `metadata()` becomes the provider's tags.
- Anything the provider can't carry, such as a custom return path on Resend, is dropped and logged rather than failing the send.

## Mailables and the legacy adapter

Both end up in the same pipeline:

- A [Mailable](mailables.md) builds an `Email` in `build()` and sends it through `service('mailer')`, tagging it with its class name.
- `service('email')` returns the [legacy adapter](legacy-compatibility.md), which turns CodeIgniter-style calls such as `setTo()` and `setMessage()` into an `Email`. Once you've published `Config\Mailer`, it sends through your default mailer like everything else.

So events, suppression and transports behave the same whichever way a message was written.

## How failures surface

Postal separates three kinds of "it didn't go":

| What happened | What you get |
|---------------|--------------|
| The server or provider rejected the message | `SendResult` with `success` false and an `error` |
| A listener or the suppression list stopped it | `SendResult` with `cancelled` true; the transport never ran |
| The configuration is wrong (unknown mailer, missing key, bad DKIM block) | A `PostalException` thrown when the mailer is first used |

Delivery problems are values you check; configuration problems are exceptions you fix. A failover mailer relies on that: it moves to the next child on a failed result, and also on an exception, so one broken provider can't take the whole send down.

## Next steps

- [MIME Rendering](mime-rendering.md) — what a raw-MIME transport actually sends
- [Events](events.md) — hooking into the send steps above
- [Writing a Custom Transport](custom-transports.md) — adding your own transport to the picture
