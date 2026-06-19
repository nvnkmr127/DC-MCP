<?php

namespace App\Modules\DailyBriefing\Jobs;

use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Services\BriefingDataCollector;
use App\Modules\DailyBriefing\Services\BriefingGenerator;
use App\Modules\TaskEngine\Services\TaskSuggestionService;
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
    public int $timeout = 180; // LLM generation can be slow on large orgs
    public string $queue = 'ai-tasks';

    /** Backoff: retry after 60s on second attempt */
    public function backoff(): array
    {
        return [60];
    }

    public function __construct(
        public readonly User $user,
        public readonly ?string $date = null
    ) {}

    public function handle(
        BriefingDataCollector $collector,
        BriefingGenerator $generator,
        TaskSuggestionService $suggestionService,
    ): void {
        $date = Carbon::parse($this->date ?? now()->toDateString());

        Log::info('Daily briefing job started', [
            'user_id' => $this->user->id,
            'date'    => $date->toDateString(),
        ]);

        try {
            $data   = $collector->collect($this->user, $date);
            $result = $generator->generate($this->user, $data);

            $briefing    = $result['briefing'];
            $suggestions = $result['suggestions'];

            if (!empty($suggestions)) {
                try {
                    $suggestionService->parseAndStoreFromBriefing($briefing, $suggestions);
                } catch (\Exception $e) {
                    Log::error('Could not store task suggestions for briefing', [
                        'briefing_id' => $briefing->id,
                        'user_id'     => $this->user->id,
                        'exception'   => $e->getMessage(),
                    ]);
                }
            }

            // Dispatch notification jobs instead of synchronous execution
            SendBriefingEmailJob::dispatch($this->user, $briefing);
            SendBriefingCliqJob::dispatch($this->user, $briefing);

            Log::info('Daily briefing notification jobs dispatched', [
                'user_id'       => $this->user->id,
                'briefing_id'   => $briefing->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Daily briefing generation failed', [
                'user_id'   => $this->user->id,
                'date'      => $date->toDateString(),
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Daily briefing job permanently failed', [
            'user_id'   => $this->user->id,
            'date'      => $this->date,
            'exception' => $exception->getMessage(),
        ]);
    }
}
