<?php

namespace App\Logging\Processors;

use Monolog\LogRecord;

class PiiScrubberProcessor
{
    /**
     * Keys that should have their values scrubbed automatically.
     */
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_key',
        'access_token',
        'refresh_token',
        'ssn',
        'credit_card',
        'cvv',
        'auth_code',
        'authorization',
        'cookie',
    ];

    /**
     * Process a log record and mask PII and sensitive data.
     *
     * @param  \Monolog\LogRecord  $record
     * @return \Monolog\LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        
        if (!empty($context)) {
            $context = $this->scrubArray($context);
        }

        // Scrub email addresses from the raw log message
        $message = $record->message;
        $message = preg_replace(
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            '[REDACTED_EMAIL]',
            $message
        );
        
        // Return a mutated clone of the LogRecord (required in Monolog 3)
        return $record->with(message: $message, context: $context);
    }

    /**
     * Recursively scrub sensitive keys from context array.
     */
    private function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->scrubArray($value);
            } elseif (is_string($key) && $this->isSensitiveKey($key)) {
                // If it's a string, preserve its type but redact the contents
                $data[$key] = '[REDACTED]';
            }
        }
        return $data;
    }

    /**
     * Check if a key contains any of the sensitive terms.
     */
    private function isSensitiveKey(string $key): bool
    {
        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if (stripos($key, $sensitiveKey) !== false) {
                return true;
            }
        }
        return false;
    }
}
