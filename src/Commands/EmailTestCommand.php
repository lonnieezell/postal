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

namespace Myth\Postal\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Email as LegacyEmailConfig;
use Myth\Postal\Email;

/**
 * Sends a test message through the full mailer pipeline and reports the result.
 */
class EmailTestCommand extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Postal';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'email:test';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Sends a test message through the full mailer pipeline.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'email:test [address] [options]';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'address' => 'The recipient address. Prompted for when omitted.',
    ];

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '--mailer'    => 'The named mailer to send through. Defaults to the configured default.',
        '--transport' => 'Alias for --mailer.',
    ];

    /**
     * Actually execute the command.
     *
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $address = trim((string) ($params[0] ?? CLI::prompt('Recipient address')));

        if ($address === '') {
            CLI::error('A recipient address is required.');

            return EXIT_ERROR;
        }

        $name   = $params['mailer'] ?? $params['transport'] ?? null;
        $result = service('mailer')->mailer($name)->send($this->buildMessage($address));

        if ($result->success) {
            CLI::write('Sent. Message ID: ' . ($result->messageId ?? '(none)'), 'green');

            return EXIT_SUCCESS;
        }

        CLI::error('Send failed: ' . ($result->error ?? 'unknown error'));

        return EXIT_ERROR;
    }

    /**
     * Builds the test message, taking the From address from the framework's
     * Config\Email and falling back to the recipient when none is configured.
     */
    private function buildMessage(string $address): Email
    {
        // Resolve the framework's Config\Email by FQCN for a typed return; an
        // app-level override is honoured through Factories.
        $config = config(LegacyEmailConfig::class);

        $fromEmail = $config->fromEmail !== '' ? $config->fromEmail : $address;
        $fromName  = $config->fromName;

        return (new Email())
            ->from($fromEmail, $fromName)
            ->to($address)
            ->subject('Postal test message')
            ->text('This is a test message sent by the Postal email:test command.');
    }
}
