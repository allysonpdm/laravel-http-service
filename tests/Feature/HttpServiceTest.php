<?php

namespace ThreeRN\HttpService\Tests\Feature;

use ThreeRN\HttpService\Tests\TestCase;
use ThreeRN\HttpService\Facades\HttpService;
use ThreeRN\HttpService\Models\HttpRequestLog;
use ThreeRN\HttpService\Models\RateLimitControl;
use ThreeRN\HttpService\Exceptions\RateLimitException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class HttpServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake HTTP responses
        Http::fake();
    }

    /** @test */
    public function it_can_make_get_request()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        $response = HttpService::get('https://api.example.com/users');

        $this->assertEquals(200, $response->status());
        $this->assertEquals(['data' => 'success'], $response->json());
    }

    /** @test */
    public function it_can_make_post_request()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['id' => 1], 201),
        ]);

        $response = HttpService::post('https://api.example.com/users', [
            'name' => 'John Doe',
        ]);

        $this->assertEquals(201, $response->status());
    }

    /** @test */
    public function it_logs_successful_requests()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        HttpService::get('https://api.example.com/users');

        $this->assertDatabaseHas('http_request_logs', [
            'url' => 'https://api.example.com/users',
            'method' => 'GET',
            'status_code' => 200,
        ]);
    }

    /** @test */
    public function it_logs_request_payload()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['id' => 1], 201),
        ]);

        HttpService::post('https://api.example.com/users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $log = HttpRequestLog::first();
        $this->assertEquals(['name' => 'John Doe', 'email' => 'john@example.com'], $log->payload);
    }

    /** @test */
    public function it_can_disable_logging_temporarily()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        HttpService::withoutLogging()->get('https://api.example.com/users');

        $this->assertDatabaseCount('http_request_logs', 0);
    }

    /** @test */
    public function it_blocks_domain_after_429_response()
    {
        Http::fake([
            'api.example.com/*' => Http::response(null, 429, ['Retry-After' => '900']),
        ]);

        HttpService::get('https://api.example.com/users');

        $this->assertTrue(RateLimitControl::isBlocked('api.example.com'));
    }

    /** @test */
    public function it_throws_exception_when_domain_is_blocked()
    {
        RateLimitControl::blockDomain('api.example.com', 15);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage("Domain 'api.example.com' is rate limited");

        HttpService::get('https://api.example.com/users');
    }

    /** @test */
    public function it_does_not_make_request_when_blocked()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        RateLimitControl::blockDomain('api.example.com', 15);

        try {
            HttpService::get('https://api.example.com/users');
        } catch (RateLimitException $e) {
            // Expected
        }

        // Verifica que nenhuma requisição HTTP foi feita
        Http::assertNothingSent();
    }

    /** @test */
    public function it_can_disable_rate_limiting_temporarily()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        RateLimitControl::blockDomain('api.example.com', 15);

        $response = HttpService::withoutRateLimit()->get('https://api.example.com/users');

        $this->assertEquals(200, $response->status());
    }

    /** @test */
    public function it_extracts_retry_after_from_header()
    {
        Http::fake([
            'api.example.com/*' => Http::response(null, 429, ['Retry-After' => '1800']), // 30 minutos
        ]);

        HttpService::get('https://api.example.com/users');

        $control = RateLimitControl::where('domain', 'api.example.com')->first();
        $this->assertEquals(30, $control->wait_time_minutes);
    }

    /** @test */
    public function it_uses_default_block_time_when_no_retry_after()
    {
        Http::fake([
            'api.example.com/*' => Http::response(null, 429),
        ]);

        HttpService::get('https://api.example.com/users');

        $control = RateLimitControl::where('domain', 'api.example.com')->first();
        $this->assertEquals(15, $control->wait_time_minutes); // default do config
    }

    /** @test */
    public function it_can_make_request_with_custom_headers()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        HttpService::post(
            'https://api.example.com/users',
            ['name' => 'John'],
            ['Authorization' => 'Bearer token123', 'X-Custom' => 'value']
        );

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer token123') &&
                   $request->hasHeader('X-Custom', 'value');
        });
    }

    /** @test */
    public function it_logs_errors_when_request_fails()
    {
        Http::fake(function () {
            throw new \Exception('Connection timeout');
        });

        try {
            HttpService::get('https://api.example.com/users');
        } catch (\Exception $e) {
            // Expected
        }

        $log = HttpRequestLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('Connection timeout', $log->error_message);
        $this->assertNull($log->status_code);
    }

    /** @test */
    public function it_can_set_custom_timeout()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        HttpService::timeout(60)->get('https://api.example.com/users');

        Http::assertSent(function ($request) {
            return $request->timeout() === 60;
        });
    }

    /** @test */
    public function it_tracks_response_time()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        HttpService::get('https://api.example.com/users');

        $log = HttpRequestLog::first();
        $this->assertNotNull($log->response_time);
        $this->assertIsFloat($log->response_time);
        $this->assertGreaterThan(0, $log->response_time);
    }

    /** @test */
    public function it_can_chain_multiple_options()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        RateLimitControl::blockDomain('api.example.com', 15);

        $response = HttpService::withoutLogging()
            ->withoutRateLimit()
            ->timeout(45)
            ->get('https://api.example.com/users');

        $this->assertEquals(200, $response->status());
        $this->assertDatabaseCount('http_request_logs', 0);
    }

    /** @test */
    public function it_supports_all_http_methods()
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'success'], 200),
        ]);

        HttpService::get('https://api.example.com/users');
        HttpService::post('https://api.example.com/users', ['name' => 'John']);
        HttpService::put('https://api.example.com/users/1', ['name' => 'Jane']);
        HttpService::patch('https://api.example.com/users/1', ['email' => 'new@email.com']);
        HttpService::delete('https://api.example.com/users/1');

        $this->assertDatabaseCount('http_request_logs', 5);
        $this->assertDatabaseHas('http_request_logs', ['method' => 'GET']);
        $this->assertDatabaseHas('http_request_logs', ['method' => 'POST']);
        $this->assertDatabaseHas('http_request_logs', ['method' => 'PUT']);
        $this->assertDatabaseHas('http_request_logs', ['method' => 'PATCH']);
        $this->assertDatabaseHas('http_request_logs', ['method' => 'DELETE']);
    }
}
