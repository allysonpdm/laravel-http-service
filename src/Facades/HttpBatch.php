<?php

namespace ThreeRN\HttpService\Facades;

use Illuminate\Support\Facades\Facade;

class HttpBatch extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \ThreeRN\HttpService\Services\HttpBatchService::class;
    }
}
