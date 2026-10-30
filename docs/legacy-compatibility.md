# Legacy Compatibility

Postal ships a drop-in replacement for CodeIgniter 4's built-in email service. Existing application code that calls `service('email')` keeps working unchanged — the same fluent `setX` API, the same `send()` returning a boolean — but every message now flows through Postal's `Email` builder, `Mailer`, and transports.

No configuration changes are required: the adapter reads the flat `Config\Email` keys your app already has. Once you publish `app/Config/Mailer.php`, the adapter sends through your default mailer instead (see [Which mailer sends](#which-mailer-sends)).

## How it works

`service('email')` returns a `Myth\Postal\LegacyEmailAdapter`. The adapter composes a new `Email` value object from the legacy setters and sends it through a `Mailer` built from your configured protocol. The new `Email` itself carries none of the legacy API — the compatibility layer lives entirely in the adapter.

```php
<?php

$email = service('email');

$email->setFrom('me@example.com', 'Me')
    ->setTo('you@example.com')
    ->setSubject('Hello')
    ->setMessage('Plain text body');

if (! $email->send()) {
    log_message('error', $email->printDebugger(['headers']));
}
```

## Which mailer sends

The adapter picks its transport by asking one question: has the app configured Postal?

- **No `app/Config/Mailer.php`.** The adapter builds a transport from the flat `Config\Email` keys, as described in [Legacy configuration keys](#legacy-configuration-keys).
- **An app `Config\Mailer` exists** (any subclass of `Myth\Postal\Config\Mailer`, such as the one `php spark publish` writes). The adapter sends through the default mailer, `service('mailer')->mailer()`. The flat transport keys (`protocol`, `mailPath`, `SMTP*`, `DSN`) are ignored, including keys passed to `initialize()` or `setProtocol()`. Shield's `emailer()` always passes them, and its mail still goes through your default mailer.

Message keys (`mailType`, `wordWrap`/`wrapChars`, `priority`, `BCCBatchMode`/`BCCBatchSize`, `fromEmail`/`fromName`) apply either way. When the default mailer handles the send, its `fireEvents`, suppression list and unsubscribe settings apply too.

!!! warning "Behaviour change"
    Before, publishing `Config\Mailer` had no effect on `service('email')`, which kept sending through `Config\Email` (usually PHP's `mail()`). If you publish `Config\Mailer`, make sure its default mailer is the one your legacy mail should use. [`LegacyConfigMapper`](#reusing-the-legacy-mapping) can turn your `Config\Email` settings into a mailer definition.

## Type compatibility and Shield

`LegacyEmailAdapter` extends `CodeIgniter\Email\Email`. Code that type-hints the framework class accepts it, so [CodeIgniter Shield](https://github.com/codeigniter4/shield)'s `emailer()` helper works with no changes. Activation, two-factor, and magic-link emails all send through Postal's transports and fire Postal's events (unless your `Config\Mailer` turns `fireEvents` off).

The framework class is used only for its type. The adapter never runs the parent constructor, and it overrides every method that builds or sends a message. The framework's own sender is never used. See [ADR 0002](adr/0002-legacy-adapter-extends-framework-email.md).

Set options through the setters or `initialize()`. The adapter inherits the framework's public properties (`$mailType`, `$SMTPHost`, …), but writing them directly is not supported.

If you subclass the adapter, your overrides must match the framework's untyped parameters. For example, write `setFrom($from, $name = '', $returnPath = null)`, not `setFrom(string $from, ...)`.

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

When the app has not published `Config\Mailer`, the adapter maps your existing `app/Config/Email.php` onto the new mailer:

- `protocol` selects the transport (`mail`, `sendmail`, or `smtp`) for the send. It is case-insensitive, and an unknown value falls back to `mail`.
- `SMTPHost`, `SMTPUser`, `SMTPPass`, `SMTPPort`, `SMTPTimeout`, `SMTPCrypto`, `SMTPKeepAlive`, and `SMTPAuthMethod` configure the SMTP transport. Auth is enabled automatically when a username and password are both set.
- `mailPath` is the sendmail binary path.
- `mailType`, `priority`, `wordWrap`/`wrapChars`, and `BCCBatchMode`/`BCCBatchSize` seed the matching defaults.
- `DSN` requests delivery-status notifications over SMTP.

`initialize()` merges per-call overrides on top of these without touching the shared config.

## Reusing the legacy mapping

`Myth\Postal\LegacyConfigMapper::toMailer(array $flat): array` exposes the mapping the adapter uses. Pass it the flat `Config\Email` keys, for example from a settings table, and it returns a mailer definition you can put in `Config\Mailer::$mailers`. Mail sent through that mailer goes out the same way as it would through the adapter.

```php
use Myth\Postal\LegacyConfigMapper;

$mailer = LegacyConfigMapper::toMailer([
    'protocol' => 'smtp',
    'SMTPHost' => 'smtp.example.com',
    'SMTPPort' => '587',
    'SMTPUser' => 'user',
    'SMTPPass' => 'secret',
]);
// ['transport' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587,
//  'timeout' => 5, 'encryption' => 'tls', 'keepAlive' => false,
//  'username' => 'user', 'password' => 'secret', 'authType' => 'login']
```

| Input key | Output key | Default |
| --- | --- | --- |
| `protocol` | `transport` | `mail` |
| `mailPath` | `path` (sendmail) | `/usr/sbin/sendmail` |
| `SMTPHost` | `host` | `''` |
| `SMTPPort` | `port` | `25` |
| `SMTPTimeout` | `timeout` | `5` |
| `SMTPCrypto` | `encryption` | `tls` |
| `SMTPKeepAlive` | `keepAlive` | `false` |
| `SMTPUser` | `username` | `''` |
| `SMTPPass` | `password` | `''` |
| `SMTPAuthMethod` | `authType` | `login` |
| `DSN` | `dsn` | `false` |

- **Defaults.** Missing or `null` keys take `Config\Email`'s defaults shown above. Other keys are ignored.
- **Casting.** Values are cast with CodeIgniter's `DataCaster`, the engine behind Model and Entity `$casts`, so database-style strings work: `'587'` becomes `587`, and `'0'`, `'false'`, `''` and `'f'` become `false`. Other keys are cast to strings.
- **Protocol.** The protocol is lowercased. Anything other than `smtp`, `sendmail` or `mail` falls back to `mail`.
- **Per transport.** `sendmail` returns only `transport` and `path`, and `mail` returns only `transport`. The SMTP keys appear only for `smtp`.
- **Credentials.** `username`, `password` and `authType` are included only when `SMTPUser` and `SMTPPass` are both non-empty, so no empty credentials are passed.
- **DSN.** When `DSN` is true, `dsn` is `['notify' => 'SUCCESS,FAILURE,DELAY', 'ret' => 'FULL']`. Otherwise it is left out.

## Behavioural notes

- **Invalid addresses are swallowed.** Regardless of the `validate` setting, a malformed address is never thrown — it is recorded in the debugger and skipped, and `send()` returns `false`.
- **Header defaults match CI4.** The envelope sender (`Return-Path`) defaults to the `From` address when you don't pass a return path to `setFrom()`, and `Reply-To` defaults to `From` when you don't set one.
- **Events fire.** Adapter sends flow through Postal's [event pipeline](events.md): a listener on `email.sending` can cancel the send (which then returns `false`). When your app publishes `Config\Mailer`, its `fireEvents` setting decides whether they fire.
- **BCC batch mode** loops the blind recipients in `BCCBatchSize` chunks; the result is the logical AND of every batch.
- **`printDebugger()`** renders the current message (headers, subject, body) through `MessageRenderer` and prepends any recorded errors. The output is compatible with the legacy debugger, though not byte-identical, and user-supplied values are HTML-escaped.
- **Word wrap** maps to the renderer's hard wrap (see [MIME Rendering](mime-rendering.md)).

## Attachments

`attach()` accepts a file path and the message is delivered with the file attached, just like the native service. `setAttachmentCID()` marks a previously attached file as an inline image and returns its Content-ID, so HTML bodies can reference it with a `cid:` URL.

An unreadable attachment path fails the send: `send()` returns `false` with an explanatory note in `printDebugger()` rather than throwing.

**Limitation:** the in-memory buffer form of CI4's `attach()` — passing raw file *contents* as the first argument together with an explicit MIME type instead of a path — is not supported. Such a value is treated as a path, found to be unreadable, and fails the send. Attach files by path instead.
