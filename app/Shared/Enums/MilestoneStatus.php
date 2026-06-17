<?php

namespace App\Shared\Enums;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Missed = 'missed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
