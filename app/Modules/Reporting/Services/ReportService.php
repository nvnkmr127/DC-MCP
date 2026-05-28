<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Models\Report;
use App\Modules\Reporting\Models\ReportSchedule;
use App\Modules\Reporting\Templates\SeoReportTemplate;
use App\Modules\Reporting\Templates\AdsReportTemplate;
use App\Modules\Reporting\Templates\SocialReportTemplate;
use App\Modules\Reporting\Templates\SprintReportTemplate;
use App\Modules\Reporting\Templates\FullServiceReportTemplate;
use App\Modules\Reporting\Jobs\GenerateReportJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ReportService
{
    public function __construct(
        protected readonly ReportRenderer $renderer
    ) {}

    /**
     * Map template strings to implementation classes.
     */
    public function getTemplateInstance(string $templateName)
    {
        return match ($templateName) {
            'seo_report' => new SeoReportTemplate(),
            'ads_report' => new AdsReportTemplate(),
            'social_report' => new SocialReportTemplate(),
            'sprint_report' => new SprintReportTemplate(),
            'full_service' => new FullServiceReportTemplate(),
            default => throw new \InvalidArgumentException("Unknown template type: {$templateName}"),
        };
    }

    /**
     * Create a report metadata record.
     */
    public function createReport(array $data): Report
    {
        $template = $this->getTemplateInstance($data['template']);
        
        $from = isset($data['date_from']) ? Carbon::parse($data['date_from']) : now()->subDays(30);
        $to = isset($data['date_to']) ? Carbon::parse($data['date_to']) : now();

        return Report::create([
            'organization_id' => $data['organization_id'],
            'project_id' => $data['project_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'title' => $data['title'] ?? ucfirst(str_replace('_', ' ', $data['template'])) . ' - ' . now()->format('Y-m-d'),
            'type' => $data['type'] ?? 'custom',
            'status' => 'draft',
            'template' => $data['template'],
            'date_from' => $from,
            'date_to' => $to,
            'config' => $data['config'] ?? ['sections' => array_column($template->getSections(), 'id')],
            'recipients' => $data['recipients'] ?? [],
            'generated_by' => $data['generated_by'] ?? null,
        ]);
    }

    /**
     * Generate the report PDF and store to storage.
     */
    public function generateReport(Report $report): Report
    {
        $report->update(['status' => 'generating']);

        try {
            // 1. Gather metrics data based on template requirements
            $template = $this->getTemplateInstance($report->template);
            $metricSlugs = $template->getRequiredMetrics();

            // Fetch snapshots
            $snapshots = DB::table('metric_snapshots')
                ->join('kpi_definitions', 'metric_snapshots.kpi_definition_id', '=', 'kpi_definitions.id')
                ->where('metric_snapshots.organization_id', $report->organization_id)
                ->whereIn('kpi_definitions.slug', $metricSlugs)
                ->whereBetween('metric_snapshots.date_key', [$report->date_from->toDateString(), $report->date_to->toDateString()])
                ->when($report->project_id, fn($q) => $q->where('metric_snapshots.project_id', $report->project_id))
                ->when($report->client_id, fn($q) => $q->where('metric_snapshots.client_id', $report->client_id))
                ->select(
                    'kpi_definitions.slug',
                    'metric_snapshots.value',
                    'metric_snapshots.dimension_1',
                    'metric_snapshots.dimension_2',
                    'metric_snapshots.date_key'
                )
                ->orderBy('metric_snapshots.date_key')
                ->get();

            // Organize snapshots by metric slug
            $metricsData = [];
            foreach ($metricSlugs as $slug) {
                $metricsData[$slug] = $snapshots->where('slug', $slug)->map(fn($row) => [
                    'value' => (float) $row->value,
                    'dimension_1' => $row->dimension_1,
                    'dimension_2' => $row->dimension_2,
                    'date' => $row->date_key,
                ])->values()->toArray();
            }

            // 2. Fetch project/task specific data if project_id is available
            $projectData = null;
            if ($report->project_id) {
                $project = DB::table('projects')->where('id', $report->project_id)->first();
                if ($project) {
                    $totalTasks = DB::table('tasks')->where('project_id', $report->project_id)->count();
                    $completedTasks = DB::table('tasks')->where('project_id', $report->project_id)->where('status', 'done')->count();
                    $overdueTasks = DB::table('tasks')
                        ->where('project_id', $report->project_id)
                        ->whereNotNull('due_date')
                        ->where('due_date', '<', now())
                        ->whereNotIn('status', ['done', 'cancelled'])
                        ->count();

                    $projectData = [
                        'name' => $project->name,
                        'description' => $project->description,
                        'status' => $project->status,
                        'budget' => (float) $project->budget,
                        'budget_used' => (float) $project->budget_used,
                        'tasks_total' => $totalTasks,
                        'tasks_completed' => $completedTasks,
                        'tasks_overdue' => $overdueTasks,
                    ];
                }
            }

            $reportData = [
                'report' => [
                    'title' => $report->title,
                    'type' => $report->type,
                    'template' => $report->template,
                    'date_from' => $report->date_from->toDateString(),
                    'date_to' => $report->date_to->toDateString(),
                ],
                'sections' => $template->getSections(),
                'metrics' => $metricsData,
                'project' => $projectData,
            ];

            // 3. Render and save PDF
            $pdfContent = $this->renderer->renderPdf($report, $reportData);
            
            $path = "reports/{$report->organization_id}/{$report->id}.pdf";
            Storage::disk('public')->put($path, $pdfContent);

            $report->update([
                'status' => 'ready',
                'generated_file_path' => $path,
                'generated_at' => now(),
            ]);

            // Notify or dispatch webhooks here if necessary

            return $report;
        } catch (\Exception $e) {
            $report->update([
                'status' => 'draft',
            ]);
            throw $e;
        }
    }

    /**
     * Send report via email.
     */
    public function sendReport(Report $report, array $recipients): void
    {
        if ($report->status !== 'ready' || !$report->generated_file_path) {
            throw new \Exception("Report is not ready for emailing.");
        }

        $filePath = Storage::disk('public')->path($report->generated_file_path);

        Mail::raw("Please find attached the digital marketing performance report: {$report->title}.", function ($message) use ($report, $recipients, $filePath) {
            $message->to($recipients)
                ->subject("[DIGICLOUDIFY] Report: {$report->title}")
                ->attach($filePath, [
                    'as' => "{$report->title}.pdf",
                    'mime' => 'application/pdf',
                ]);
        });

        $report->update([
            'sent_at' => now(),
            'status' => 'sent',
            'recipients' => array_unique(array_merge($report->recipients ?? [], $recipients)),
        ]);
    }
}
