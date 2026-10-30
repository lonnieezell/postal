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

use CodeIgniter\DataCaster\DataCaster;

/**
 * Maps CodeIgniter's flat Config\Email keys to a Postal mailer definition.
 *
 * This is the mapping LegacyEmailAdapter uses, exposed so other code (such as a
 * settings bridge or a config generator) sends mail exactly as the adapter would.
 */
final class LegacyConfigMapper
{
    /**
     * Config\Email's defaults, used for missing (or null) keys.
     */
    private const DEFAULTS = [
        'protocol'       => 'mail',
        'mailPath'       => '/usr/sbin/sendmail',
        'SMTPHost'       => '',
        'SMTPUser'       => '',
        'SMTPPass'       => '',
        'SMTPPort'       => 25,
        'SMTPTimeout'    => 5,
        'SMTPKeepAlive'  => false,
        'SMTPCrypto'     => 'tls',
        'SMTPAuthMethod' => 'login',
        'DSN'            => false,
    ];

    /**
     * DataCaster types for the non-string keys; every other key is cast to string.
     */
    private const TYPES = [
        'SMTPPort'      => 'int',
        'SMTPTimeout'   => 'int',
        'SMTPKeepAlive' => 'bool',
        'DSN'           => 'bool',
    ];

    /**
     * Builds a mailer definition from flat Config\Email keys. Values may be
     * database-style strings ('587', '0', 'f'); they are cast with DataCaster.
     *
     * @param array<string, mixed> $flat Config\Email keys, e.g. from get_object_vars()
     *
     * @return array<string, mixed>
     */
    public static function toMailer(array $flat): array
    {
        $caster = new DataCaster(types: self::TYPES);
        $config = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value        = $caster->castAs($flat[$key] ?? $default, $key);
            $config[$key] = isset(self::TYPES[$key]) ? $value : (string) $value;
        }

        $protocol = strtolower($config['protocol']);

        if ($protocol === 'sendmail') {
            return ['transport' => 'sendmail', 'path' => $config['mailPath']];
        }

        if ($protocol !== 'smtp') {
            return ['transport' => 'mail'];
        }

        $mailer = [
            'transport'  => 'smtp',
            'host'       => $config['SMTPHost'],
            'port'       => $config['SMTPPort'],
            'timeout'    => $config['SMTPTimeout'],
            'encryption' => $config['SMTPCrypto'],
            'keepAlive'  => $config['SMTPKeepAlive'],
        ];

        if ($config['SMTPUser'] !== '' && $config['SMTPPass'] !== '') {
            $mailer['username'] = $config['SMTPUser'];
            $mailer['password'] = $config['SMTPPass'];
            $mailer['authType'] = $config['SMTPAuthMethod'];
        }

        if ($config['DSN'] === true) {
            $mailer['dsn'] = ['notify' => 'SUCCESS,FAILURE,DELAY', 'ret' => 'FULL'];
        }

        return $mailer;
    }
}
