<?php

namespace ThreeRN\HttpService\Tests\Unit\Models;

use ThreeRN\HttpService\Models\RateLimitControl;
use ThreeRN\HttpService\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class RateLimitControlTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_block_a_domain()
    {
        $control = RateLimitControl::blockDomain('api.example.com', 15);

        $this->assertDatabaseHas('rate_limit_controls', [
            'domain' => 'api.example.com',
            'wait_time_minutes' => 15,
        ]);

        $this->assertInstanceOf(RateLimitControl::class, $control);
    }

    /** @test */
    public function it_can_check_if_domain_is_blocked()
    {
        RateLimitControl::blockDomain('api.example.com', 15);

        $this->assertTrue(RateLimitControl::isBlocked('api.example.com'));
        $this->assertFalse(RateLimitControl::isBlocked('api.other.com'));
    }

    /** @test */
    public function it_returns_false_for_expired_blocks()
    {
        $control = RateLimitControl::create([
            'domain' => 'api.example.com',
            'blocked_at' => now()->subMinutes(30),
            'wait_time_minutes' => 15,
            'unblock_at' => now()->subMinutes(15),
        ]);

        $this->assertFalse(RateLimitControl::isBlocked('api.example.com'));
    }

    /** @test */
    public function it_can_get_remaining_block_time()
    {
        RateLimitControl::blockDomain('api.example.com', 30);

        $remainingTime = RateLimitControl::getRemainingBlockTime('api.example.com');

        $this->assertIsInt($remainingTime);
        $this->assertGreaterThan(0, $remainingTime);
        $this->assertLessThanOrEqual(30, $remainingTime);
    }

    /** @test */
    public function it_returns_null_for_unblocked_domain()
    {
        $remainingTime = RateLimitControl::getRemainingBlockTime('api.example.com');

        $this->assertNull($remainingTime);
    }

    /** @test */
    public function it_can_unblock_a_domain()
    {
        RateLimitControl::blockDomain('api.example.com', 15);
        
        $this->assertTrue(RateLimitControl::isBlocked('api.example.com'));

        $result = RateLimitControl::unblockDomain('api.example.com');

        $this->assertTrue($result);
        $this->assertFalse(RateLimitControl::isBlocked('api.example.com'));
    }

    /** @test */
    public function it_returns_false_when_unblocking_non_blocked_domain()
    {
        $result = RateLimitControl::unblockDomain('api.example.com');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_clean_expired_blocks()
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

        $count = RateLimitControl::cleanExpiredBlocks();

        $this->assertEquals(1, $count);
        $this->assertTrue(RateLimitControl::isBlocked('api.active.com'));
        $this->assertFalse(RateLimitControl::isBlocked('api.expired.com'));
    }

    /** @test */
    public function it_removes_old_blocks_when_blocking_same_domain()
    {
        RateLimitControl::blockDomain('api.example.com', 10);
        $this->assertCount(1, RateLimitControl::where('domain', 'api.example.com')->get());

        RateLimitControl::blockDomain('api.example.com', 20);
        $this->assertCount(1, RateLimitControl::where('domain', 'api.example.com')->get());

        $control = RateLimitControl::where('domain', 'api.example.com')->first();
        $this->assertEquals(20, $control->wait_time_minutes);
    }

    /** @test */
    public function it_can_filter_active_blocks()
    {
        RateLimitControl::create([
            'domain' => 'api.active.com',
            'blocked_at' => now(),
            'wait_time_minutes' => 15,
            'unblock_at' => now()->addMinutes(15),
        ]);

        RateLimitControl::create([
            'domain' => 'api.expired.com',
            'blocked_at' => now()->subMinutes(30),
            'wait_time_minutes' => 15,
            'unblock_at' => now()->subMinutes(15),
        ]);

        $activeBlocks = RateLimitControl::active()->get();

        $this->assertCount(1, $activeBlocks);
        $this->assertEquals('api.active.com', $activeBlocks->first()->domain);
    }

    /** @test */
    public function it_can_filter_expired_blocks()
    {
        RateLimitControl::create([
            'domain' => 'api.active.com',
            'blocked_at' => now(),
            'wait_time_minutes' => 15,
            'unblock_at' => now()->addMinutes(15),
        ]);

        RateLimitControl::create([
            'domain' => 'api.expired.com',
            'blocked_at' => now()->subMinutes(30),
            'wait_time_minutes' => 15,
            'unblock_at' => now()->subMinutes(15),
        ]);

        $expiredBlocks = RateLimitControl::expired()->get();

        $this->assertCount(1, $expiredBlocks);
        $this->assertEquals('api.expired.com', $expiredBlocks->first()->domain);
    }

    /** @test */
    public function it_can_filter_by_domain()
    {
        RateLimitControl::blockDomain('api.example.com', 15);
        RateLimitControl::blockDomain('api.other.com', 20);

        $blocks = RateLimitControl::byDomain('api.example.com')->get();

        $this->assertCount(1, $blocks);
        $this->assertEquals('api.example.com', $blocks->first()->domain);
    }
}
