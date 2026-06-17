<?php

namespace App\Shared\Enums;

enum SprintStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
