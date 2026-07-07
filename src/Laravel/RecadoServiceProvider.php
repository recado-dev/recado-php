<?php

declare(strict_types=1);

namespace Recado\Sdk\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Recado\Sdk\Laravel\Mail\RecadoTransport;
use Recado\Sdk\Laravel\Notifications\RecadoChannel;
use Recado\Sdk\RecadoClient;

/**
 * Laravel package service provider. Auto-discovered via composer's
 * extra.laravel.providers; this class is only ever loaded inside a Laravel
 * application, so the SDK itself stays usable in plain PHP without illuminate.
 */
final class RecadoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/recado-sdk.php', 'recado-sdk');

        $this->app->singleton(RecadoClient::class, function ($app): RecadoClient {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('recado-sdk', []);

            return new RecadoClient(
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
                __DIR__.'/../../config/recado-sdk.php' => $this->app->configPath('recado-sdk.php'),
            ], 'recado-sdk-config');
        }

        $this->registerMailTransport();
        $this->registerNotificationChannel();
    }

    /**
     * Register the "recado" mail driver so an app can route Laravel's Mail
     * facade through the platform /send API with MAIL_MAILER=recado plus a
     * config/mail.php entry: 'recado' => ['transport' => 'recado'].
     */
    private function registerMailTransport(): void
    {
        if (! class_exists(Mail::class)) {
            return;
        }

        Mail::extend('recado', function (array $config): RecadoTransport {
            return new RecadoTransport(
                $this->app->make(RecadoClient::class),
                (array) $this->app['config']->get('recado-sdk.mail', []),
                $this->app->make(Dispatcher::class),
                $this->app->make('log'),
            );
        });
    }

    /**
     * Register the "recado" notification channel so a Notification's via() can
     * return ['recado'] and deliver through the platform /send API.
     */
    private function registerNotificationChannel(): void
    {
        if (! class_exists(ChannelManager::class)) {
            return;
        }

        $this->app->resolving(ChannelManager::class, function (ChannelManager $manager): void {
            $manager->extend('recado', function ($app): RecadoChannel {
                return new RecadoChannel(
                    $app->make(RecadoClient::class),
                    (array) $app['config']->get('recado-sdk.mail', []),
                    $app->make(Dispatcher::class),
                    $app->make('log'),
                );
            });
        });
    }
}
