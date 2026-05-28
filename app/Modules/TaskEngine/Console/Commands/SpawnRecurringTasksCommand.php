<?php

namespace App\Modules\TaskEngine\Console\Commands;

use App\Modules\Auth\Models\Organization;
use App\Modules\TaskEngine\Services\RecurringTaskEngine;
use Illuminate\Console\Command;

class SpawnRecurringTasksCommand extends Command
{
    protected $signature = 'tasks:spawn-recurring';
    protected $description = 'Spawn tasks from recurring rules that are due.';

    public function handle(RecurringTaskEngine $engine): int
    {
        $total = 0;

        Organization::all()->each(function ($org) use ($engine, &$total) {
            $count = $engine->spawnDue($org->id);
            if ($count > 0) {
                $this->info("Org {$org->name}: spawned {$count} task(s).");
                $total += $count;
            }
        });

        $this->info("Total recurring tasks spawned: {$total}");
        return Command::SUCCESS;
    }
}
