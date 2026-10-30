# Previewing Mailables

Designing an email is a lot easier when you can see it. Postal can render any Mailable straight to your browser — HTML, plain text, and the raw MIME — without sending a thing.

It's **off by default** and **never runs in production**. You opt in per environment.

## Turning it on

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

`Config\Postal` also sets the preview's URI prefix and where it looks for Mailables — see the [Config\Postal reference](reference/config-postal.md#preview).

Add a pair to `$mailableNamespaces` for every place your Mailables live:

```php
<?php

public array $mailableNamespaces = [
    'App\Mails'           => APPPATH . 'Mails',
    'Acme\Billing\Mails'  => ROOTPATH . 'modules/Billing/Mails',
];
```

## Making a Mailable previewable

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

## What you'll see

Visit `/postal/preview` (or whatever you set `$previewPath` to). The index lists every previewable Mailable with its subject. Click one to open the detail view, which has three tabs:

- **HTML** — rendered in a sandboxed `<iframe>`, so the email's CSS can't bleed into the preview page (or vice versa)
- **Text** — your `text()` body if you supplied one, otherwise the version Postal auto-generates from the HTML
- **Raw MIME** — the full message exactly as it goes on the wire, headers and all — handy for debugging encoding or attachments

Attachments are listed by name. Inline `cid:` images won't render inside the iframe — that's an inherent limit of the sandbox, not a bug in your email.

If a Mailable throws while building its preview, it still appears in the list with an error badge, and its detail page shows the exception inline. One broken Mailable never takes down the whole preview.

## Building your own preview screen

The preview pages are a thin layer over `Myth\Postal\Preview\MailablePreviewer`. Use it directly to show previews inside your own admin area. Pass it the namespace → directory pairs to scan; it doesn't read `Config\Postal` itself:

```php
<?php

use CodeIgniter\Exceptions\PageNotFoundException;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Preview\MailablePreviewer;

$previewer = new MailablePreviewer([
    'App\Mails'          => APPPATH . 'Mails',
    'Modules\Blog\Mails' => ROOTPATH . 'modules/Blog/Mails',
]);

$classes = $previewer->discover(); // sorted Mailable & Previewable class names

try {
    $preview = $previewer->render($class);
} catch (PostalException $e) {
    throw PageNotFoundException::forPageNotFound($e->getMessage());
}

if ($preview->error !== null) {
    echo $preview->error->getMessage();
} else {
    echo $preview->subject;
    echo $preview->htmlBody;    // null when there's no HTML body
    echo $preview->text;        // derived from the HTML when $preview->textAuto is true
    echo $preview->rawMime;
    print_r($preview->attachments); // file names
}
```

- `discover()` returns every class in those directories that extends `Mailable` and implements `Previewable`.
- `isPreviewable()` is safe to call with untrusted input, such as a URL segment. It checks the class name's shape before the autoloader ever sees it. `render()` and `subject()` run the same check themselves.
- `subject($class)` builds the Mailable and returns only its subject. It skips the text and MIME rendering, so it's the cheap call for a list screen. Unlike `render()`, it lets a build error throw, so wrap it in `try`/`catch`.
- `render()` and `subject()` throw a `PostalException` for a class that isn't previewable. Anything thrown while building or rendering the Mailable comes back in `$preview->error` instead, so one broken Mailable can't take down your page.

## Next steps

- [Mailables](mailables.md) — composing the messages you preview
- [Config\Postal reference](reference/config-postal.md#preview) — every preview setting
