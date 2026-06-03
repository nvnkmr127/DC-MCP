<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->foreign('parent_task_id')
                ->references('id')
                ->on('tasks')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->foreign('parent_task_id')
                ->references('id')
                ->on('tasks')
                ->onDelete('cascade');
        });
    }
};
