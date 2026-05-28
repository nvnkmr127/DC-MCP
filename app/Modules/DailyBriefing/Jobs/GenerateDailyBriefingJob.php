<?php

namespace App\Modules\DailyBriefing\Jobs;

use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Services\BriefingDataCollector;
use App\Modules\DailyBriefing\Services\BriefingGenerator;
use App\Modules\MCP\Adapters\GmailAdapter;
use App\Modules\MCP\Adapters\ZohoCliqAdapter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDailyBriefingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly User $user,
        public readonly ?string $date = null
    ) {}

    public function handle(
        BriefingDataCollector $collector,
        BriefingGenerator $generator,
        GmailAdapter $gmailAdapter,
        ZohoCliqAdapter $cliqAdapter
    ): void {
        $date = Carbon::parse($this->date ?? now()->toDateString());

        try {
            $data = $collector->collect($this->user, $date);
            $briefing = $generator->generate($this->user, $data);

            // Attempt to deliver via configured channels
            $deliveredVia = [];

            if ($gmailAdapter->sendBriefingEmail($this->user, $briefing)) {
                $deliveredVia[] = 'email';
            }

            if ($cliqAdapter->sendDailyBriefing($this->user, $briefing)) {
                $deliveredVia[] = 'zoho_cliq';
            }

            $briefing->update([
                'delivered_via' => $deliveredVia,
                'delivered_at'  => now(),
                'status'        => 'delivered',
            ]);
        } catch (\Exception $e) {
            Log::error("Daily briefing generation failed for user {$this->user->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
