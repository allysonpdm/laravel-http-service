<?php

namespace ThreeRN\HttpService\Tests\Unit\Exceptions;

use ThreeRN\HttpService\Exceptions\RateLimitException;
use ThreeRN\HttpService\Tests\TestCase;

class RateLimitExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_exception_with_correct_message()
    {
        $exception = new RateLimitException('api.example.com', 15);

        $this->assertEquals(
            "Domain 'api.example.com' is rate limited. Try again in 15 minutes.",
            $exception->getMessage()
        );
    }

    /** @test */
    public function it_has_429_status_code()
    {
        $exception = new RateLimitException('api.example.com', 15);

        $this->assertEquals(429, $exception->getCode());
    }

    /** @test */
    public function it_can_get_domain()
    {
        $exception = new RateLimitException('api.example.com', 15);

        $this->assertEquals('api.example.com', $exception->getDomain());
    }

    /** @test */
    public function it_can_get_remaining_minutes()
    {
        $exception = new RateLimitException('api.example.com', 15);

        $this->assertEquals(15, $exception->getRemainingMinutes());
    }

    /** @test */
    public function it_handles_zero_remaining_minutes()
    {
        $exception = new RateLimitException('api.example.com', 0);

        $this->assertEquals(0, $exception->getRemainingMinutes());
        $this->assertStringContainsString('0 minutes', $exception->getMessage());
    }
}
