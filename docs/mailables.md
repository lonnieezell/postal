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

`send()` returns the same [`SendResult`](reference/send-result.md) you get from the mailer directly, so you can check `$result->success` if you need to.

!!! warning "Call the parent constructor"
    If your Mailable defines its own `__construct()`, call `parent::__construct()`. That's what sets up the underlying `Email` the helpers compose into.

## Composing in `build()`

`build()` is where you describe the message. It runs **lazily**, when you call `send()` or `render()` — never in the constructor. So passing data into the constructor and reading it in `build()` (like `$this->user` above) always works.

Inside `build()` you have protected helpers that mirror part of the [`Email`](reference/email.md) builder:

| Helper | What it sets |
|--------|--------------|
| `from(string $address, string $name = '')` | The sender |
| `to(array\|string $address, string $name = '')` | A recipient (call more than once to add several) |
| `subject(string $subject)` | The subject line |
| `html(string $html)` | The HTML body |
| `text(string $text)` | The plain-text body |
| `markdown(string $view, array $data = [])` | Both bodies at once, from a markdown view — see [Markdown Mailables](markdown-mailables.md) |
| `layout(string $view)` | Overrides the Layout `markdown()` wraps its HTML in, for this Mailable only — call it *before* `markdown()` |
| `transport(string $name)` | The named mailer to send through — see [Choosing a mailer](#choosing-a-mailer) |

Each returns `$this`, so they chain.

For anything else, such as `cc()`, `bcc()`, `replyTo()`, attachments or `metadata()`, call the `Email` builder directly through the protected `$this->email` property:

```php
<?php

$this->email->cc('team@example.com');
```

!!! tip "Prefer `markdown()` over hand-rolled `html()`/`text()`"
    If your body is prose rather than bespoke markup, `->markdown('emails/welcome', [...])` gets you converted HTML, a matching plain-text fallback, and a shared page layout in one call. See [Markdown Mailables](markdown-mailables.md).

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
        ->html(view('emails/newsletter', ['user' => $this->user]));
}
```

`transport('marketing')` selects the mailer named `marketing` — the same name you'd pass to `service('mailer')->mailer('marketing')`.

## Generating a Mailable

Spark scaffolds one for you:

```bash
php spark make:mailable Welcome
```

That writes `app/Mails/Welcome.php` in the `App\Mails` namespace, with an empty `build()` ready to fill in. The generated class also implements [`Previewable`](previewing.md#making-a-mailable-previewable) with a `previewInstance()` stub, so it shows up in the browser preview out of the box. Pass `--force` to overwrite an existing file:

```bash
php spark make:mailable Welcome --force
```

Pass `--markdown` to scaffold a [Markdown Mailable](markdown-mailables.md) instead: alongside the class, it writes a starter markdown view at `app/Views/emails/welcome.php`, and generates a `build()` that calls `->markdown('emails/welcome')` rather than `->html('')`:

```bash
php spark make:mailable Welcome --markdown
```

`--force` applies to both files, independently — each is only overwritten if it already exists and `--force` was passed.

Pass `--namespace` to generate into another registered namespace, such as a module. The class lands in that namespace's `Mails` directory:

```bash
php spark make:mailable Welcome --namespace Acme\\Blog
```

Remember to add the namespace to `$mailableNamespaces` if you want the Mailable in the [preview](previewing.md).

## Previewing Mailables

Postal can render any Mailable to your browser — HTML, plain text and raw MIME — without sending it. See [Previewing Mailables](previewing.md).

## Rendering without sending

The [browser preview](previewing.md) is built on `Mailable::render()`, a public seam you can use anywhere:

```php
<?php

$email = (new WelcomeEmail($user))->render();

echo $email->subject;
echo $email->htmlBody;
```

`render()` runs `build()` and hands back the composed [`Email`](reference/email.md) — it never sends. It's the build-without-send counterpart to `send()`.

## Next steps

- [Testing](testing.md) — assert that a Mailable was sent, by class, without touching a real transport
- [MIME Rendering](mime-rendering.md) — how your HTML and text bodies become a wire-ready message
