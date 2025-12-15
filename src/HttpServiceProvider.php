<?php

namespace ThreeRN\HttpService;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
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

        // Carrega migrations automaticamente somente se ainda não houver
        // migrations equivalentes publicadas na aplicação.
        // Isso evita executar duas vezes a mesma criação de tabela
        // quando o pacote publica as migrations em database/migrations.
        $existingHttpLog = File::glob(database_path('migrations') . '/*_create_http_request_logs_table.php');
        $existingRateLimit = File::glob(database_path('migrations') . '/*_create_rate_limit_controls_table.php');

        if (empty($existingHttpLog) && empty($existingRateLimit)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
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
