<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests\Laravel;

use Recado\Sdk\Laravel\RecadoServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case that boots a minimal Laravel application (via Testbench) with
 * the SDK service provider registered, used to prove the Mail::extend('recado')
 * wiring end to end.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RecadoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('recado-sdk.base_url', 'https://recado.example.com/api/v1');
        $app['config']->set('recado-sdk.token', 'test-token');
        $app['config']->set('mail.default', 'recado');
        $app['config']->set('mail.mailers.recado', ['transport' => 'recado']);
    }
}
