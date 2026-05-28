<?php

namespace App\Modules\Revenue\Services;

use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Revenue\Models\Invoice;
use Illuminate\Support\Facades\DB;

class ClientHealthService
{
    public function computeHealth(Client $client): array
    {
        $breakdown = [
            'deliverable_score' => $this->deliverableScore($client),
            'payment_score'     => $this->paymentScore($client),
            'activity_score'    => $this->activityScore($client),
            'sla_score'         => $this->slaScore($client),
        ];

        $score = (int) round(
            $breakdown['deliverable_score'] * 0.35 +
            $breakdown['payment_score']     * 0.30 +
            $breakdown['activity_score']    * 0.20 +
            $breakdown['sla_score']         * 0.15
        );

        $status = match(true) {
            $score >= 70 => 'green',
            $score >= 40 => 'yellow',
            default      => 'red',
        };

        $client->update([
            'health_score'       => $score,
            'health_status'      => $status,
            'health_breakdown'   => $breakdown,
            'health_computed_at' => now(),
        ]);

        return compact('score', 'status', 'breakdown');
    }

    public function computeAllForOrg(string $orgId): void
    {
        Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->each(fn($client) => $this->computeHealth($client));
    }

    private function deliverableScore(Client $client): int
    {
        $tasks = Task::where('client_id', $client->id)
            ->whereDate('due_date', '>=', now()->subDays(30))
            ->get(['status', 'due_date']);

        if ($tasks->isEmpty()) return 75;

        $onTime = $tasks->filter(fn($t) =>
            $t->status === 'done' && (!$t->due_date || $t->due_date->gte(now()))
        )->count();

        return (int) round(($onTime / $tasks->count()) * 100);
    }

    private function paymentScore(Client $client): int
    {
        $invoices = Invoice::where('client_id', $client->id)
            ->whereIn('status', ['paid', 'overdue', 'sent'])
            ->whereDate('due_date', '>=', now()->subDays(90))
            ->get(['status', 'due_date']);

        if ($invoices->isEmpty()) return 100;

        $overdue = $invoices->filter(fn($i) => $i->isOverdue())->count();
        return (int) round((1 - ($overdue / $invoices->count())) * 100);
    }

    private function activityScore(Client $client): int
    {
        $lastTask = Task::where('client_id', $client->id)
            ->latest('updated_at')
            ->value('updated_at');

        if (!$lastTask) return 50;

        $daysSince = now()->diffInDays($lastTask);
        return match(true) {
            $daysSince <= 7  => 100,
            $daysSince <= 14 => 80,
            $daysSince <= 30 => 60,
            $daysSince <= 60 => 30,
            default          => 0,
        };
    }

    private function slaScore(Client $client): int
    {
        $breached = Task::where('client_id', $client->id)
            ->where('sla_status', 'breached')
            ->whereDate('updated_at', '>=', now()->subDays(30))
            ->count();

        return match(true) {
            $breached === 0 => 100,
            $breached === 1 => 70,
            $breached === 2 => 40,
            default         => 10,
        };
    }
}
