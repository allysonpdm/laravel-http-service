<?php

namespace ThreeRN\HttpService\Exceptions;

use Exception;

class CircuitBreakerException extends Exception
{
    protected string $domain;
    protected int $remainingSeconds;

    public function __construct(string $domain, int $remainingSeconds = 0)
    {
        $this->domain = $domain;
        $this->remainingSeconds = $remainingSeconds;

        parent::__construct(
            "Circuit breaker is OPEN for domain '{$domain}'. Retry in {$remainingSeconds} second(s).",
            503
        );
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getRemainingSeconds(): int
    {
        return $this->remainingSeconds;
    }
}
