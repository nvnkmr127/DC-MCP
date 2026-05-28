<?php

namespace App\Modules\Reporting\Templates;

class SocialReportTemplate implements ReportTemplateInterface
{
    public function getSections(): array
    {
        return [
            [
                'id' => 'exec_summary',
                'title' => 'Executive Summary',
                'description' => 'Social media channel growth and profile engagement overview.',
            ],
            [
                'id' => 'platform_breakdown',
                'title' => 'Platform Breakdown',
                'description' => 'Metrics comparison across Instagram, Facebook, LinkedIn, etc.',
            ],
            [
                'id' => 'top_posts',
                'title' => 'Top Performing Posts',
                'description' => 'Most engaging posts, reels, or stories by impressions and shares.',
            ],
            [
                'id' => 'engagement_trends',
                'title' => 'Engagement Trends',
                'description' => 'Growth trends for follower counts, comments, likes, and profile visits.',
            ],
            [
                'id' => 'next_month_plan',
                'title' => 'Content Calendar & Next Month Plan',
                'description' => 'Focus content formats, themes, and publication schedule adjustments.',
            ]
        ];
    }

    public function getRequiredMetrics(): array
    {
        return [
            'social_followers_total',
            'social_reach_total',
            'social_engagement_rate',
            'social_interactions',
        ];
    }

    public function getDefaultDateRange(): string
    {
        return 'last_30d';
    }
}
