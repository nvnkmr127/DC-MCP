<?php

namespace App\Modules\Reporting\Templates;

interface ReportTemplateInterface
{
    /**
     * Get the sections of the report template.
     *
     * @return array
     */
    public function getSections(): array;

    /**
     * Get required KPI slugs for the template.
     *
     * @return array
     */
    public function getRequiredMetrics(): array;

    /**
     * Get the default date range (e.g. 'last_30d', 'last_7d').
     *
     * @return string
     */
    public function getDefaultDateRange(): string;
}
