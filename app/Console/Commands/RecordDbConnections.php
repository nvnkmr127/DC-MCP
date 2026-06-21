<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;

class RecordDbConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pulse:record-db-connections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Records the number of active PostgreSQL database connections to Pulse';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = DB::table('pg_stat_activity')
            ->where('datname', DB::connection()->getDatabaseName())
            ->count();

        Pulse::record('db_connections', 'postgres', $count);
        
        $this->info("Recorded {$count} DB connections.");
    }
}
