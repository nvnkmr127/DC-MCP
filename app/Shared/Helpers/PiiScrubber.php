<?php

namespace App\Shared\Helpers;

class PiiScrubber
{
    /**
     * Keys that should have their values scrubbed automatically.
     */
    protected static array $sensitiveKeys = [
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
     * Recursively scrub sensitive keys from array data.
     *
     * @param array $data
     * @return array
     */
    public static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            } elseif (is_string($key) && self::isSensitiveKey($key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_string($value)) {
                $data[$key] = self::scrubString($value);
            }
        }
        return $data;
    }

    /**
     * Scrub sensitive patterns (like emails) from a string.
     *
     * @param string $text
     * @return string
     */
    public static function scrubString(string $text): string
    {
        if (empty($text)) {
            return $text;
        }

        // Scrub email addresses
        $text = preg_replace(
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            '[REDACTED_EMAIL]',
            $text
        );
        
        // Potential future patterns like SSN or Credit Cards can be added here
        
        return $text;
    }

    /**
     * Check if a key contains any of the sensitive terms.
     */
    protected static function isSensitiveKey(string $key): bool
    {
        foreach (self::$sensitiveKeys as $sensitiveKey) {
            if (stripos($key, $sensitiveKey) !== false) {
                return true;
            }
        }
        return false;
    }
}
