<?php

namespace App\Modules\Reporting\Templates;

class AdsReportTemplate implements ReportTemplateInterface
{
    public function getSections(): array
    {
        return [
            [
                'id' => 'exec_summary',
                'title' => 'Executive Summary',
                'description' => 'Summary of ad performance, conversions, and return on ad spend.',
            ],
            [
                'id' => 'spend_overview',
                'title' => 'Spend Overview',
                'description' => 'Total budget allocation, spend to date, and average CPC/CPM.',
            ],
            [
                'id' => 'campaign_performance',
                'title' => 'Campaign Performance',
                'description' => 'Campaign level breakdown of impressions, clicks, conversions, and ROAS.',
            ],
            [
                'id' => 'top_ads',
                'title' => 'Top Performing Creatives',
                'description' => 'Creatives with the highest CTR, lowest cost per result, or highest ROAS.',
            ],
            [
                'id' => 'audience_insights',
                'title' => 'Audience & Demographic Insights',
                'description' => 'Conversion breakdown by age, gender, location, and platform placement.',
            ],
            [
                'id' => 'recommendations',
                'title' => 'Optimization & Recommendations',
                'description' => 'Actionable insights for budget reallocation, creative changes, and testing.',
            ]
        ];
    }

    public function getRequiredMetrics(): array
    {
        return [
            'meta_ads_spend',
            'meta_ads_clicks',
            'meta_ads_impressions',
            'meta_ads_cpc',
            'meta_ads_cpm',
            'meta_ads_ctr',
            'meta_ads_conversions',
            'meta_ads_roas',
        ];
    }

    public function getDefaultDateRange(): string
    {
        return 'last_30d';
    }
}
