<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Postal.
 *
 * (c) Lonnie Ezell <lonnieje@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Postal;

use Myth\Postal\Config\Email as EmailConfig;
use Myth\Postal\Exceptions\PackageException;
use Myth\Postal\Transport\TransportInterface;

/**
 * Resolves named mailers from Config\Email into Mailer instances backed by
 * their configured transport. Resolution is lazy and cached per mailer name.
 */
class MailerManager
{
    private readonly EmailConfig $config;

    /**
     * @var array<string, Mailer>
     */
    private array $mailers = [];

    public function __construct(?EmailConfig $config = null)
    {
        // Resolve by FQCN, not the short name: config('Email') would resolve to
        // the framework's Config\Email, not this package's config class. The
        // FQCN form still honours app-level overrides via Factories.
        // @phpstan-ignore codeigniter.factoriesClassConstFetch
        $this->config = $config ?? config(EmailConfig::class);
    }

    /**
     * Returns the Mailer for the given mailer name, or the default mailer when
     * none is given. Instances are cached.
     */
    public function mailer(?string $name = null): Mailer
    {
        $name ??= $this->config->default;

        return $this->mailers[$name] ??= new Mailer(
            $this->resolveTransport($name),
            $this->config->fireEvents,
        );
    }

    /**
     * Sends the message through the default mailer.
     */
    public function send(Email $email): SendResult
    {
        return $this->mailer()->send($email);
    }

    /**
     * Builds the transport instance for a named mailer from the transport map.
     */
    private function resolveTransport(string $name): TransportInterface
    {
        if (! isset($this->config->mailers[$name])) {
            throw PackageException::forUnknownMailer($name);
        }

        $transportName = $this->config->mailers[$name]['transport'] ?? '';

        if (! isset($this->config->transports[$transportName])) {
            throw PackageException::forUnknownTransport((string) $transportName);
        }

        $class = $this->config->transports[$transportName];

        // The whole mailer entry is the transport's settings; each transport
        // reads the keys it understands (host, level, …) and ignores the rest.
        return new $class($this->config->mailers[$name]);
    }
}
