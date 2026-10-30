# Quick Start

Five minutes from `composer require` to your first email sent and tested.

## 1. Install

```bash
composer require myth/postal
```

CodeIgniter auto-discovers the package — nothing else to register.

## 2. Configure a mailer

Publish the config file into your application:

```bash
php spark publish
```

Then edit `app/Config/Mailer.php`:

```php
<?php

namespace Config;

use Myth\Postal\Config\Mailer as PostalMailer;

class Mailer extends PostalMailer
{
    public string $default = 'log'; // switch to 'smtp' for production

    public array $mailers = [
        'log'  => ['transport' => 'log'],
        'smtp' => [
            'transport'  => 'smtp',
            'host'       => 'smtp.example.com',
            'port'       => 587,
            'authType'   => 'login',
            'encryption' => 'tls',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        // PHP doesn't allow function calls in property defaults, so read secrets here.
        $this->mailers['smtp']['username'] = env('SMTP_USER');
        $this->mailers['smtp']['password'] = env('SMTP_PASS');
    }
}
```

With `default = 'log'`, every send in development writes the full rendered message to your app log — nothing leaves your machine.

Redeclaring `$mailers` replaces the package's list rather than adding to it, so list every mailer you want to use.

## 3. Create a Mailable

Generate a class with Spark:

```bash
php spark make:mailable Welcome
```

That writes `app/Mails/Welcome.php`. Open it and fill in `build()`:

```php
<?php

namespace App\Mails;

use Myth\Postal\Mailable;

class Welcome extends Mailable
{
    public function __construct(
        private readonly string $name,
        private readonly string $address,
    ) {
        parent::__construct();
    }

    protected function build(): void
    {
        $this->from('hello@example.com', 'Acme')
            ->to($this->address, $this->name)
            ->subject('Welcome aboard')
            ->html("<p>Hi {$this->name}, glad you're here.</p>");
    }
}
```

## 4. Send it

```php
<?php

use App\Mails\Welcome;

(new Welcome('Alice', 'alice@example.com'))->send();
```

Open `writable/logs/` and you'll see the subject, headers, and body exactly as they'd go on the wire. When you're ready to deliver for real, set `$default = 'smtp'` in your config.

## 5. Test it

```php
<?php

namespace Tests;

use App\Mails\Welcome;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Mailer;

final class WelcomeEmailTest extends CIUnitTestCase
{
    public function testWelcomeEmailIsSent(): void
    {
        $fake = Mailer::fake();

        (new Welcome('Alice', 'alice@example.com'))->send();

        $fake->assertSent(Welcome::class);
        $fake->assertSentTo('alice@example.com');
    }
}
```

`Mailer::fake()` swaps the transport for an in-memory recorder — nothing leaves your app, and `assertSent()` verifies the right message went out.

## Next steps

- [Mailables](mailables.md) — previewing in the browser, choosing a mailer per message
- [Testing](testing.md) — the full assertion API
- [SMTP Mailer](smtp-mailer.md) — configuring your production transport
- [Installation](installation.md) — full requirements and configuration reference
