# DKIM Signing

A DKIM signature is how a receiving server knows the message really came from your domain and wasn't tampered with in transit. It's also one of the things DMARC checks before deciding whether your mail lands in the inbox or the spam folder. Postal can sign messages for you at the library level — the signature is computed as the message is sent and travels with it.

You enable it by adding one `dkim` block to a mailer. The transport renders the message, signs it, and hands the signed bytes straight to delivery. There's nothing to call at send time and nothing to change in your application code.

## Configuration

Add a `dkim` key to any SMTP or sendmail mailer. It takes three values: the `domain` you're signing as, the `selector` that names the public key in DNS, and the `privateKey` used to sign:

```php
<?php

public array $mailers = [
    'primary' => [
        'transport'  => 'smtp',
        'host'       => 'smtp.example.com',
        'port'       => 587,
        'username'   => env('SMTP_USER'),
        'password'   => env('SMTP_PASS'),
        'dkim'       => [
            'domain'     => 'example.com',
            'selector'   => 'postal',
            'privateKey' => WRITEPATH . 'keys/dkim-private.pem',
        ],
    ],
];
```

`privateKey` accepts either a path to a readable PEM file (as above) or the PEM text itself — handy when the key comes from an environment variable or a secrets manager:

```php
<?php

'privateKey' => env('DKIM_PRIVATE_KEY'),
```

!!! warning "Protect the private key"
    The signing key is a secret. Never paste the PEM text inline into `Config\Email` (it's committed to version control) — point `privateKey` at a key file kept **outside** your repository, or load the PEM from `.env`/a secrets manager via `env()`. Restrict the key file's permissions to the web user (for example `chmod 600`).

The matching public key lives in DNS as a `TXT` record at `<selector>._domainkey.<domain>` — for the example above, `postal._domainkey.example.com`. Until that record is published, receivers can't verify the signature, so set up DNS before you switch signing on.

A `dkim` block that's missing its `domain`, `selector`, or a usable `privateKey` is a configuration error: the mailer throws a `PostalException` the first time it's resolved, rather than sending unsigned mail and hoping you notice.

## How it works

When you send through a signed mailer, the transport:

1. Renders the message to its raw MIME form once.
2. Computes a `relaxed/relaxed`, `rsa-sha256` signature over the standard header set (`From`, `To`, `Cc`, `Subject`, `Date`, `Message-ID`, and the MIME headers) plus a hash of the body.
3. Prepends the resulting `DKIM-Signature` header.
4. Delivers those exact bytes — verbatim.

That last point is the important one. The signature covers the precise bytes of the headers and body, so the message can't be re-rendered after signing or the signature breaks. Postal signs once and the transport transmits what was signed, which is why signing is built into the delivery path rather than bolted on as a separate step.

!!! warning "Sign last, deliver unchanged"
    A DKIM signature is only valid for the message as it was signed. If something downstream rewrites the body — a relay that injects a footer, a mailing-list manager that adds an unsubscribe block — the signature no longer matches and verification fails. Library-level signing works precisely because Postal is the last thing to touch the message before it goes on the wire.

## Raw-MIME transports only

DKIM signing is available on the transports that deliver raw MIME and can carry the signed bytes untouched: **`smtp`** and **`sendmail`**.

It is **not** available on `ses`, `mail`, `null`, or `log`. Adding a `dkim` block to one of those raises a `PostalException` when the mailer is resolved:

```php
<?php

'broken' => [
    'transport' => 'ses',
    'dkim'      => ['domain' => 'example.com', 'selector' => 'postal', 'privateKey' => '...'],
],
// PostalException: The "ses" transport cannot DKIM-sign: it does not deliver
// raw MIME (such providers sign server-side). Remove the "dkim" config…
```

This isn't a missing feature — it's the right boundary. Amazon SES and similar API providers sign outbound mail on their own infrastructure once you've set up the DKIM records in their console. Signing the message a second time in Postal would be redundant at best and conflicting at worst. Let the provider own signing for those transports, and use Postal's signing for the ones that hand raw MIME to an MTA.

## With the failover mailer

DKIM is applied **per leaf**. A [failover mailer](failover-mailer.md) is just a coordinator — it never renders or delivers a message itself — so it stays signing-agnostic. Each child that needs signing carries its own `dkim` block:

```php
<?php

public array $mailers = [
    'relay-a' => [
        'transport' => 'smtp',
        'host'      => 'smtp-a.example.com',
        'dkim'      => [
            'domain'     => 'example.com',
            'selector'   => 'postal',
            'privateKey' => WRITEPATH . 'keys/dkim-private.pem',
        ],
    ],
    'relay-b' => [
        'transport' => 'smtp',
        'host'      => 'smtp-b.example.com',
        'dkim'      => [
            'domain'     => 'example.com',
            'selector'   => 'postal',
            'privateKey' => WRITEPATH . 'keys/dkim-private.pem',
        ],
    ],
    'primary' => [
        'transport' => 'failover',
        'chain'     => ['relay-a', 'relay-b'],
    ],
];
```

!!! note "Each leaf signs itself"
    Whichever child actually delivers the message signs it on the way out, so a message that falls through from `relay-a` to `relay-b` is signed by `relay-b`. List a `dkim` block on every leaf that should send signed mail; a leaf without one sends unsigned, even inside a signed chain.

## Next steps

- [The SMTP Mailer](smtp-mailer.md) — the most common home for a `dkim` block
- [The Sendmail & Mail Mailers](sendmail-mail-mailers.md) — sign through a local `sendmail` binary
- [The Failover Mailer](failover-mailer.md) — sign each leaf of a resilient chain
