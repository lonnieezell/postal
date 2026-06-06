# Legacy Compatibility

Postal ships a drop-in replacement for CodeIgniter 4's built-in email service. Existing application code that calls `service('email')` keeps working unchanged — the same fluent `setX` API, the same `send()` returning a boolean — but every message now flows through Postal's `Email` builder, `Mailer`, and transports.

No configuration changes are required: the adapter reads the flat `Config\Email` keys your app already has.

## How it works

`service('email')` returns a `Myth\Postal\LegacyEmailAdapter`. The adapter composes a new `Email` value object from the legacy setters and sends it through a `Mailer` built from your configured protocol. The new `Email` itself carries none of the legacy API — the compatibility layer lives entirely in the adapter.

```php
$email = service('email');

$email->setFrom('me@example.com', 'Me')
    ->setTo('you@example.com')
    ->setSubject('Hello')
    ->setMessage('Plain text body');

if (! $email->send()) {
    log_message('error', $email->printDebugger(['headers']));
}
```

## Supported methods

The full legacy surface is available:

| Area | Methods |
| --- | --- |
| Composition | `setFrom($from, $name, $returnPath)`, `setReplyTo`, `setTo`, `setCC`, `setBCC($bcc, $limit)`, `setSubject`, `setMessage`, `setAltMessage`, `setHeader`, `setPriority` |
| Body type | `setMailType('text'\|'html')` governs whether `setMessage` fills the HTML or text part; `setAltMessage` sets the plain-text alternative for an HTML message |
| Behaviour | `setProtocol('mail'\|'sendmail'\|'smtp')`, `setWordWrap`, `setNewline`/`setCRLF` (accepted, no-op), `initialize(array $config)`, `clear($clearAttachments)` |
| Send / debug | `send($autoClear)` returns `bool`, `batchBCCSend()`, `printDebugger($include)` |
| Validation | `validateEmail`, `isValidEmail`, `cleanEmail` |

`setMessage` order-independence is preserved: whether you call `setMailType('html')` before or after `setMessage`, the body lands in the right part because the choice is resolved at send time.

## Legacy configuration keys

The adapter maps your existing `app/Config/Email.php` onto the new mailer:

- `protocol` selects the transport (`mail`, `sendmail`, or `smtp`) for the send.
- `SMTPHost`, `SMTPUser`, `SMTPPass`, `SMTPPort`, `SMTPTimeout`, `SMTPCrypto`, `SMTPKeepAlive`, and `SMTPAuthMethod` configure the SMTP transport. Auth is enabled automatically when a username and password are both set.
- `mailPath` is the sendmail binary path.
- `mailType`, `priority`, `wordWrap`/`wrapChars`, and `BCCBatchMode`/`BCCBatchSize` seed the matching defaults.
- `DSN` requests delivery-status notifications over SMTP.

`initialize()` merges per-call overrides on top of these without touching the shared config.

## Behavioural notes

- **Invalid addresses are swallowed.** Regardless of the `validate` setting, a malformed address is never thrown — it is recorded in the debugger and skipped, and `send()` returns `false`.
- **BCC batch mode** loops the blind recipients in `BCCBatchSize` chunks; the result is the logical AND of every batch.
- **`printDebugger()`** renders the current message (headers, subject, body) through `MessageRenderer` and prepends any recorded errors. The output is compatible with the legacy debugger, though not byte-identical, and user-supplied values are HTML-escaped.
- **Word wrap** maps to the renderer's hard wrap (see [MIME Rendering](mime-rendering.md)).

## Attachments

`attach()` and `setAttachmentCID()` are present and record their inputs, but attachment rendering is not implemented yet. A send with attachments returns `false` with an explanatory note in `printDebugger()` rather than silently dropping the files. Full attachment support is tracked separately.
