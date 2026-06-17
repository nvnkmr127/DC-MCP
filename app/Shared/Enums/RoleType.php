<?php

namespace App\Shared\Enums;

enum RoleType: string
{
    case Ceo = 'ceo';
    case ProjectManager = 'project_manager';
    case Analyst = 'analyst';
    case Marketer = 'marketer';
    case Developer = 'developer';
    case Designer = 'designer';
    case Copywriter = 'copywriter';
    case Client = 'client';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
