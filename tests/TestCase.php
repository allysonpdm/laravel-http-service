<?php

namespace ThreeRN\HttpService\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ThreeRN\HttpService\HttpServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            HttpServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Configura banco de dados em memória para testes
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Configurações do pacote
        $app['config']->set('http-service.logging_enabled', true);
        $app['config']->set('http-service.rate_limit_enabled', true);
        $app['config']->set('http-service.default_block_time', 15);
        $app['config']->set('http-service.timeout', 30);
    }
}
