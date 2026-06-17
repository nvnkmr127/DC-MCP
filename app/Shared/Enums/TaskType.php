<?php

namespace App\Shared\Enums;

enum TaskType: string
{
    case Feature = 'feature';
    case Bug = 'bug';
    case Content = 'content';
    case Design = 'design';
    case Research = 'research';
    case Review = 'review';
    case Meeting = 'meeting';
    case Report = 'report';
    case CampaignSetup = 'campaign_setup';
    case AdCreative = 'ad_creative';
    case SeoAudit = 'seo_audit';
    case EmailSequence = 'email_sequence';
    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
