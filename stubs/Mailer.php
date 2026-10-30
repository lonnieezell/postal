<?php

declare(strict_types=1);

namespace Config;

use Myth\Postal\Config\Mailer as PostalMailer;

/**
 * Postal mailer configuration for this application.
 *
 * Every setting is inherited from the package's config — redeclare only the
 * properties you want to change, most often $default and $mailers:
 *
 *     public string $default = 'smtp';
 *
 *     public array $mailers = [
 *         'smtp' => [
 *             'transport'  => 'smtp',
 *             'host'       => 'smtp.example.com',
 *             'port'       => 587,
 *             'authType'   => 'login',
 *             'encryption' => 'tls',
 *         ],
 *     ];
 *
 * PHP doesn't allow function calls in property defaults, so read secrets
 * in the constructor:
 *
 *     public function __construct()
 *     {
 *         parent::__construct();
 *
 *         $this->mailers['smtp']['username'] = env('SMTP_USER');
 *         $this->mailers['smtp']['password'] = env('SMTP_PASS');
 *     }
 */
class Mailer extends PostalMailer
{
}
