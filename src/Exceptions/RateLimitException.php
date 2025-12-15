<?php

namespace ThreeRN\HttpService\Exceptions;

use Exception;

class RateLimitException extends Exception
{
    protected string $domain;
    protected int $remainingMinutes;

    public function __construct(string $domain, int $remainingMinutes)
    {
        $this->domain = $domain;
        $this->remainingMinutes = $remainingMinutes;

        parent::__construct(
            "Domain '{$domain}' is rate limited. Try again in {$remainingMinutes} minutes.",
            429
        );
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getRemainingMinutes(): int
    {
        return $this->remainingMinutes;
    }
}
