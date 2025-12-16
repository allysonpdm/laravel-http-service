<?php

namespace ThreeRN\HttpService\Console\Commands;

use Illuminate\Console\Command;
use ThreeRN\HttpService\Models\RateLimitControl;

class ListBlockedDomainsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'http-service:list-blocks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all currently blocked domains';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $blocks = RateLimitControl::active()->get();

        if ($blocks->isEmpty()) {
            $this->info('No domains are currently blocked');
            return self::SUCCESS;
        }

        $this->info('Currently blocked domains:');
        $this->newLine();

        $data = $blocks->map(function ($block) {
            return [
                'Domain' => $block->domain,
                'Blocked At' => $block->blocked_at->format('Y-m-d H:i:s'),
                'Unblock At' => $block->unblock_at->format('Y-m-d H:i:s'),
                'Remaining (min)' => now()->diffInMinutes($block->unblock_at),
            ];
        })->toArray();

        $this->table(
            ['Domain', 'Blocked At', 'Unblock At', 'Remaining (min)'],
            $data
        );

        return self::SUCCESS;
    }
}
