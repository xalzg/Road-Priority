<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('road_segment_id')
                ->nullable()
                ->constrained('road_segments')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('damage_report_id')
                ->nullable()
                ->constrained('damage_reports')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('assessed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('assessment_date')->useCurrent();
            $table->string('protocol_version', 32)->default('SDI-ADAPTED-1.0');
            $table->decimal('severity_level', 8, 4);
            $table->decimal('safety_risk_score', 8, 4)->nullable();
            $table->decimal('road_function_score', 8, 4)->nullable();
            $table->decimal('impact_score', 8, 4)->nullable();
            $table->json('measurements')->nullable();
            $table->string('status', 16)->default('draft');
            $table->text('notes')->nullable();
            $table->string('assessment_source', 32)->default('field_survey');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index(['road_segment_id', 'status', 'assessment_date'], 'idx_assessments_segment_status_date');
            $table->index('assessment_source', 'idx_assessments_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_assessments');
    }
};
