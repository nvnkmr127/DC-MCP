<?php

namespace App\Modules\Reporting\Templates;

class FullServiceReportTemplate implements ReportTemplateInterface
{
    public function getSections(): array
    {
        return [
            [
                'id' => 'exec_summary',
                'title' => 'Executive Summary',
                'description' => 'Cross-channel digital marketing performance summary.',
            ],
            [
                'id' => 'seo_overview',
                'title' => 'SEO Traffic & Search Visibility',
                'description' => 'Organic impressions, clicks, rankings, and DA summaries.',
            ],
            [
                'id' => 'paid_ads_overview',
                'title' => 'Paid Advertising (Meta/Google Ads)',
                'description' => 'Ad spends, CTR, cost-per-result, conversions, and ROAS.',
            ],
            [
                'id' => 'social_media_overview',
                'title' => 'Social Channel Growth & Engagement',
                'description' => 'Engagement and follower growth overview.',
            ],
            [
                'id' => 'next_steps',
                'title' => 'Recommended Actions & Next Steps',
                'description' => 'Priorities for next month across all active digital channels.',
            ]
        ];
    }

    public function getRequiredMetrics(): array
    {
        return [
            'organic_clicks',
            'organic_impressions',
            'meta_ads_spend',
            'meta_ads_conversions',
            'meta_ads_roas',
            'social_followers_total',
            'social_engagement_rate',
        ];
    }

    public function getDefaultDateRange(): string
    {
        return 'last_30d';
    }
}
