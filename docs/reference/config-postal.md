# Config\Postal

`Config\Postal` holds the settings for features outside the mailer: the browser preview and markdown emails. To change them, create `app/Config/Postal.php` with a `Config\Postal` class that extends `Myth\Postal\Config\Postal`, and redeclare the properties you want to change.

```php
<?php

namespace Config;

use Myth\Postal\Config\Postal as BasePostal;

class Postal extends BasePostal
{
    public bool $enablePreview = true;
}
```

## Properties

### Preview

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$enablePreview` | `bool` | `false` | Turns on the browser preview. It is never served when `ENVIRONMENT` is `production`, whatever this is set to. |
| `$previewPath` | `string` | `'postal/preview'` | The URI prefix the preview is served under. |
| `$mailableNamespaces` | `array<string, string>` | `['App\Mails' => APPPATH . 'Mails']` | Namespace → directory pairs searched for previewable Mailables. |

See [Previewing Mailables](../previewing.md).

### Markdown

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$markdownExtensions` | `list<class-string>` | `[GithubFlavoredMarkdownExtension::class]` | CommonMark extensions loaded on top of the core parser. |
| `$componentViewPath` | `string` | `'mail/components'` | Where Mail Component views live: `<mail-button>` uses `{componentViewPath}/button`. |
| `$defaultLayout` | `string` | `'mail/layouts/default'` | The Layout markdown emails are wrapped in, unless a Mailable calls `layout()`. |

See [Markdown Mailables](../markdown-mailables.md).

## `.env`

Scalar settings can be overridden from `.env` with the `postal.` prefix:

```ini
postal.enablePreview = true
postal.previewPath = mail/preview
```

## Methods

| Method | Description |
|--------|-------------|
| `previewEnabled(string $environment): bool` | `true` when `$environment` isn't `production` and `$enablePreview` is on. |

## Related

- [Config\Mailer](config-mailer.md) — mailers and transports
