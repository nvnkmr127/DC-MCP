<?php

namespace App\Shared\Helpers;

class HtmlSanitizer
{
    /**
     * Recursively sanitize all string values in an array/object by stripping HTML tags.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function sanitize($data)
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                // Also sanitize keys if necessary, but typically values are the risk
                $sanitized[$key] = self::sanitize($value);
            }
            return $sanitized;
        }

        if (is_string($data)) {
            return strip_tags($data);
        }

        return $data;
    }
}
