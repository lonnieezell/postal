# Mailables

A Mailable is a reusable, class-based email. Instead of building an `Email` inline every time you need to send a welcome message, you describe it once in a class and send it wherever you need it.

```php
<?php

(new WelcomeEmail($user))->send();
```

That's the whole idea: the message lives in one place, the call site stays a single line, and tests can assert on it by type.

## A quick example

A Mailable extends `Myth\Postal\Mailable` and composes the message in `build()`:

```php
<?php

namespace App\Mails;

use Myth\Postal\Mailable;

class WelcomeEmail extends Mailable
{
    public function __construct(private readonly User $user)
    {
        parent::__construct();
    }

    protected function build(): void
    {
        $this->from('hello@example.com', 'Acme')
            ->to($this->user->email, $this->user->name)
            ->subject('Welcome aboard')
            ->html(view('emails/welcome', ['user' => $this->user]))
            ->text('Glad to have you, ' . $this->user->name);
    }
}
```

Send it from anywhere:

```php
<?php

$result = (new WelcomeEmail($user))->send();
```

`send()` returns the same [`SendResult`](smtp-mailer.md) you get from the mailer directly, so you can check `$result->success` if you need to.

!!! warning "Call the parent constructor"
    If your Mailable defines its own `__construct()`, call `parent::__construct()`. That's what sets up the underlying `Email` the helpers compose into.

## Composing in `build()`

`build()` is where you describe the message. It runs **lazily**, once, when you call `send()` — never in the constructor. So passing data into the constructor and reading it in `build()` (like `$this->user` above) always works.

Inside `build()` you have protected helpers that mirror the [`Email`](index.md) builder:

| Helper | What it sets |
|--------|--------------|
| `from(string $address, string $name = '')` | The sender |
| `to(array\|string $address, string $name = '')` | A recipient (call more than once to add several) |
| `subject(string $subject)` | The subject line |
| `html(string $html)` | The HTML body |
| `text(string $text)` | The plain-text body |

Each returns `$this`, so they chain.

## Choosing a mailer

By default a Mailable sends through your default mailer. To route through a named mailer from `Config\Mailer`, call `transport()` in `build()`:

```php
<?php

protected function build(): void
{
    $this->transport('marketing')
        ->from('news@example.com')
        ->to($this->user->email)
        ->subject('This month at Acme')
        ->html($this->render());
}
```

`transport('marketing')` selects the mailer named `marketing` — the same name you'd pass to `service('mailer')->mailer('marketing')`.

## Generating a Mailable

Spark scaffolds one for you:

```bash
php spark make:mailable Welcome
```

That writes `app/Mails/Welcome.php` in the `App\Mails` namespace, with an empty `build()` ready to fill in. The generated class also implements [`Previewable`](#previewing-mailables) with a `previewInstance()` stub, so it shows up in the browser preview out of the box. Pass `--force` to overwrite an existing file:

```bash
php spark make:mailable Welcome --force
```

## Previewing Mailables

Designing an email is a lot easier when you can see it. Postal can render any Mailable straight to your browser — HTML, plain text, and the raw MIME — without sending a thing.

It's **off by default** and **never runs in production**. You opt in per environment.

### Turning it on

Two independent locks both have to be open before a preview is reachable:

1. The environment is **not** `production`.
2. `Config\Postal::$enablePreview` is `true`.

Either lock alone keeps previews hidden, so a stray flag can't leak mail in production and a misconfigured environment can't expose it on its own.

Create `app/Config/Postal.php` (or set the value from `.env`):

```php
<?php

namespace Config;

use Myth\Postal\Config\Postal as BasePostal;

class Postal extends BasePostal
{
    public bool $enablePreview = true;
}
```

=== ".env"
    ```ini
    postal.enablePreview = true
    ```

!!! danger "Never enable this in production"
    Even with the flag on, Postal refuses to register the preview routes when `ENVIRONMENT` is `production`. Leave the flag in your development config and you've got nothing to worry about.

A few more knobs on `Config\Postal`:

| Property | Default | What it does |
|----------|---------|--------------|
| `$enablePreview` | `false` | The opt-in switch described above |
| `$previewPath` | `'postal/preview'` | The URI prefix the preview is mounted under |
| `$mailableNamespaces` | `['App\Mails' => APPPATH . 'Mails']` | Namespace → directory pairs scanned for previewable Mailables |

Add a pair to `$mailableNamespaces` for every place your Mailables live:

```php
<?php

public array $mailableNamespaces = [
    'App\Mails'           => APPPATH . 'Mails',
    'Acme\Billing\Mails'  => ROOTPATH . 'modules/Billing/Mails',
];
```

### Making a Mailable previewable

The preview needs sample data to render with — a Mailable usually takes a real `User`, and there isn't one at preview time. Implement `Myth\Postal\Previewable` and return an instance built from fake data:

```php
<?php

namespace App\Mails;

use Myth\Postal\Mailable;
use Myth\Postal\Previewable;

class WelcomeEmail extends Mailable implements Previewable
{
    public function __construct(private readonly User $user)
    {
        parent::__construct();
    }

    public static function previewInstance(): static
    {
        return new static(User::makeSample());
    }

    protected function build(): void
    {
        // ...
    }
}
```

Keeping the sample data right here in the class means it travels with the Mailable — discoverable, version-controlled, and refactor-safe. Only Mailables that implement `Previewable` show up in the preview; the rest are ignored.

!!! tip "Generated Mailables are ready to go"
    `make:mailable` scaffolds the `implements Previewable` clause and a `previewInstance()` stub for you. Fill in the sample data and you're done.

### What you'll see

Visit `/postal/preview` (or whatever you set `$previewPath` to). The index lists every previewable Mailable with its subject. Click one to open the detail view, which has three tabs:

- **HTML** — rendered in a sandboxed `<iframe>`, so the email's CSS can't bleed into the preview page (or vice versa)
- **Text** — your `text()` body if you supplied one, otherwise the version Postal auto-generates from the HTML
- **Raw MIME** — the full message exactly as it goes on the wire, headers and all — handy for debugging encoding or attachments

Attachments are listed by name. Inline `cid:` images won't render inside the iframe — that's an inherent limit of the sandbox, not a bug in your email.

If a Mailable throws while building its preview, it still appears in the list with an error badge, and its detail page shows the exception inline. One broken Mailable never takes down the whole preview.

### Rendering without sending

The preview is built on `Mailable::render()`, a public seam you can use anywhere:

```php
<?php

$email = (new WelcomeEmail($user))->render();

echo $email->subject;
echo $email->htmlBody;
```

`render()` runs `build()` and hands back the composed [`Email`](index.md) — it never sends. It's the build-without-send counterpart to `send()`.

## Next steps

- [Testing Mail](testing.md) — assert that a Mailable was sent, by class, without touching a real transport
- [MIME Rendering](mime-rendering.md) — how your HTML and text bodies become a wire-ready message
