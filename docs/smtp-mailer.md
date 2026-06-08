# The SMTP Mailer

When it's time to send real email, the `smtp` transport speaks SMTP directly to your mail server. It handles the full conversation — EHLO, authentication, the envelope, and the message body — and supports STARTTLS, implicit SSL/TLS, every common auth method, and connection reuse across a batch.

## Configuring an SMTP mailer

Transports are wired through configuration, not code. Add a named mailer to your `Config\Email` and point it at the `smtp` transport, then give it your server's settings:

```php
<?php

public array $mailers = [
    'smtp' => [
        'transport'  => 'smtp',
        'host'       => 'smtp.example.com',
        'port'       => 587,
        'username'   => env('SMTP_USER'),
        'password'   => env('SMTP_PASS'),
        'authType'   => 'login',
        'encryption' => 'tls',
        'timeout'    => 30,
        'keepAlive'  => false,
    ],
];
```

!!! warning "Keep credentials out of committed config"
    Source `username` and `password` from your environment with `env('SMTP_USER')` / `env('SMTP_PASS')` (backed by `.env`), as shown above — never hardcode them in `Config\Email`, which is committed to version control. Add the variables to `.env` (which is git-ignored) and to your deployment's secret store.

Send through it by name, exactly like any other mailer:

```php
<?php

use Myth\Postal\Email;

$email = (new Email())
    ->from('you@example.com', 'Your Name')
    ->to('user@example.com')
    ->subject('Welcome aboard')
    ->html('<p>Glad to have you with us.</p>');

$result = service('mailer')->mailer('smtp')->send($email);
```

To make SMTP the default for every send, set `public string $default = 'smtp';` and call `service('mailer')->send($email)` with no name.

## Configuration reference

Every key in the mailer array beyond `transport` is an SMTP setting. Anything you omit falls back to a sensible default.

| Setting | Default | What it does |
|---------|---------|--------------|
| `host` | `localhost` | The SMTP server hostname or IP. |
| `port` | `25` | The server port. Use `587` for STARTTLS, `465` for implicit SSL. |
| `username` | *(none)* | The auth username. |
| `password` | *(none)* | The auth password, or the OAuth2 access token for `xoauth2`. |
| `authType` | *(none)* | `login`, `plain`, or `xoauth2`. Leave unset for no authentication. |
| `encryption` | *(none)* | `tls` for a STARTTLS upgrade, `ssl` for an implicit TLS connection. |
| `timeout` | `30` | Connection timeout in seconds. |
| `keepAlive` | `false` | Reuse one connection across multiple sends. See below. |
| `helo` | *(system hostname)* | The hostname announced in the EHLO command. Defaults to `gethostname()`. |
| `dsn` | *(none)* | Delivery Status Notification options. See below. |

### Authentication

Pick the method your provider expects with `authType`:

- **`login`** — the classic challenge/response exchange. The most widely supported.
- **`plain`** — username and password sent in a single base64 token.
- **`xoauth2`** — OAuth2 bearer tokens. Put your access token in `password` and the account in `username`.

```php
<?php

'authType' => 'xoauth2',
'username' => 'you@gmail.com',
'password' => $oauthAccessToken,
```

Leave `authType` unset for servers that don't require authentication (a local relay, for instance).

### Encryption

Two encryption modes, matching the two common ports:

=== "STARTTLS (port 587)"
    ```php
<?php

    'port'       => 587,
    'encryption' => 'tls',
    ```
    Connects in the clear, then upgrades the live connection to TLS with `STARTTLS`.

=== "Implicit SSL (port 465)"
    ```php
<?php

    'port'       => 465,
    'encryption' => 'ssl',
    ```
    Negotiates TLS immediately, before the SMTP conversation begins.

!!! tip "Port 465 is implicit TLS automatically"
    Set `port => 465` and Postal uses implicit TLS even if you leave `encryption` unset, per [RFC 8314](https://www.rfc-editor.org/rfc/rfc8314). Setting `encryption => 'ssl'` is still fine — and is what forces implicit TLS on any other port.

## Keep-alive

By default each send opens a fresh connection and closes it with `QUIT`. When you're sending a batch in one request, that handshake-per-message overhead adds up. Set `keepAlive` to `true` to reuse a single connection:

```php
<?php

$mailer = service('mailer')->mailer('smtp');

foreach ($users as $user) {
    $mailer->send($welcomeFor($user));
}
```

The first send connects and authenticates; each later send issues a quick `RSET` and reuses the open connection. If the server has dropped an idle connection in the meantime, the transport reconnects transparently rather than failing the send.

!!! note "One connection per mailer instance"
    Keep-alive lives on the resolved mailer instance, which is cached per name. Grab the mailer once and reuse it for the batch, as shown above.

## Delivery Status Notifications

SMTP can ask the server to report back on delivery with [DSN](https://www.rfc-editor.org/rfc/rfc3461). Configure it with the `dsn` key:

```php
<?php

'dsn' => [
    'notify' => 'FAILURE,DELAY',
    'ret'    => 'HDRS',
    'envid'  => 'order-4821',
],
```

- **`notify`** — when to report: any of `SUCCESS`, `FAILURE`, `DELAY`, or `NEVER`.
- **`ret`** — how much of the message to return on a bounce: `FULL` or `HDRS` (headers only).
- **`envid`** — an envelope identifier echoed back in the notification, handy for correlation.

DSN parameters are only sent when the server advertises `DSN` support in its EHLO reply. If it doesn't, they're silently skipped — your mail still goes out.

## Testing without a real server

The transport talks through a small seam, `Myth\Postal\Transport\SmtpSocket`, rather than touching the network directly. The shipped `StreamSocket` is the real implementation that opens a TCP/TLS stream to your server.

That seam exists so the SMTP conversation is fully testable. Pass your own `SmtpSocket` as the transport's second argument to drive the protocol in a unit test:

```php
<?php

use Myth\Postal\Transport\SmtpTransport;

$transport = new SmtpTransport(['host' => 'mail.test'], $myFakeSocket);
```

Postal's own suite uses a scripted double for exactly this. That double ships in the package's `tests/` directory — it's internal test plumbing, not public API, so don't rely on it from your application.

## Security

!!! danger "Handle credentials and untrusted addresses with care"
    - **Source secrets from the environment.** Read `username`/`password` from `.env` via `env()` and never commit them in `Config\Email`. See the warning above.
    - **SMTP command injection is blocked.** CR/LF is stripped from every command line, so an address, HELO name, or DSN value can't smuggle extra SMTP commands. Still, validate addresses at your application boundary.
    - **Credentials never reach your logs.** A failed `AUTH` reports against a safe label, so base64-encoded usernames and passwords stay out of exception messages and `SendResult` errors.
    - **Use encryption for authenticated sends.** `login` and `plain` put your credentials on the wire. Pair them with `encryption => 'tls'` or `'ssl'` so they're never sent in the clear.

## Next steps

- [The Log Mailer](log-mailer.md) — inspect messages in development without sending them
- [MIME Rendering](mime-rendering.md) — what the message body looks like on the wire
