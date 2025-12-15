<?php

namespace ThreeRN\HttpService\Console\Commands;

use Illuminate\Console\Command;
use ThreeRN\HttpService\Models\RateLimitControl;

class CleanExpiredBlocksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'http-service:clean-blocks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired rate limit blocks from database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Cleaning expired rate limit blocks...');

        $count = RateLimitControl::cleanExpiredBlocks();

        if ($count > 0) {
            $this->info("✅ Removed {$count} expired block(s)");
        } else {
            $this->info('ℹ️  No expired blocks found');
        }

        return self::SUCCESS;
    }
}
