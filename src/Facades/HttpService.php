<?php

namespace ThreeRN\HttpService\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Http\Client\Response get(string $url, array $query = [], array $headers = [])
 * @method static \Illuminate\Http\Client\Response post(string $url, array $data = [], array $headers = [])
 * @method static \Illuminate\Http\Client\Response put(string $url, array $data = [], array $headers = [])
 * @method static \Illuminate\Http\Client\Response patch(string $url, array $data = [], array $headers = [])
 * @method static \Illuminate\Http\Client\Response delete(string $url, array $data = [], array $headers = [])
 * @method static \ThreeRN\HttpService\Services\HttpService withoutLogging()
 * @method static \ThreeRN\HttpService\Services\HttpService withLogging()
 * @method static \ThreeRN\HttpService\Services\HttpService withoutRateLimit()
 * @method static \ThreeRN\HttpService\Services\HttpService withRateLimit()
 * @method static \ThreeRN\HttpService\Services\HttpService timeout(int $seconds)
 *
 * @see \ThreeRN\HttpService\Services\HttpService
 */
class HttpService extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'http-service';
    }
}
