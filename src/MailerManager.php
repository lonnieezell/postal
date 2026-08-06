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

use Myth\Postal\Config\Mailer as MailerConfig;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Transport\DkimSigningTransport;
use Myth\Postal\Transport\FailoverTransport;
use Myth\Postal\Transport\RawMimeTransport;
use Myth\Postal\Transport\TransportInterface;

/**
 * Resolves named mailers from Config\Mailer into Mailer instances backed by
 * their configured transport. Resolution is lazy and cached per mailer name.
 */
class MailerManager
{
    private readonly MailerConfig $config;

    /**
     * @var array<string, Mailer>
     */
    private array $mailers = [];

    public function __construct(?MailerConfig $config = null)
    {
        // Resolved by short name so that an application's Config\Mailer, if one
        // has been published, is preferred over the package's default.
        $this->config = $config ?? config('Mailer');
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
            $this->config->suppressionList !== null ? new ($this->config->suppressionList)() : null,
            $this->config->unsubscribeUrl !== null ? new ($this->config->unsubscribeUrl)() : null,
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
            throw PostalException::forUnknownMailer($name);
        }

        $transportName = $this->config->mailers[$name]['transport'] ?? '';

        if (! isset($this->config->transports[$transportName])) {
            throw PostalException::forUnknownTransport((string) $transportName);
        }

        $class = $this->config->transports[$transportName];

        // A failover mailer is a composite: build its child transports by name
        // and hand them to the FailoverTransport. Children are resolved through
        // the same path, so each carries its own transport's settings — and its
        // own DKIM signing — while the composite itself stays DKIM-agnostic.
        if (is_a($class, FailoverTransport::class, true)) {
            return new $class($this->resolveFailoverChildren($name));
        }

        $settings = $this->config->mailers[$name];

        // A leaf with "dkim" config is wrapped in a DKIM signer, but only when
        // it delivers raw MIME. The check runs against the class (before the
        // transport is built) so a structured-API leaf fails fast with a clear
        // error rather than constructing a transport it can never sign through.
        $dkim = $settings['dkim'] ?? null;

        if (is_array($dkim) && $dkim !== []) {
            if (! is_a($class, RawMimeTransport::class, true)) {
                throw PostalException::forDkimUnsupported((string) $transportName);
            }

            return new DkimSigningTransport(new $class($settings), $dkim);
        }

        // The whole mailer entry is the transport's settings; each transport
        // reads the keys it understands (host, level, …) and ignores the rest.
        return new $class($settings);
    }

    /**
     * Builds the ordered child transports for a failover mailer from the mailer
     * names listed under its "chain" key.
     *
     * @return list<TransportInterface>
     */
    private function resolveFailoverChildren(string $name): array
    {
        $childNames = $this->config->mailers[$name]['chain'] ?? [];

        if (! is_array($childNames)) {
            $childNames = [];
        }

        $children = [];

        foreach ($childNames as $childName) {
            $children[] = $this->resolveTransport((string) $childName);
        }

        return $children;
    }
}
