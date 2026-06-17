<?php

namespace App\Shared\Enums;

enum ClientTier: string
{
    case Basic = 'basic';
    case Standard = 'standard';
    case Premium = 'premium';
    case Enterprise = 'enterprise';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
