<?php

namespace App\Modules\MCP\DataObjects;

class ConnectionTestResult
{
    public function __construct(
        public readonly bool $isConnected,
        public readonly ?string $errorMessage = null,
        public readonly array $diagnostics = []
    ) {}

    public static function success(array $diagnostics = []): self
    {
        return new self(true, null, $diagnostics);
    }

    public static function failure(string $errorMessage, array $diagnostics = []): self
    {
        return new self(false, $errorMessage, $diagnostics);
    }
}
