<?php

namespace App\Modules\Reporting\Templates;

class SeoReportTemplate implements ReportTemplateInterface
{
    public function getSections(): array
    {
        return [
            [
                'id' => 'exec_summary',
                'title' => 'Executive Summary',
                'description' => 'High level summary of organic SEO performance and achievements.',
            ],
            [
                'id' => 'keyword_rankings',
                'title' => 'Keyword Rankings',
                'description' => 'Target keyword search engine positions and movements.',
            ],
            [
                'id' => 'organic_traffic',
                'title' => 'Organic Traffic',
                'description' => 'Search engine clicks, impressions, and CTR metrics.',
            ],
            [
                'id' => 'tech_health',
                'title' => 'Technical Health',
                'description' => 'Site crawling errors, speed, Core Web Vitals, and indexing issues.',
            ],
            [
                'id' => 'backlinks',
                'title' => 'Backlink Profile',
                'description' => 'Domain Authority, new referring domains, and link building progress.',
            ],
            [
                'id' => 'next_steps',
                'title' => 'Recommended Actions & Next Steps',
                'description' => 'Immediate priorities and next month focus.',
            ]
        ];
    }

    public function getRequiredMetrics(): array
    {
        return [
            'organic_clicks',
            'organic_impressions',
            'average_ctr',
            'average_position',
            'domain_authority',
            'referring_domains',
        ];
    }

    public function getDefaultDateRange(): string
    {
        return 'last_30d';
    }
}
