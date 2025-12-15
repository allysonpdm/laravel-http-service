<?php

namespace ThreeRN\HttpService\Tests\Unit\Models;

use ThreeRN\HttpService\Models\HttpRequestLog;
use ThreeRN\HttpService\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HttpRequestLogTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_log()
    {
        $log = HttpRequestLog::create([
            'url' => 'https://api.example.com/users',
            'method' => 'GET',
            'payload' => ['test' => 'data'],
            'response' => ['status' => 'success'],
            'status_code' => 200,
            'response_time' => 0.5,
        ]);

        $this->assertDatabaseHas('http_request_logs', [
            'url' => 'https://api.example.com/users',
            'method' => 'GET',
            'status_code' => 200,
        ]);
    }

    /** @test */
    public function it_casts_payload_and_response_to_array()
    {
        $log = HttpRequestLog::create([
            'url' => 'https://api.example.com/users',
            'method' => 'POST',
            'payload' => ['name' => 'John'],
            'response' => ['id' => 1, 'name' => 'John'],
            'status_code' => 201,
            'response_time' => 0.3,
        ]);

        $this->assertIsArray($log->payload);
        $this->assertIsArray($log->response);
        $this->assertEquals(['name' => 'John'], $log->payload);
    }

    /** @test */
    public function it_can_filter_by_url()
    {
        HttpRequestLog::create([
            'url' => 'https://api.example.com/users',
            'method' => 'GET',
            'status_code' => 200,
            'response_time' => 0.5,
        ]);

        HttpRequestLog::create([
            'url' => 'https://api.another.com/data',
            'method' => 'GET',
            'status_code' => 200,
            'response_time' => 0.3,
        ]);

        $logs = HttpRequestLog::byUrl('example.com')->get();

        $this->assertCount(1, $logs);
        $this->assertStringContainsString('example.com', $logs->first()->url);
    }

    /** @test */
    public function it_can_filter_by_method()
    {
        HttpRequestLog::create([
            'url' => 'https://api.example.com/users',
            'method' => 'GET',
            'status_code' => 200,
            'response_time' => 0.5,
        ]);

        HttpRequestLog::create([
            'url' => 'https://api.example.com/users',
            'method' => 'POST',
            'status_code' => 201,
            'response_time' => 0.3,
        ]);

        $logs = HttpRequestLog::byMethod('POST')->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('POST', $logs->first()->method);
    }

    /** @test */
    public function it_can_filter_by_status_code()
    {
        HttpRequestLog::create([
            'url' => 'https://api.example.com/users',
            'method' => 'GET',
            'status_code' => 200,
            'response_time' => 0.5,
        ]);

        HttpRequestLog::create([
            'url' => 'https://api.example.com/error',
            'method' => 'GET',
            'status_code' => 404,
            'response_time' => 0.2,
        ]);

        $logs = HttpRequestLog::byStatusCode(404)->get();

        $this->assertCount(1, $logs);
        $this->assertEquals(404, $logs->first()->status_code);
    }

    /** @test */
    public function it_can_filter_logs_with_errors()
    {
        HttpRequestLog::create([
            'url' => 'https://api.example.com/users',
            'method' => 'GET',
            'status_code' => 200,
            'response_time' => 0.5,
        ]);

        HttpRequestLog::create([
            'url' => 'https://api.example.com/error',
            'method' => 'GET',
            'error_message' => 'Connection timeout',
            'response_time' => 30.0,
        ]);

        $logs = HttpRequestLog::withErrors()->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('Connection timeout', $logs->first()->error_message);
    }

    /** @test */
    public function it_can_filter_successful_requests()
    {
        HttpRequestLog::create([
            'url' => 'https://api.example.com/users',
            'method' => 'GET',
            'status_code' => 200,
            'response_time' => 0.5,
        ]);

        HttpRequestLog::create([
            'url' => 'https://api.example.com/created',
            'method' => 'POST',
            'status_code' => 201,
            'response_time' => 0.3,
        ]);

        HttpRequestLog::create([
            'url' => 'https://api.example.com/error',
            'method' => 'GET',
            'status_code' => 500,
            'response_time' => 0.2,
        ]);

        $logs = HttpRequestLog::successful()->get();

        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn($log) => $log->status_code >= 200 && $log->status_code < 300));
    }
}
