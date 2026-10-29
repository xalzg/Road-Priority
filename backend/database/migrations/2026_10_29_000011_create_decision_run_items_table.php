<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_run_id')
                ->constrained('decision_runs')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('road_segment_id')
                ->constrained('road_segments')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->boolean('is_included')->default(true);
            $table->string('exclusion_reason', 255)->nullable();
            $table->unsignedInteger('rank_position')->nullable();
            $table->decimal('preference_score', 18, 12)->nullable();
            $table->decimal('d_plus', 18, 12)->nullable();
            $table->decimal('d_minus', 18, 12)->nullable();
            $table->string('largest_driving_criterion', 32)->nullable();
            $table->json('input_values_snapshot');
            $table->json('intermediate_values_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['decision_run_id', 'road_segment_id'], 'uq_run_road_segment');
            $table->unique(['decision_run_id', 'rank_position'], 'uq_run_rank_position');
            $table->index(['decision_run_id', 'is_included', 'rank_position'], 'idx_run_items_included_rank');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_run_items');
    }
};

