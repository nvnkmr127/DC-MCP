<?php

namespace App\Modules\Revenue\Console\Commands;

use App\Modules\Auth\Models\User;
use App\Modules\Notifications\Models\InAppNotification;
use App\Modules\Revenue\Models\CampaignBudget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckBudgetBurnCommand extends Command
{
    protected $signature = 'budgets:check-burn';
    protected $description = 'Alert when campaign budgets hit 70% or 90% spend threshold';

    public function handle(): int
    {
        $month = now()->format('Y-m');

        $budgets = CampaignBudget::where('month_year', $month)
            ->where('allocated_budget', '>', 0)
            ->with('client:id,name,organization_id')
            ->get();

        foreach ($budgets as $budget) {
            if (!$budget->client) {
                continue;
            }

            $utilization = ($budget->spent_amount / $budget->allocated_budget) * 100;

            foreach ([90, 70] as $threshold) {
                if ($utilization < $threshold) {
                    continue;
                }

                $cacheKey = "budget_burn_{$budget->id}_{$threshold}_" . now()->toDateString();
                if (Cache::has($cacheKey)) {
                    continue;
                }

                Cache::put($cacheKey, true, now()->endOfDay());

                $orgId = $budget->client->organization_id;
                $ceos  = User::where('organization_id', $orgId)
                    ->whereHas('roles', fn($q) => $q->where('slug', 'ceo'))
                    ->pluck('id');

                $channel = strtoupper(str_replace('_', ' ', $budget->channel));
                $body    = "⚠️ {$budget->client->name} {$channel} budget is " . round($utilization) . '% spent'
                    . ' (₹' . number_format($budget->spent_amount, 0, '.', ',')
                    . ' / ₹' . number_format($budget->allocated_budget, 0, '.', ',') . ')';

                foreach ($ceos as $userId) {
                    InAppNotification::create([
                        'organization_id' => $orgId,
                        'user_id'         => $userId,
                        'type'            => 'campaign_alert',
                        'channel'         => 'in_app',
                        'title'           => "Budget alert — {$threshold}% threshold reached",
                        'body'            => $body,
                        'data'            => ['budget_id' => $budget->id, 'threshold' => $threshold],
                        'status'          => 'unread',
                        'sent_at'         => now(),
                    ]);
                }
            }
        }

        $this->info('Budget burn check complete.');
        return 0;
    }
}
