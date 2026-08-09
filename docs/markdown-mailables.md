# Markdown Mailables

Instead of hand-rolling HTML for every email, you can author the body as plain CommonMark markdown. Postal converts it to HTML (and derives a plain-text fallback from the same source), drops in reusable pre-styled components for things like buttons and callout panels, and wraps the result in a shared page layout.

The quickest way in is `Mailable::markdown()`, covered in [Markdown Mailables in `build()`](#markdown-mailables-in-build) below. The rest of this page documents the pieces it's built from — the `markdown()` helper, Mail Components, and `LayoutRenderer` — which also work standalone if you need finer control.

## Converting markdown to HTML

The `markdown()` helper resolves a view file to raw markdown and converts it in one call:

```php
<?php

$rendered = markdown('emails/welcome', ['name' => $user->name]);

echo (string) $rendered;   // the converted HTML
echo $rendered->text();    // a plain-text fallback, derived from the same markdown source
```

It's a global helper, always available — no `helper()` call needed, the same as CodeIgniter's own `view()`.

Under the hood, conversion runs through `service('markdown')`, a `MarkdownRenderer` built from a CommonMark `Environment`. If you already have a markdown string in hand rather than a view file, you can call it directly:

```php
<?php

$html = service('markdown')->toHtml('# Hello **World**');
$text = service('markdown')->toText('# Hello **World**');
```

`Config\Postal::$markdownExtensions` controls which CommonMark extensions load, on top of the core parser. By default that's GitHub-Flavored Markdown (tables, strikethrough, autolinks):

```php
<?php

public array $markdownExtensions = [
    League\CommonMark\Extension\GithubFlavoredMarkdownExtension::class,
];
```

## Mail Components

Mail Components are reusable, pre-styled elements you drop straight into markdown source using `mail-`-prefixed tags. Postal ships two: a button and a panel.

```markdown
<mail-button url="https://example.com/confirm">Confirm Email</mail-button>
```

Tag attributes become the component view's data, and the tag's inner content becomes a `$slot` variable:

```php
view('mail/components/button', ['url' => 'https://example.com/confirm', 'slot' => 'Confirm Email'])
```

This isn't a regex trick — Mail Component tags are parsed as real nodes in the markdown document, alongside headings, lists, and everything else. They're always available; there's nothing to enable.

### Single-line vs. multi-line

Write a component two ways, depending on how much content it wraps:

=== "Single-line"
    ```markdown
    <mail-button url="https://example.com/confirm">Confirm Email</mail-button>
    ```

    Everything between the tags is inline content — `**bold**`, links, and code spans work, but nested Mail Components don't.

=== "Multi-line"
    ```markdown
    <mail-panel color="#fef3c7">
    Heads up — your trial ends **Friday**.

    [Upgrade now](https://example.com/upgrade) to keep your data.
    </mail-panel>
    ```

    The closing tag lives on its own line. Everything in between is parsed as full markdown, including multiple paragraphs and nested Mail Components.

!!! warning "Keep the closing tag clean"
    A single-line tag's closing `</mail-...>` must be the last thing on the line. If you write something after it (`<mail-button url="...">Confirm</mail-button> and click here`), it won't be recognized as a Mail Component — it falls back to being treated as plain HTML in the markdown, tag and all.

### The shipped components

| Component | Attributes | Notes |
|-----------|------------|-------|
| `<mail-button>` | `url` (**required**) | Renders a styled link. Omitting `url` throws a `Myth\Postal\Exceptions\PostalException` with a clear message, rather than rendering a broken link. |
| `<mail-panel>` | `color` (optional) | A callout box. Defaults to a light blue background when `color` isn't set. |

Both are styled with inline `style=""` attributes directly on their markup — not relying solely on Layout CSS inlining — so they still look right even in email clients that strip `<style>` blocks entirely.

`Config\Postal::$componentViewPath` (default `'mail/components'`) controls where `<mail-{tag}>` looks for its view: `<mail-button>` resolves to `{componentViewPath}/button`.

## Layouts

!!! tip "Customizing the default Layout and Mail Components"
    Run `php spark publish` to copy the default Layout and Mail Components into your app —
    see [Installation](installation.md#configuration) — then edit the copies in
    `app/Views/mail/` freely; Postal always prefers your app's version over its own.

A Layout is a single-slot wrapper template applied *after* your markdown has already been converted to HTML — not CodeIgniter's `extend()`/`section()`, which composes at the raw view level before conversion. The Layout view receives the converted content as `$content`:

```php
<!-- app/Views/mail/layouts/default.php -->
<!doctype html>
<html>
<head>
<style>
  body { font-family: sans-serif; background: #f4f4f5; }
  .container { max-width: 600px; margin: 0 auto; padding: 24px; }
</style>
</head>
<body>
  <div class="container">
    <?= $content ?>
  </div>
</body>
</html>
```

`LayoutRenderer` wraps content in a Layout and then inlines the Layout's CSS, so styles still apply in email clients (like Gmail) that strip `<head>`/`<style>` on delivery:

```php
<?php

use Myth\Postal\Markdown\LayoutRenderer;

$html = (new LayoutRenderer())->render((string) $rendered);
```

That uses `Config\Postal::$defaultLayout` (default `'mail/layouts/default'`). Pass a second argument to use a different layout for just this call:

```php
<?php

$html = (new LayoutRenderer())->render((string) $rendered, 'mail/layouts/marketing');
```

!!! tip "The `<style>` block stays"
    CSS inlining adds `style=""` attributes for clients that need them, but it doesn't strip the original `<style>` block — clients that *do* render `<head>` styles get the benefit of both.

## Markdown Mailables in `build()`

`Mailable::markdown()` wires everything above into one call: it resolves and converts the view via the `markdown()` helper, wraps the HTML in the resolved Layout, runs CSS inlining, and sets both outputs on the Mailable — `html()` with the rendered HTML, `text()` with the plain-text fallback derived from the same markdown source.

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
            ->markdown('emails/welcome', ['user' => $this->user]);
    }
}
```

From the outside, a Mailable calling `->markdown(...)` behaves exactly like one calling `->html()->text()` directly — same `Email::$htmlBody`/`$textBody`, same `Mailable::fake()`/`assertSent()` surface.

Call `layout()` *before* `markdown()` to use a different Layout view for just that Mailable, instead of `Config\Postal::$defaultLayout` — `markdown()` resolves the layout as soon as it runs, so calling `layout()` afterward throws a `Myth\Postal\Exceptions\PostalException` rather than silently having no effect:

```php
<?php

protected function build(): void
{
    $this->from('news@example.com')
        ->to($this->user->email)
        ->subject('This month at Acme')
        ->layout('mail/layouts/marketing')
        ->markdown('emails/newsletter', ['user' => $this->user]);
}
```

## Security: raw HTML passthrough

CommonMark's raw-HTML passthrough is left at its default (`allow`): any HTML you write directly in a markdown view — including a value interpolated without `esc()` — reaches the rendered email unescaped, exactly as it would in a regular CI4 view.

!!! warning "Escape untrusted data yourself"
    Markdown mailables carry the same trust model as any other CodeIgniter view: nothing here sanitizes interpolated values automatically. If a markdown view interpolates data a user controls — a name, a comment, a URL — wrap it in `esc()` yourself, exactly as you would in a regular `.php` view:

    ```php
    <?php
    // app/Views/emails/comment-notification.php
    ?>
    New comment from **<?= esc($comment->authorName) ?>**:

    > <?= esc($comment->body) ?>
    ```

    Skipping this is no more or less risky than skipping `esc()` in an HTML view — but it's easy to assume markdown is "just text" and let your guard down. It isn't: it compiles to HTML, and unescaped interpolation compiles right along with it.

## Config reference

| Property | Default | What it does |
|----------|---------|---------------|
| `$markdownExtensions` | `[GithubFlavoredMarkdownExtension::class]` | CommonMark extensions loaded by `service('markdown')` |
| `$componentViewPath` | `'mail/components'` | Base path Mail Component tags resolve views against |
| `$defaultLayout` | `'mail/layouts/default'` | The Layout view used when no per-call override is given |

## Next steps

- [Mailables](mailables.md) — the class-based email `Mailable::markdown()` plugs into
- [MIME Rendering](mime-rendering.md) — how the HTML and text bodies a Mailable produces become a wire-ready message
