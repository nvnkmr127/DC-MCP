<?php

namespace App\Modules\TaskEngine\Console\Commands;

use App\Modules\TaskEngine\Services\SlaEngine;
use Illuminate\Console\Command;

class CheckSlasCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:check-slas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate task deadline thresholds, log SLA warnings or breaches, and dispatch notifications.';

    /**
     * Execute the console command.
     */
    public function handle(SlaEngine $slaEngine): int
    {
        $this->info('Starting SLA check...');
        $slaEngine->checkSlas();
        $this->info('SLA check completed successfully.');
        return Command::SUCCESS;
    }
}
