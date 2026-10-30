# SendResult

`Myth\Postal\SendResult` is what every send returns: `service('mailer')->send()`, `Mailable::send()`, and each transport's `send()`. It is read-only.

```php
<?php

$result = service('mailer')->send($email);

if (! $result->success) {
    log_message('error', 'Mail failed: ' . $result->error);
}
```

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$success` | `bool` | `true` when the transport accepted the message. |
| `$messageId` | `?string` | The message ID, when the transport has one. See the table below. |
| `$error` | `?string` | Why the send failed or was cancelled. `null` on success. |
| `$raw` | `mixed` | The provider's response or exception, when there is one. See the table below. |
| `$cancelled` | `bool` | `true` when the send was stopped before reaching the transport. `$success` is then `false`. |

## Outcomes

| Outcome | `$success` | `$cancelled` | `$error` |
|---------|------------|--------------|----------|
| Sent | `true` | `false` | `null` |
| Failed | `false` | `false` | The transport's error message |
| Cancelled by an `email.sending` listener | `false` | `true` | `null` |
| Cancelled because every recipient was suppressed | `false` | `true` | `'All recipients are suppressed'` |

Check `$cancelled` before treating a result as a delivery failure: a cancelled send never reached the transport. See [Cancelling a send](../events.md#cancelling-a-send) and [Suppression list](../suppression-unsubscribe.md#suppression-list).

## By transport

| Transport | `$messageId` on success | `$raw` |
|-----------|-------------------------|--------|
| `smtp` | The `Message-ID` header | — |
| `sendmail`, `mail` | The `Message-ID` header | — |
| `ses` | The SES message ID | The SES response array, or the `SesV2Exception` on failure |
| `resend` | The Resend email ID | The decoded response body, or the exception on a network failure |
| `failover` | Whatever the child that succeeded returned | Whatever the child that succeeded returned |
| `log`, `null` | `null` | — |

When every child of a [failover mailer](../failover-mailer.md) fails, `$error` lists each child's error: `All failover transports failed: …; …`.

## Factories

Transports build results with three static methods. You need them only when [writing your own transport](config-mailer.md#transports) or a test double.

| Method | Result |
|--------|--------|
| `SendResult::ok(?string $messageId = null, mixed $raw = null)` | A successful send. |
| `SendResult::fail(string $error, mixed $raw = null)` | A failed send. |
| `SendResult::cancelled(?string $reason = null)` | A cancelled send. `$reason` becomes `$error`. |

A transport should return `fail()` for a rejected message and throw only for configuration or connection errors.

## Related

- [Events](../events.md) — `email.sent` and `email.failed` listeners receive the result
- [Email](email.md) — the message you send
