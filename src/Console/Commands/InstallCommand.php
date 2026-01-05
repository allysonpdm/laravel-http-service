<?php

namespace ThreeRN\HttpService\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'http-service:install {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install HTTP Service package: publish config and migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing HTTP Service...');
        $this->newLine();

        // Publica configuração
        $this->publishConfig();

        // Publica migrations
        $this->publishMigrations();

        $this->newLine();
        $this->info('HTTP Service installed successfully!');
        $this->newLine();
        $this->comment('Next steps:');
        $this->comment('1. Review the config file: config/http-service.php');
        $this->comment('2. Run migrations: php artisan migrate');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Publica o arquivo de configuração
     */
    protected function publishConfig(): void
    {
        $configSource = __DIR__ . '/../../../config/http-service.php';
        $configDest = config_path('http-service.php');
        $force = $this->option('force');

        if (File::exists($configDest) && !$force) {
            $this->warn('Skipped config file: config/http-service.php (already exists)');
            return;
        }

        File::copy($configSource, $configDest);
        $this->info('Published config file: config/http-service.php');
    }

    /**
     * Publica as migrations
     */
    protected function publishMigrations(): void
    {
        $migrationsSource = __DIR__ . '/../../../database/migrations';
        $migrationsDest = database_path('migrations');

        if (!File::isDirectory($migrationsSource)) {
            $this->error('Migrations source directory not found');
            return;
        }

        $timestamp = date('Y_m_d_His');
        $migrations = [
            'create_http_request_logs_table.php',
            'create_rate_limit_controls_table.php',
            'add_headers_to_http_request_logs_table.php',
        ];

        foreach ($migrations as $index => $migration) {
            $source = $migrationsSource . '/' . $migration;
            
            // Adiciona timestamp único para cada migration
            $time = date('Y_m_d_His', strtotime("+{$index} seconds"));
            $dest = $migrationsDest . '/' . $time . '_' . $migration;

            if (!File::exists($source)) {
                $this->warn("Migration not found: {$migration}");
                continue;
            }

            // Verifica se já existe uma migration semelhante
            $existingMigrations = File::glob($migrationsDest . '/*_' . $migration);
            if (!empty($existingMigrations)) {
                if ($this->option('force')) {
                    // Remove migrations antigas quando for --force
                    foreach ($existingMigrations as $old) {
                        File::delete($old);
                    }
                } else {
                    $this->warn("Skipped migration: {$migration} (already exists)");
                    continue;
                }
            }

            File::copy($source, $dest);
            $this->info("Published migration: {$migration}");
        }
    }
}
