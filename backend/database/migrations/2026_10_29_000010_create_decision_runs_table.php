<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code', 36)->unique('uq_decision_runs_run_code');
            $table->string('name', 191)->nullable();
            $table->foreignId('criterion_weight_version_id')
                ->constrained('criterion_weight_versions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('algorithm_name', 64)->default('AHP-TOPSIS');
            $table->string('algorithm_version', 32)->default('ahp-topsis-1.0.0');
            $table->string('status', 24)->default('completed');
            $table->boolean('is_simulation')->default(false);
            $table->char('input_hash', 64);
            $table->json('input_snapshot_reference');
            $table->foreignId('executed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_simulation', 'executed_at'], 'idx_decision_runs_status_sim_exec');
            $table->index('input_hash', 'idx_decision_runs_input_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_runs');
    }
};
