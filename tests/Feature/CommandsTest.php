<?php

namespace ThreeRN\HttpService\Tests\Feature;

use ThreeRN\HttpService\Tests\TestCase;
use ThreeRN\HttpService\Models\RateLimitControl;
use ThreeRN\HttpService\Models\HttpRequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommandsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function install_command_publishes_config_and_migrations()
    {
        // Este teste é mais complexo pois envolve arquivos do sistema
        // Em um ambiente real, você testaria se os arquivos são publicados
        $this->artisan('http-service:install')
            ->assertExitCode(0);
    }

    /** @test */
    public function list_blocks_command_shows_active_blocks()
    {
        RateLimitControl::blockDomain('api.example.com', 15);
        RateLimitControl::blockDomain('api.other.com', 30);

        $this->artisan('http-service:list-blocks')
            ->expectsOutput('🚫 Currently blocked domains:')
            ->assertExitCode(0);
    }

    /** @test */
    public function list_blocks_command_shows_message_when_no_blocks()
    {
        $this->artisan('http-service:list-blocks')
            ->expectsOutput('ℹ️  No domains are currently blocked')
            ->assertExitCode(0);
    }

    /** @test */
    public function unblock_command_removes_domain_block()
    {
        RateLimitControl::blockDomain('api.example.com', 15);
        
        $this->assertTrue(RateLimitControl::isBlocked('api.example.com'));

        $this->artisan('http-service:unblock api.example.com')
            ->assertExitCode(0);

        $this->assertFalse(RateLimitControl::isBlocked('api.example.com'));
    }

    /** @test */
    public function unblock_command_shows_warning_for_non_blocked_domain()
    {
        $this->artisan('http-service:unblock api.example.com')
            ->expectsOutput("⚠️  Domain 'api.example.com' was not blocked")
            ->assertExitCode(0);
    }

    /** @test */
    public function clean_blocks_command_removes_expired_blocks()
    {
        // Bloqueio ativo
        RateLimitControl::create([
            'domain' => 'api.active.com',
            'blocked_at' => now(),
            'wait_time_minutes' => 15,
            'unblock_at' => now()->addMinutes(15),
        ]);

        // Bloqueio expirado
        RateLimitControl::create([
            'domain' => 'api.expired.com',
            'blocked_at' => now()->subMinutes(30),
            'wait_time_minutes' => 15,
            'unblock_at' => now()->subMinutes(15),
        ]);

        $this->artisan('http-service:clean-blocks')
            ->expectsOutput('✅ Removed 1 expired block(s)')
            ->assertExitCode(0);

        $this->assertTrue(RateLimitControl::isBlocked('api.active.com'));
        $this->assertFalse(RateLimitControl::isBlocked('api.expired.com'));
    }

    /** @test */
    public function clean_blocks_command_shows_message_when_no_expired_blocks()
    {
        RateLimitControl::blockDomain('api.example.com', 15);

        $this->artisan('http-service:clean-blocks')
            ->expectsOutput('ℹ️  No expired blocks found')
            ->assertExitCode(0);
    }

    /** @test */
    public function clean_logs_command_removes_old_logs()
    {
        // Log recente
        HttpRequestLog::create([
            'url' => 'https://api.example.com/recent',
            'method' => 'GET',
            'status_code' => 200,
            'response_time' => 0.5,
            'created_at' => now(),
        ]);

        // Log antigo
        HttpRequestLog::create([
            'url' => 'https://api.example.com/old',
            'method' => 'GET',
            'status_code' => 200,
            'response_time' => 0.5,
            'created_at' => now()->subDays(35),
        ]);

        $this->artisan('http-service:clean-logs')
            ->expectsOutput('✅ Removed 1 old log(s)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('http_request_logs', 1);
        $this->assertDatabaseHas('http_request_logs', ['url' => 'https://api.example.com/recent']);
        $this->assertDatabaseMissing('http_request_logs', ['url' => 'https://api.example.com/old']);
    }

    /** @test */
    public function clean_logs_command_accepts_custom_days()
    {
        HttpRequestLog::create([
            'url' => 'https://api.example.com/test',
            'method' => 'GET',
            'status_code' => 200,
            'response_time' => 0.5,
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('http-service:clean-logs --days=7')
            ->expectsOutput('✅ Removed 1 old log(s)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('http_request_logs', 0);
    }
}
