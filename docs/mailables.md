# Mailables

A Mailable is a reusable, class-based email. Instead of building an `Email` inline every time you need to send a welcome message, you describe it once in a class and send it wherever you need it.

```php
(new WelcomeEmail($user))->send();
```

That's the whole idea: the message lives in one place, the call site stays a single line, and tests can assert on it by type.

## A quick example

A Mailable extends `Myth\Postal\Mailable` and composes the message in `build()`:

```php
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

By default a Mailable sends through your default mailer. To route through a named mailer from `Config\Email`, call `transport()` in `build()`:

```php
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

That writes `app/Mails/Welcome.php` in the `App\Mails` namespace, with an empty `build()` ready to fill in. Pass `--force` to overwrite an existing file:

```bash
php spark make:mailable Welcome --force
```

## Next steps

- [Testing Mail](testing.md) — assert that a Mailable was sent, by class, without touching a real transport
- [MIME Rendering](mime-rendering.md) — how your HTML and text bodies become a wire-ready message
