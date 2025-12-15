<?php

namespace ThreeRN\HttpService\Console\Commands;

use Illuminate\Console\Command;
use ThreeRN\HttpService\Models\RateLimitControl;

class UnblockDomainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'http-service:unblock {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually unblock a domain from rate limiting';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $domain = $this->argument('domain');

        $this->info("Unblocking domain: {$domain}");

        if (RateLimitControl::unblockDomain($domain)) {
            $this->info("✅ Domain '{$domain}' has been unblocked");
        } else {
            $this->warn("⚠️  Domain '{$domain}' was not blocked");
        }

        return self::SUCCESS;
    }
}
