<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('queue_job_runs', function (Blueprint $table) {
            // Add columns (AFTER works on MySQL; other drivers ignore it gracefully)
                $table->unsignedInteger('retries')->default(0)->after('attempt');

                $table->unsignedBigInteger('retried_from_id')->nullable()->after('retries');

                // Add FK constraint in a separate statement for portability
                $table->foreign('retried_from_id')
                    ->references('id')
                    ->on('queue_job_runs')
                    ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('queue_job_runs', function (Blueprint $table) {
                $table->dropForeign(['retried_from_id']);
                $table->dropColumn('retried_from_id');
                $table->dropColumn('retries');
        });
    }
};
