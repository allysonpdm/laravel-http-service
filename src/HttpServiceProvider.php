<?php

namespace ThreeRN\HttpService;

use Illuminate\Support\ServiceProvider;
use ThreeRN\HttpService\Services\HttpService as HttpServiceClass;
use ThreeRN\HttpService\Console\Commands\InstallCommand;
use ThreeRN\HttpService\Console\Commands\CleanExpiredBlocksCommand;
use ThreeRN\HttpService\Console\Commands\UnblockDomainCommand;
use ThreeRN\HttpService\Console\Commands\ListBlockedDomainsCommand;
use ThreeRN\HttpService\Console\Commands\CleanOldLogsCommand;

class HttpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configurações
        $this->mergeConfigFrom(
            __DIR__ . '/../config/http-service.php',
            'http-service'
        );

        // Registra o serviço no container
        $this->app->singleton('http-service', function ($app) {
            return new HttpServiceClass();
        });

        // Alias para facilitar injeção de dependência
        $this->app->alias('http-service', HttpServiceClass::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publica configuração
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/http-service.php' => config_path('http-service.php'),
            ], 'http-service-config');

            // Publica migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'http-service-migrations');

            // Registra comandos
            $this->commands([
                InstallCommand::class,
                CleanExpiredBlocksCommand::class,
                UnblockDomainCommand::class,
                ListBlockedDomainsCommand::class,
                CleanOldLogsCommand::class,
            ]);
        }

        // Carrega migrations automaticamente (útil para testes)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['http-service', HttpServiceClass::class];
    }
}
