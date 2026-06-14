<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Mailer\Sdk\Laravel\Mail\MailerTransport;
use Mailer\Sdk\MailerClient;

/**
 * Laravel package service provider. Auto-discovered via composer's
 * extra.laravel.providers; this class is only ever loaded inside a Laravel
 * application, so the SDK itself stays usable in plain PHP without illuminate.
 */
final class MailerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/mailer-sdk.php', 'mailer-sdk');

        $this->app->singleton(MailerClient::class, function ($app): MailerClient {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('mailer-sdk', []);

            return new MailerClient(
                (string) ($config['base_url'] ?? ''),
                (string) ($config['token'] ?? ''),
                null,
                [
                    'timeout' => (int) ($config['timeout'] ?? 10),
                    'retries' => (int) ($config['retries'] ?? 2),
                    'retry_base_delay' => (int) ($config['retry_base_delay'] ?? 200),
                    'retry_max_delay' => (int) ($config['retry_max_delay'] ?? 5000),
                ],
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/mailer-sdk.php' => $this->app->configPath('mailer-sdk.php'),
            ], 'mailer-sdk-config');
        }

        $this->registerMailTransport();
    }

    /**
     * Register the "mailer" mail driver so an app can route Laravel's Mail
     * facade through the platform /send API with MAIL_MAILER=mailer plus a
     * config/mail.php entry: 'mailer' => ['transport' => 'mailer'].
     */
    private function registerMailTransport(): void
    {
        if (! class_exists(Mail::class)) {
            return;
        }

        Mail::extend('mailer', function (array $config): MailerTransport {
            return new MailerTransport(
                $this->app->make(MailerClient::class),
                (array) $this->app['config']->get('mailer-sdk.mail', []),
                $this->app->make(Dispatcher::class),
                $this->app->make('log'),
            );
        });
    }
}
