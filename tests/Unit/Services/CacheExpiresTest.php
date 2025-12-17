<?php

namespace ThreeRN\HttpService\Tests\Unit\Services;

use ThreeRN\HttpService\Services\HttpService;
use ThreeRN\HttpService\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CacheExpiresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** @test */
    public function it_can_cache_using_datetime_expires_field()
    {
        $futureTime = now()->addHours(2)->format('Y-m-d H:i:s');
        
        Http::fake([
            'api.example.com/*' => Http::response([
                'token' => 'abc123',
                'expirationTime' => $futureTime,
            ], 200),
        ]);

        $service = new HttpService();
        $response = $service->cacheUsingExpires('expirationTime')
            ->expiresAsDatetime()
            ->get('https://api.example.com/token');

        $this->assertEquals('abc123', $response->json('token'));
        
        // Verifica se foi cacheado
        Http::assertSentCount(1);
        
        // Segunda requisição deve vir do cache
        $response2 = $service->cacheUsingExpires('expirationTime')
            ->expiresAsDatetime()
            ->get('https://api.example.com/token');
            
        $this->assertEquals('abc123', $response2->json('token'));
        Http::assertSentCount(1); // Ainda deve ser 1, pois usou cache
    }

    /** @test */
    public function it_can_cache_using_nested_field()
    {
        $futureTime = now()->addHours(1)->format('Y-m-d H:i:s');
        
        Http::fake([
            'api.example.com/*' => Http::response([
                'data' => [
                    'auth' => [
                        'token' => 'xyz789',
                        'expires' => $futureTime,
                    ],
                ],
            ], 200),
        ]);

        $service = new HttpService();
        $response = $service->cacheUsingExpires('data.auth.expires')
            ->expiresAsDatetime()
            ->get('https://api.example.com/auth');

        $this->assertEquals('xyz789', $response->json('data.auth.token'));
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_can_cache_using_seconds_field()
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'token' => 'token123',
                'expires_in' => 3600, // 1 hora em segundos
            ], 200),
        ]);

        $service = new HttpService();
        $response = $service->cacheUsingExpires('expires_in')
            ->expiresAsSeconds()
            ->get('https://api.example.com/token');

        $this->assertEquals('token123', $response->json('token'));
        Http::assertSentCount(1);
        
        // Segunda requisição deve vir do cache
        $response2 = $service->cacheUsingExpires('expires_in')
            ->expiresAsSeconds()
            ->get('https://api.example.com/token');
            
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_can_cache_using_minutes_field()
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'session_id' => 'sess_456',
                'ttl' => 30, // 30 minutos
            ], 200),
        ]);

        $service = new HttpService();
        $response = $service->cacheUsingExpires('ttl')
            ->expiresAsMinutes()
            ->get('https://api.example.com/session');

        $this->assertEquals('sess_456', $response->json('session_id'));
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_uses_fallback_ttl_when_field_not_found()
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'data' => 'some data',
                // Sem campo expirationTime
            ], 200),
        ]);

        $service = new HttpService();
        $response = $service->cacheUsingExpires('expirationTime', 7200) // 2 horas de fallback
            ->expiresAsDatetime()
            ->get('https://api.example.com/data');

        $this->assertEquals('some data', $response->json('data'));
        
        // Deve ter cacheado com o TTL de fallback
        Http::assertSentCount(1);
        
        $response2 = $service->cacheUsingExpires('expirationTime', 7200)
            ->expiresAsDatetime()
            ->get('https://api.example.com/data');
            
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_supports_iso_8601_datetime_format()
    {
        $futureTime = now()->addHours(3)->toIso8601String();
        
        Http::fake([
            'api.example.com/*' => Http::response([
                'resource_id' => 'res_123',
                'expires' => $futureTime,
            ], 200),
        ]);

        $service = new HttpService();
        $response = $service->cacheUsingExpires('expires')
            ->expiresAsDatetime()
            ->get('https://api.example.com/resource');

        $this->assertEquals('res_123', $response->json('resource_id'));
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_returns_minimum_one_second_for_past_dates()
    {
        $pastTime = now()->subHours(1)->format('Y-m-d H:i:s');
        
        Http::fake([
            'api.example.com/*' => Http::response([
                'data' => 'expired',
                'expirationTime' => $pastTime,
            ], 200),
        ]);

        $service = new HttpService();
        $response = $service->cacheUsingExpires('expirationTime')
            ->expiresAsDatetime()
            ->get('https://api.example.com/expired');

        // Mesmo com data no passado, deve cachear por pelo menos 1 segundo
        $this->assertEquals('expired', $response->json('data'));
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_can_chain_with_other_methods()
    {
        $futureTime = now()->addHours(1)->format('Y-m-d H:i:s');
        
        Http::fake([
            'api.example.com/*' => Http::response([
                'result' => 'success',
                'expires' => $futureTime,
            ], 200),
        ]);

        $service = new HttpService();
        $response = $service->withoutLogging()
            ->withoutRateLimit()
            ->cacheUsingExpires('expires')
            ->expiresAsDatetime()
            ->timeout(60)
            ->get('https://api.example.com/data');

        $this->assertEquals('success', $response->json('result'));
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_uses_datetime_mask_by_default()
    {
        $futureTime = now()->addHours(1)->format('Y-m-d H:i:s');
        
        Http::fake([
            'api.example.com/*' => Http::response([
                'token' => 'default_test',
                'expirationTime' => $futureTime,
            ], 200),
        ]);

        $service = new HttpService();
        // Não especifica máscara, deve usar datetime por padrão
        $response = $service->cacheUsingExpires('expirationTime')
            ->get('https://api.example.com/token');

        $this->assertEquals('default_test', $response->json('token'));
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_handles_invalid_datetime_gracefully()
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'token' => 'invalid_date_test',
                'expirationTime' => 'not-a-valid-date',
            ], 200),
        ]);

        $service = new HttpService();
        $response = $service->cacheUsingExpires('expirationTime', 3600) // fallback de 1 hora
            ->expiresAsDatetime()
            ->get('https://api.example.com/token');

        // Deve usar o fallback TTL
        $this->assertEquals('invalid_date_test', $response->json('token'));
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_can_get_nested_value_from_array()
    {
        $service = new HttpService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getNestedValue');
        $method->setAccessible(true);

        $array = [
            'level1' => [
                'level2' => [
                    'level3' => 'found it!',
                ],
            ],
        ];

        $result = $method->invoke($service, $array, 'level1.level2.level3');
        $this->assertEquals('found it!', $result);

        $result = $method->invoke($service, $array, 'level1.level2');
        $this->assertIsArray($result);
        $this->assertEquals('found it!', $result['level3']);

        $result = $method->invoke($service, $array, 'nonexistent.field');
        $this->assertNull($result);
    }

    /** @test */
    public function it_calculates_seconds_until_future_date()
    {
        $service = new HttpService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateSecondsUntil');
        $method->setAccessible(true);

        $futureTime = now()->addMinutes(30)->format('Y-m-d H:i:s');
        $seconds = $method->invoke($service, $futureTime);

        // Deve estar próximo de 1800 segundos (30 minutos)
        $this->assertGreaterThan(1700, $seconds);
        $this->assertLessThan(1900, $seconds);
    }

    /** @test */
    public function it_returns_null_for_invalid_datetime_format()
    {
        $service = new HttpService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateSecondsUntil');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'invalid-date-format');
        $this->assertNull($result);
    }
}
