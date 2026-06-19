<?php

namespace App\Modules\DailyBriefing\Jobs;

use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use App\Modules\MCP\Adapters\ZohoCliqAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBriefingCliqJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public string $queue = 'default';

    public function backoff(): array
    {
        return [30, 60];
    }

    public function __construct(
        public readonly User $user,
        public readonly DailyBriefing $briefing
    ) {}

    public function handle(ZohoCliqAdapter $cliqAdapter): void
    {
        try {
            if ($cliqAdapter->sendDailyBriefing($this->user, $this->briefing)) {
                $deliveredVia = $this->briefing->delivered_via ?? [];
                if (!in_array('zoho_cliq', $deliveredVia)) {
                    $deliveredVia[] = 'zoho_cliq';
                    $this->briefing->update([
                        'delivered_via' => $deliveredVia,
                        'delivered_at'  => now(),
                        'status'        => 'delivered',
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('SendBriefingCliqJob failed', [
                'user_id'     => $this->user->id,
                'briefing_id' => $this->briefing->id,
                'exception'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
