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
        Schema::create('kpi_definitions', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->string('name');
            $blueprint->string('slug');
            $blueprint->text('description')->nullable();
            $blueprint->enum('category', ['marketing', 'project', 'financial', 'team', 'client']);
            $blueprint->enum('source', ['manual', 'meta_ads', 'google_analytics', 'notion', 'internal']);
            $blueprint->uuid('mcp_connection_id')->nullable();
            $blueprint->enum('aggregation', ['sum', 'average', 'last_value', 'count', 'percentage']);
            $blueprint->string('unit')->default('INR');
            $blueprint->decimal('target_value', 15, 4)->nullable();
            $blueprint->enum('target_direction', ['higher_better', 'lower_better'])->default('higher_better');
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('mcp_connection_id')->references('id')->on('mcp_connections')->onDelete('set null');

            $blueprint->unique(['organization_id', 'slug']);
            $blueprint->index('organization_id');
            $blueprint->index('category');
        });

        // Check if sqlite is running (typical for unit tests)
        if (config('database.default') === 'sqlite' || DB::getDriverName() === 'sqlite') {
            Schema::create('metric_snapshots', function (Blueprint $blueprint) {
                $blueprint->uuid('id');
                $blueprint->uuid('organization_id');
                $blueprint->uuid('kpi_definition_id');
                $blueprint->uuid('project_id')->nullable();
                $blueprint->uuid('client_id')->nullable();
                $blueprint->uuid('mcp_connection_id')->nullable();
                $blueprint->decimal('value', 15, 4);
                $blueprint->string('dimension_1')->nullable();
                $blueprint->string('dimension_2')->nullable();
                $blueprint->json('metadata')->nullable();
                $blueprint->string('source_external_id')->nullable();
                $blueprint->timestamp('recorded_at');
                $blueprint->timestamp('synced_at');
                $blueprint->date('date_key');
                
                $blueprint->primary(['id', 'date_key']);
                
                $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                $blueprint->foreign('kpi_definition_id')->references('id')->on('kpi_definitions')->onDelete('cascade');
                $blueprint->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
                $blueprint->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
                $blueprint->foreign('mcp_connection_id')->references('id')->on('mcp_connections')->onDelete('set null');
            });
        } else {
            // PostgreSQL range partitioning for metric_snapshots by date_key
            DB::statement('
                CREATE TABLE metric_snapshots (
                    id UUID NOT NULL,
                    organization_id UUID NOT NULL,
                    kpi_definition_id UUID NOT NULL,
                    project_id UUID NULL,
                    client_id UUID NULL,
                    mcp_connection_id UUID NULL,
                    value DECIMAL(15, 4) NOT NULL,
                    dimension_1 VARCHAR(255) NULL,
                    dimension_2 VARCHAR(255) NULL,
                    metadata JSONB NULL,
                    source_external_id VARCHAR(255) NULL,
                    recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    synced_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    date_key DATE NOT NULL,
                    PRIMARY KEY (id, date_key),
                    CONSTRAINT fk_ms_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
                    CONSTRAINT fk_ms_kpi FOREIGN KEY (kpi_definition_id) REFERENCES kpi_definitions (id) ON DELETE CASCADE,
                    CONSTRAINT fk_ms_proj FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL,
                    CONSTRAINT fk_ms_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL,
                    CONSTRAINT fk_ms_mcp FOREIGN KEY (mcp_connection_id) REFERENCES mcp_connections (id) ON DELETE SET NULL
                ) PARTITION BY RANGE (date_key);
            ');

            // Create default partition to avoid crashes when inserting outside explicit ranges
            DB::statement('
                CREATE TABLE metric_snapshots_default PARTITION OF metric_snapshots DEFAULT;
            ');

            // Pre-create some monthly partitions for 2026 for efficiency
            for ($month = 1; $month <= 12; $month++) {
                $year = 2026;
                $startDate = sprintf('%04d-%02d-01', $year, $month);
                $endDate = ($month == 12) 
                    ? sprintf('%04d-01-01', $year + 1)
                    : sprintf('%04d-%02d-01', $year, $month + 1);
                
                $partitionName = sprintf('metric_snapshots_y%04dm%02d', $year, $month);
                DB::statement("
                    CREATE TABLE {$partitionName} PARTITION OF metric_snapshots
                    FOR VALUES FROM ('{$startDate}') TO ('{$endDate}');
                ");
            }

            // Add indexes to the parent partitioned table (Postgres automatically propagates indexes to partitions)
            DB::statement('CREATE INDEX idx_ms_org ON metric_snapshots (organization_id);');
            DB::statement('CREATE INDEX idx_ms_kpi ON metric_snapshots (kpi_definition_id);');
            DB::statement('CREATE INDEX idx_ms_proj ON metric_snapshots (project_id);');
            DB::statement('CREATE INDEX idx_ms_client ON metric_snapshots (client_id);');
            DB::statement('CREATE INDEX idx_ms_date_key ON metric_snapshots (date_key);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite' || DB::getDriverName() === 'sqlite') {
            Schema::dropIfExists('metric_snapshots');
        } else {
            // Dropping the partitioned parent table drops all child partitions as well
            DB::statement('DROP TABLE IF EXISTS metric_snapshots CASCADE;');
        }
        Schema::dropIfExists('kpi_definitions');
    }
};
