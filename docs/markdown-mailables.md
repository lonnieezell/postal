# Markdown Mailables

Instead of hand-rolling HTML for every email, write the body as plain CommonMark markdown. Postal converts it to HTML, derives a plain-text fallback from that same source, wraps the result in a shared page layout, and gives you pre-styled components for things like buttons and callout panels — so your emails stay visually consistent without every mailable reinventing its own markup.

## Quick start: `Mailable::markdown()`

The fast path is a single call in your Mailable's `build()`: point `markdown()` at a view file, and it handles conversion, layout, and the plain-text fallback for you.

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

`emails/welcome` is a plain CI4 view file (`app/Views/emails/welcome.php`) written in markdown, not HTML — headings, bold text, links, and the [Mail Components](#mail-components) below all just work. Behind the scenes, `->markdown()`:

1. Resolves the view and converts it from markdown to HTML.
2. Wraps that HTML in a [Layout](#customizing-the-layout) — a shared page template.
3. Inlines the layout's CSS, so styling survives in email clients that strip `<style>` blocks.
4. Sets both outputs on the Mailable: `html()` with the rendered HTML, `text()` with a plain-text fallback generated from the markdown source.

From the outside, a Mailable calling `->markdown(...)` behaves exactly like one calling `->html()->text()` directly — same `Email::$htmlBody`/`$textBody`, same `Mailable::fake()`/`assertSent()` surface.

Want a different layout for one Mailable? Call `layout()` *before* `markdown()`:

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

`markdown()` resolves the layout the moment it runs, so `layout()` has to come first — call it afterward and Postal throws a `Myth\Postal\Exceptions\PostalException` instead of silently ignoring it.

!!! tip "Generate the scaffolding for you"
    `php spark make:mailable Welcome --markdown` scaffolds both the Mailable class and a starter markdown view, with `build()` already wired up to call `->markdown()`. See [Mailables](mailables.md#generating-a-mailable).

## Mail Components

Mail Components are reusable, pre-styled elements you drop straight into your markdown using `mail-`-prefixed tags. Postal ships two: a button and a panel.

```markdown
<mail-button url="https://example.com/confirm">Confirm Email</mail-button>
```

They aren't a regex trick — Mail Component tags are parsed as real nodes in the markdown document, right alongside headings and lists. There's nothing to enable; they're always available inside any markdown view.

A tag's attributes become the component view's data, and its inner content becomes a `$slot` variable, so the example above resolves to:

```php
view('mail/components/button', ['url' => 'https://example.com/confirm', 'slot' => 'Confirm Email'])
```

### Single-line vs. multi-line

Write a component two ways, depending on how much content it needs to wrap:

=== "Single-line"
    ```markdown
    <mail-button url="https://example.com/confirm">Confirm Email</mail-button>
    ```

    Everything between the tags is inline content — `**bold**`, links, and code spans all work, but nested Mail Components don't.

=== "Multi-line"
    ```markdown
    <mail-panel color="#fef3c7">
    Heads up — your trial ends **Friday**.

    [Upgrade now](https://example.com/upgrade) to keep your data.
    </mail-panel>
    ```

    The closing tag lives on its own line. Everything in between is parsed as full markdown, including multiple paragraphs and nested Mail Components.

!!! warning "Keep the closing tag clean"
    On a single-line tag, the closing `</mail-...>` has to be the last thing on the line. Write something after it — `<mail-button url="...">Confirm</mail-button> and click here` — and Postal won't recognize it as a Mail Component at all. It falls back to plain HTML in the markdown, tag and all.

### The shipped components

| Component | Attributes | Notes |
|-----------|------------|-------|
| `<mail-button>` | `url` (**required**) | Renders a styled link. Omitting `url` throws a `Myth\Postal\Exceptions\PostalException` with a clear message, rather than rendering a broken link. |
| `<mail-panel>` | `color` (optional) | A callout box. Takes a hex value (`#fef3c7`) or a CSS colour keyword (`linen`); anything else falls back to the default light blue, since the value lands directly in a `style` attribute. |

Both are styled with inline `style=""` attributes directly on their own markup, rather than relying solely on the layout's CSS inlining — so they still look right even in clients that strip `<style>` blocks entirely.

Want to restyle them, or add your own? `Config\Postal::$componentViewPath` (default `'mail/components'`) controls where `<mail-{tag}>` looks for its view — `<mail-button>` resolves to `{componentViewPath}/button`. See the tip below for copying the shipped views into your own app.

## Customizing the Layout

A Layout is the shared page template your markdown gets wrapped in — a single slot, filled in with the HTML your markdown converted to. It's applied *after* conversion, which is a deliberate difference from CodeIgniter's own `extend()`/`section()`: those compose at the raw view level, before anything's been converted, and don't give Postal a clean point to run CSS inlining or resolve Mail Components against the final HTML.

The default layout ships at `mail/layouts/default`, and receives the converted content as `$content`:

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

!!! tip "Make it yours"
    Run `php spark publish` to copy the default Layout and Mail Components into your app — see [Installation](installation.md#configuration) — then edit the copies in `app/Views/mail/` freely. Postal always prefers your app's version over its own, and publishing never overwrites a file you've already customized.

`Mailable::markdown()` uses `Config\Postal::$defaultLayout` (default `'mail/layouts/default'`) unless you override it per-Mailable with `layout()`, as shown in the [quick start](#quick-start-mailablemarkdown) above.

!!! tip "The `<style>` block stays"
    CSS inlining adds `style=""` attributes for clients that need them, but it doesn't strip the original `<style>` block — clients that *do* render `<head>` styles get the benefit of both.

## Advanced: converting markdown directly

`Mailable::markdown()` covers the common case, but its two building blocks — the `markdown()` helper and `LayoutRenderer` — are useful on their own too, if you want converted HTML without the full pipeline.

The `markdown()` helper resolves a view file to raw markdown and converts it in one call. It's a global helper, always available — no `helper()` call needed, same as CodeIgniter's own `view()`:

```php
<?php

$rendered = markdown('emails/welcome', ['name' => $user->name]);

echo (string) $rendered;   // the converted HTML
echo $rendered->text();    // a plain-text fallback, derived from the same markdown source
```

If you already have a markdown string rather than a view file, call the conversion service directly:

```php
<?php

$html = service('markdown')->toHtml('# Hello **World**');
$text = service('markdown')->toText('# Hello **World**');
```

`Config\Postal::$markdownExtensions` controls which CommonMark extensions load on top of the core parser. By default that's GitHub-Flavored Markdown (tables, strikethrough, autolinks):

```php
<?php

public array $markdownExtensions = [
    League\CommonMark\Extension\GithubFlavoredMarkdownExtension::class,
];
```

To apply a Layout yourself — without going through a Mailable — use `LayoutRenderer`, which wraps content in a Layout and inlines its CSS:

```php
<?php

use Myth\Postal\Markdown\LayoutRenderer;

$html = (new LayoutRenderer())->render((string) $rendered);
```

That uses `Config\Postal::$defaultLayout` too, unless you pass a second argument to use a different layout for just this call:

```php
<?php

$html = (new LayoutRenderer())->render((string) $rendered, 'mail/layouts/marketing');
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
