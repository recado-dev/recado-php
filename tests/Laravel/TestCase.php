<?php

declare(strict_types=1);

namespace Mailer\Sdk\Tests\Laravel;

use Mailer\Sdk\Laravel\MailerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case that boots a minimal Laravel application (via Testbench) with
 * the SDK service provider registered, used to prove the Mail::extend('mailer')
 * wiring end to end.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MailerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mailer-sdk.base_url', 'https://api.mailer.test/api/v1');
        $app['config']->set('mailer-sdk.token', 'test-token');
        $app['config']->set('mail.default', 'mailer');
        $app['config']->set('mail.mailers.mailer', ['transport' => 'mailer']);
    }
}
