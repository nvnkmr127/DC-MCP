<?php

namespace App\Modules\Standup\Console\Commands;

use App\Modules\Auth\Models\User;
use App\Modules\Standup\Models\EodStandup;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Console\Command;

class SendStandupReminderCommand extends Command
{
    protected $signature = 'standup:send-reminders';
    protected $description = 'Send EOD standup reminders to team members who have not submitted today.';

    public function handle(NotificationService $notificationService): int
    {
        $today = today()->toDateString();

        $submitted = EodStandup::whereDate('date', $today)
            ->pluck('user_id')
            ->toArray();

        $pending = User::where('is_active', true)
            ->whereNotIn('id', $submitted)
            ->get();

        foreach ($pending as $user) {
            $notificationService->send($user, 'standup_reminder', [
                'message' => 'Please submit your EOD standup before end of day.',
                'action_url' => '/standup',
            ]);
        }

        $this->info("Sent standup reminders to {$pending->count()} team member(s).");
        return Command::SUCCESS;
    }
}
