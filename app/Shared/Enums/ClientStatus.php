<?php

namespace App\Shared\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Prospect = 'prospect';
    case Churned = 'churned';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
