<?php

namespace App\Modules\DailyBriefing\Services;

use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BriefingGenerator
{
    /**
     * Generate daily briefing using Claude API or template fallback.
     */
    public function generate(User $user, array $data): DailyBriefing
    {
        $apiKey = config('ai.anthropic.api_key');
        $apiUrl = config('ai.anthropic.api_url', 'https://api.anthropic.com/v1/messages');
        $model = config('ai.anthropic.model', 'claude-3-5-sonnet-20241022');

        $htmlContent = null;
        $tokensUsed = 0;

        if ($apiKey) {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->timeout(15)
                ->post($apiUrl, [
                    'model' => $model,
                    'max_tokens' => 1500,
                    'system' => "You are a smart executive assistant for Digicloudify, a digital marketing agency in Hyderabad. Generate a concise, actionable daily briefing. Be direct and prioritise urgent items. Use clear sections. No fluff. Output in HTML format suitable for email.",
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => "Here is the structured JSON data for the day. Write a personalized daily briefing in HTML containing:\n" .
                                         "- A one-line 'Today's focus' sentence\n" .
                                         "- Priority alerts (urgent items, overdue tasks, or SLA breaches)\n" .
                                         "- Task summary\n" .
                                         "- Metric highlights (only if the user is a ceo or analyst)\n" .
                                         "- Quick wins (2-3 easy tasks to knock out today)\n\n" .
                                         json_encode($data, JSON_PRETTY_PRINT)
                        ]
                    ]
                ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $htmlContent = $result['content'][0]['text'] ?? null;
                    $tokensUsed = ($result['usage']['input_tokens'] ?? 0) + ($result['usage']['output_tokens'] ?? 0);
                }
            } catch (\Exception $e) {
                // Fail silently and use fallback
            }
        }

        // Fallback to local template-based briefing if Claude API fails or is missing
        if (!$htmlContent) {
            $htmlContent = $this->generateFallbackHtml($user, $data);
            $model = 'local-fallback';
        }

        // Clean up markdown markers if Claude returns HTML inside markdown block
        if (str_contains($htmlContent, '```html')) {
            $htmlContent = Str::between($htmlContent, '```html', '```');
        } elseif (str_contains($htmlContent, '```')) {
            $htmlContent = Str::between($htmlContent, '```', '```');
        }
        $htmlContent = trim($htmlContent);

        // Find or create DailyBriefing record
        $briefingDate = $data['date'] ?? now()->toDateString();
        $briefing = DailyBriefing::updateOrCreate(
            [
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'date' => $briefingDate,
            ],
            [
                'status' => 'ready',
                'digest_raw' => $data,
                'digest_html' => $htmlContent,
                'digest_text' => strip_tags($htmlContent),
                'ai_model' => $model,
                'ai_tokens_used' => $tokensUsed,
            ]
        );

        return $briefing;
    }

    /**
     * Generate fallback HTML briefing for users.
     */
    protected function generateFallbackHtml(User $user, array $data): string
    {
        $dueTodayCount = count($data['tasks']['due_today'] ?? []);
        $overdueCount = count($data['tasks']['overdue'] ?? []);
        $slaWarningCount = count($data['tasks']['sla_warning'] ?? []);
        $completedYesterdayCount = count($data['tasks']['completed_yesterday'] ?? []);
        $calendarCount = count($data['calendar']['events'] ?? []);
        $emailsCount = count($data['emails']['unread_client_emails'] ?? []);
        
        $html = "<div style=\"font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;\">";
        $html .= "<h2 style=\"color: #1e3a8a; border-bottom: 2px solid #3b82f6; padding-bottom: 8px;\">📊 Daily Briefing — {$data['date']}</h2>";
        $html .= "<p style=\"font-size: 16px; font-style: italic; color: #555;\"><strong>Today's Focus:</strong> Stay on top of deadlines and address critical client messages today.</p>";

        // Priority Alerts Section
        if ($overdueCount > 0 || $slaWarningCount > 0) {
            $html .= "<div style=\"background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 12px; margin-bottom: 20px;\">";
            $html .= "<h3 style=\"margin-top: 0; color: #991b1b;\">⚠️ Priority Alerts</h3>";
            $html .= "<ul>";
            if ($overdueCount > 0) {
                $html .= "<li>You have <strong>{$overdueCount} overdue tasks</strong> requiring immediate attention.</li>";
            }
            if ($slaWarningCount > 0) {
                $html .= "<li><strong>{$slaWarningCount} tasks</strong> are within 4 hours of SLA breach.</li>";
            }
            $html .= "</ul>";
            $html .= "</div>";
        }

        // Tasks Summary Section
        $html .= "<h3 style=\"color: #1e3a8a; margin-top: 24px;\">📋 Task Summary</h3>";
        $html .= "<table style=\"width: 100%; border-collapse: collapse; margin-bottom: 20px;\">";
        $html .= "<tr><td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb;\">Due Today</td><td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold;\">{$dueTodayCount}</td></tr>";
        $html .= "<tr><td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb;\">Overdue</td><td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold; color: #ef4444;\">{$overdueCount}</td></tr>";
        $html .= "<tr><td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb;\">Completed Yesterday</td><td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold; color: #10b981;\">{$completedYesterdayCount}</td></tr>";
        $html .= "</table>";

        // Today's Calendar Section
        if ($calendarCount > 0) {
            $html .= "<h3 style=\"color: #1e3a8a;\">📅 Today's Calendar</h3>";
            $html .= "<ul>";
            foreach ($data['calendar']['events'] as $event) {
                $time = isset($event['start']) ? date('H:i', strtotime($event['start'])) : 'All Day';
                $html .= "<li><strong>{$time}</strong>: {$event['summary']}</li>";
            }
            $html .= "</ul>";
        }

        // Metrics Section (Analyst/CEO)
        if (!empty($data['metrics']['meta_ads'])) {
            $html .= "<h3 style=\"color: #1e3a8a;\">📈 Meta Ads Metrics</h3>";
            $html .= "<table style=\"width: 100%; border-collapse: collapse; margin-bottom: 20px;\">";
            $html .= "<tr style=\"background-color: #f3f4f6;\"><th style=\"padding: 8px; text-align: left;\">Metric</th><th style=\"padding: 8px; text-align: right;\">Yesterday</th><th style=\"padding: 8px; text-align: right;\">Change %</th></tr>";
            foreach ($data['metrics']['meta_ads'] as $key => $values) {
                $val = number_format($values['yesterday'], 2);
                $changeColor = $values['change_pct'] >= 0 ? '#10b981' : '#ef4444';
                $changeSign = $values['change_pct'] >= 0 ? '+' : '';
                $html .= "<tr>";
                $html .= "<td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb;\">" . ucfirst($key) . "</td>";
                $html .= "<td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right;\">{$val}</td>";
                $html .= "<td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right; color: {$changeColor}; font-weight: bold;\">{$changeSign}{$values['change_pct']}%</td>";
                $html .= "</tr>";
            }
            $html .= "</table>";
        }

        // Client Emails Section
        if ($emailsCount > 0) {
            $html .= "<h3 style=\"color: #1e3a8a;\">✉️ Unread Client Emails (last 24h)</h3>";
            $html .= "<ul>";
            foreach (array_slice($data['emails']['unread_client_emails'], 0, 5) as $email) {
                $html .= "<li style=\"margin-bottom: 8px;\"><strong>From:</strong> {$email['from']}<br/><small style=\"color: #666;\">{$email['subject']}</small></li>";
            }
            $html .= "</ul>";
        }

        // Quick Wins
        $html .= "<h3 style=\"color: #1e3a8a;\">⚡ Quick Wins</h3>";
        $html .= "<p>Identify easy-to-complete tasks to resolve quickly and unblock dependencies for your teammates.</p>";

        $html .= "<div style=\"margin-top: 30px; font-size: 12px; color: #888; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 10px;\">";
        $html .= "Digicloudify Morning Assistant • Hyderabad, India";
        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}
