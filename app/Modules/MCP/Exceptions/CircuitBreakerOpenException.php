<?php

namespace App\Modules\MCP\Exceptions;

use Exception;

class CircuitBreakerOpenException extends Exception
{
    protected string $provider;

    public function __construct(string $provider, string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        $this->provider = $provider;
        $message = $message ?: "Circuit breaker is open for provider: {$provider}. Requests are temporarily paused.";
        parent::__construct($message, $code, $previous);
    }

    public function getProvider(): string
    {
        return $this->provider;
    }
}
