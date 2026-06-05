<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Drop type check constraint
            DB::statement("ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_type_check");
            // Alter type column to be varchar(255) nullable
            DB::statement("ALTER TABLE projects ALTER COLUMN type TYPE VARCHAR(255)");
            DB::statement("ALTER TABLE projects ALTER COLUMN type DROP NOT NULL");

            // Alter budget column to be nullable
            DB::statement("ALTER TABLE projects ALTER COLUMN budget DROP NOT NULL");
        } else {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('type')->nullable()->change();
                $table->decimal('budget', 12, 2)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting not null constraint might fail if null records exist.
    }
};
