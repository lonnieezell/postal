# Quick Start

Five minutes from `composer require` to your first email sent and tested.

## 1. Install

```bash
composer require myth/postal
```

CodeIgniter auto-discovers the package — nothing else to register.

## 2. Configure a mailer

Create `app/Config/Email.php`:

```php
namespace Config;

use Myth\Postal\Config\Email as BaseEmail;

class Email extends BaseEmail
{
    public string $default = 'log'; // switch to 'smtp' for production

    public array $mailers = [
        'log'  => ['transport' => 'log'],
        'smtp' => [
            'transport'  => 'smtp',
            'host'       => 'smtp.example.com',
            'port'       => 587,
            'username'   => env('SMTP_USER'),
            'password'   => env('SMTP_PASS'),
            'encryption' => 'tls',
        ],
    ];
}
```

With `default = 'log'`, every send in development writes the full rendered message to your app log — nothing leaves your machine.

## 3. Create a Mailable

Generate a class with Spark:

```bash
php spark make:mailable Welcome
```

That writes `app/Mails/Welcome.php`. Open it and fill in `build()`:

```php
namespace App\Mails;

use Myth\Postal\Mailable;

class Welcome extends Mailable
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
    ) {
        parent::__construct();
    }

    protected function build(): void
    {
        $this->from('hello@example.com', 'Acme')
            ->to($this->email, $this->name)
            ->subject('Welcome aboard')
            ->html("<p>Hi {$this->name}, glad you're here.</p>");
    }
}
```

## 4. Send it

```php
use App\Mails\Welcome;

(new Welcome('Alice', 'alice@example.com'))->send();
```

Open `writable/logs/` and you'll see the subject, headers, and body exactly as they'd go on the wire. When you're ready to deliver for real, set `$default = 'smtp'` in your config.

## 5. Test it

```php
use Myth\Postal\Mailer;
use App\Mails\Welcome;

public function testWelcomeEmailIsSent(): void
{
    $fake = Mailer::fake();

    (new Welcome('Alice', 'alice@example.com'))->send();

    $fake->assertSent(Welcome::class);
    $fake->assertSentTo('alice@example.com');
}
```

`Mailer::fake()` swaps the transport for an in-memory recorder — nothing leaves your app, and `assertSent()` verifies the right message went out.

## Next steps

- [Mailables](mailables.md) — previewing in the browser, choosing a mailer per message
- [Testing](testing.md) — the full assertion API
- [SMTP Mailer](smtp-mailer.md) — configuring your production transport
- [Installation](installation.md) — full requirements and configuration reference
