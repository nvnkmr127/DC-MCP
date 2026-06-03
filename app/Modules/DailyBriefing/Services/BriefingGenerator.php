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
     * Returns the briefing record along with parsed task suggestions.
     *
     * @return array{briefing: DailyBriefing, suggestions: array}
     */
    public function generate(User $user, array $data): array
    {
        $apiKey = config('ai.anthropic.api_key');
        $apiUrl = config('ai.anthropic.api_url', 'https://api.anthropic.com/v1/messages');
        $model  = config('ai.anthropic.model');

        if (!$model) {
            throw new \RuntimeException('ANTHROPIC_MODEL is not configured. Set it in config/ai.php or via the ANTHROPIC_MODEL env var.');
        }

        $htmlContent  = null;
        $suggestions  = [];
        $tokensUsed   = 0;

        $roles      = $data['user']['role'] ?? 'member';
        $isCeo      = $roles === 'ceo' || (is_array($roles) && in_array('ceo', $roles));

        $systemPrompt = "You are the AI Chief of Staff for Digicloudify, a digital marketing agency in Hyderabad. " .
            "Your job is to analyze the day's operational data, write a crisp morning briefing, " .
            "and suggest concrete tasks the team should work on today. " .
            "Be direct, actionable, and prioritise ruthlessly. No fluff.";

        $userPrompt = "Here is today's operational data for the agency:\n\n" .
            json_encode($data, JSON_PRETTY_PRINT) . "\n\n" .
            "Respond with EXACTLY this structure — nothing before or after:\n\n" .
            "<briefing>\n" .
            "HTML content here (for email display). Include:\n" .
            "- One-line 'Today's Focus' sentence\n" .
            "- Priority Alerts section (urgent items, overdue tasks, SLA risks)\n" .
            "- Task Summary table\n" .
            "- Metric Highlights (only if ceo or analyst role)\n" .
            "- MCP Data Highlights (calendar events, Notion updates, client emails)\n" .
            "- Quick Wins (2-3 easy wins for today)\n" .
            "</briefing>\n\n" .
            "<suggestions>\n" .
            "A JSON array of 3-7 task suggestions based on the data. Each suggestion:\n" .
            "{\n" .
            "  \"title\": \"Short action-oriented task title\",\n" .
            "  \"description\": \"2-3 sentence context and acceptance criteria\",\n" .
            "  \"client_name\": \"Client company name or null if internal\",\n" .
            "  \"project_name\": \"Project name or null\",\n" .
            "  \"role_required\": \"one of: ceo, project_manager, analyst, marketer, developer, designer, copywriter\",\n" .
            "  \"priority\": \"one of: low, medium, high, urgent\",\n" .
            "  \"estimated_hours\": integer or null,\n" .
            "  \"due_date\": \"YYYY-MM-DD or null\",\n" .
            "  \"reasoning\": \"One sentence explaining why this task matters today\"\n" .
            "}\n" .
            "</suggestions>";

        if ($apiKey) {
            try {
                $response = Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->timeout(30)
                ->post($apiUrl, [
                    'model'      => $model,
                    'max_tokens' => 3000,
                    'system'     => $systemPrompt,
                    'messages'   => [['role' => 'user', 'content' => $userPrompt]],
                ]);

                if ($response->successful()) {
                    $result     = $response->json();
                    $rawOutput  = $result['content'][0]['text'] ?? '';
                    $tokensUsed = ($result['usage']['input_tokens'] ?? 0) + ($result['usage']['output_tokens'] ?? 0);

                    [$htmlContent, $suggestions] = $this->parseOutput($rawOutput);
                }
            } catch (\Exception $e) {
                // Fall through to local fallback
            }
        }

        if (!$htmlContent) {
            $htmlContent = $this->generateFallbackHtml($user, $data);
            $model       = 'local-fallback';
        }

        $htmlContent = trim($htmlContent);

        $briefingDate = $data['date'] ?? now()->toDateString();
        $briefing = DailyBriefing::updateOrCreate(
            [
                'organization_id' => $user->organization_id,
                'user_id'         => $user->id,
                'date'            => $briefingDate,
            ],
            [
                'status'         => 'ready',
                'digest_raw'     => $data,
                'digest_html'    => $htmlContent,
                'digest_text'    => strip_tags($htmlContent),
                'ai_model'       => $model,
                'ai_tokens_used' => $tokensUsed,
            ]
        );

        return ['briefing' => $briefing, 'suggestions' => $suggestions];
    }

    /**
     * Parse Claude's structured output into HTML and suggestions array.
     */
    private function parseOutput(string $raw): array
    {
        $html        = null;
        $suggestions = [];

        // Extract <briefing>...</briefing>
        if (preg_match('/<briefing>(.*?)<\/briefing>/s', $raw, $m)) {
            $html = trim($m[1]);
        }

        // Clean up stray markdown code fences
        if ($html && str_contains($html, '```html')) {
            $html = trim(Str::between($html, '```html', '```'));
        } elseif ($html && str_contains($html, '```')) {
            $html = trim(Str::between($html, '```', '```'));
        }

        // Extract <suggestions>...</suggestions>
        if (preg_match('/<suggestions>(.*?)<\/suggestions>/s', $raw, $m)) {
            $jsonRaw = trim($m[1]);
            // Strip any markdown code fences around the JSON
            if (str_contains($jsonRaw, '```json')) {
                $jsonRaw = trim(Str::between($jsonRaw, '```json', '```'));
            } elseif (str_contains($jsonRaw, '```')) {
                $jsonRaw = trim(Str::between($jsonRaw, '```', '```'));
            }

            try {
                $parsed = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($parsed)) {
                    $suggestions = $parsed;
                }
            } catch (\JsonException $e) {
                // Best-effort; suggestions stay empty
            }
        }

        return [$html, $suggestions];
    }

    /**
     * Fallback HTML briefing when AI API is unavailable.
     */
    protected function generateFallbackHtml(User $user, array $data): string
    {
        $dueTodayCount          = count($data['tasks']['due_today'] ?? []);
        $overdueCount           = count($data['tasks']['overdue'] ?? []);
        $slaWarningCount        = count($data['tasks']['sla_warning'] ?? []);
        $completedYesterdayCount = count($data['tasks']['completed_yesterday'] ?? []);
        $calendarCount          = count($data['calendar']['events'] ?? []);
        $emailsCount            = count($data['emails']['unread_client_emails'] ?? []);
        $notionCount            = count($data['notion']['recent_updates'] ?? []);

        $html  = "<div style=\"font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333\">";
        $html .= "<h2 style=\"color:#1e3a8a;border-bottom:2px solid #3b82f6;padding-bottom:8px\">Daily Briefing — {$data['date']}</h2>";
        $html .= "<p style=\"font-size:16px;font-style:italic;color:#555\"><strong>Today's Focus:</strong> Stay on top of deadlines and address critical client messages today.</p>";

        if ($overdueCount > 0 || $slaWarningCount > 0) {
            $html .= "<div style=\"background-color:#fef2f2;border-left:4px solid #ef4444;padding:12px;margin-bottom:20px\">";
            $html .= "<h3 style=\"margin-top:0;color:#991b1b\">Priority Alerts</h3><ul>";
            if ($overdueCount > 0)     $html .= "<li>You have <strong>{$overdueCount} overdue tasks</strong> requiring immediate attention.</li>";
            if ($slaWarningCount > 0)  $html .= "<li><strong>{$slaWarningCount} tasks</strong> are within 4 hours of SLA breach.</li>";
            $html .= "</ul></div>";
        }

        $html .= "<h3 style=\"color:#1e3a8a;margin-top:24px\">Task Summary</h3>";
        $html .= "<table style=\"width:100%;border-collapse:collapse;margin-bottom:20px\">";
        $html .= "<tr><td style=\"padding:8px;border-bottom:1px solid #e5e7eb\">Due Today</td><td style=\"padding:8px;border-bottom:1px solid #e5e7eb;font-weight:bold\">{$dueTodayCount}</td></tr>";
        $html .= "<tr><td style=\"padding:8px;border-bottom:1px solid #e5e7eb\">Overdue</td><td style=\"padding:8px;border-bottom:1px solid #e5e7eb;font-weight:bold;color:#ef4444\">{$overdueCount}</td></tr>";
        $html .= "<tr><td style=\"padding:8px;border-bottom:1px solid #e5e7eb\">Completed Yesterday</td><td style=\"padding:8px;border-bottom:1px solid #e5e7eb;font-weight:bold;color:#10b981\">{$completedYesterdayCount}</td></tr>";
        $html .= "</table>";

        if ($calendarCount > 0) {
            $html .= "<h3 style=\"color:#1e3a8a\">Today's Calendar</h3><ul>";
            foreach ($data['calendar']['events'] as $event) {
                $time  = isset($event['start']) ? date('H:i', strtotime($event['start'])) : 'All Day';
                $html .= "<li><strong>{$time}</strong>: {$event['summary']}</li>";
            }
            $html .= "</ul>";
        }

        if ($notionCount > 0) {
            $html .= "<h3 style=\"color:#1e3a8a\">Notion Updates (last 24h)</h3><ul>";
            foreach (array_slice($data['notion']['recent_updates'], 0, 5) as $page) {
                $html .= "<li><a href=\"{$page['url']}\">{$page['title']}</a></li>";
            }
            $html .= "</ul>";
        }

        if (!empty($data['metrics']['meta_ads'])) {
            $html .= "<h3 style=\"color:#1e3a8a\">Meta Ads Metrics</h3>";
            $html .= "<table style=\"width:100%;border-collapse:collapse;margin-bottom:20px\">";
            $html .= "<tr style=\"background-color:#f3f4f6\"><th style=\"padding:8px;text-align:left\">Metric</th><th style=\"padding:8px;text-align:right\">Yesterday</th><th style=\"padding:8px;text-align:right\">Change %</th></tr>";
            foreach ($data['metrics']['meta_ads'] as $key => $values) {
                $val         = number_format($values['yesterday'], 2);
                $changeColor = $values['change_pct'] >= 0 ? '#10b981' : '#ef4444';
                $changeSign  = $values['change_pct'] >= 0 ? '+' : '';
                $html .= "<tr><td style=\"padding:8px;border-bottom:1px solid #e5e7eb\">" . ucfirst($key) . "</td>";
                $html .= "<td style=\"padding:8px;border-bottom:1px solid #e5e7eb;text-align:right\">{$val}</td>";
                $html .= "<td style=\"padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;color:{$changeColor};font-weight:bold\">{$changeSign}{$values['change_pct']}%</td></tr>";
            }
            $html .= "</table>";
        }

        if ($emailsCount > 0) {
            $html .= "<h3 style=\"color:#1e3a8a\">Unread Client Emails (last 24h)</h3><ul>";
            foreach (array_slice($data['emails']['unread_client_emails'], 0, 5) as $email) {
                $html .= "<li style=\"margin-bottom:8px\"><strong>From:</strong> {$email['from']}<br><small style=\"color:#666\">{$email['subject']}</small></li>";
            }
            $html .= "</ul>";
        }

        $html .= "<div style=\"margin-top:30px;font-size:12px;color:#888;text-align:center;border-top:1px solid #e5e7eb;padding-top:10px\">Digicloudify Morning Assistant • Hyderabad, India</div>";
        $html .= "</div>";

        return $html;
    }
}
