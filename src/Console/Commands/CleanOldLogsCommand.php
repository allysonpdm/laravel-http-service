<?php

namespace ThreeRN\HttpService\Console\Commands;

use Illuminate\Console\Command;
use ThreeRN\HttpService\Models\HttpRequestLog;
use Carbon\Carbon;

class CleanOldLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'http-service:clean-logs {--days= : Number of days to retain logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old HTTP request logs from database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = $this->option('days') 
            ?? config('http-service.log_retention_days', 30);

        if ($days === null) {
            $this->warn('Log retention is disabled (log_retention_days is null)');
            return self::SUCCESS;
        }

        $this->info("Cleaning logs older than {$days} days...");

        $date = Carbon::now()->subDays($days);
        $count = HttpRequestLog::where('created_at', '<', $date)->delete();

        if ($count > 0) {
            $this->info("Removed {$count} old log(s)");
        } else {
            $this->info('No old logs found');
        }

        return self::SUCCESS;
    }
}
