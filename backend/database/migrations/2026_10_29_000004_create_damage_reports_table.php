<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code', 36)->unique('uq_damage_reports_report_code');
            $table->foreignId('reporter_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('road_segment_id')
                ->nullable()
                ->constrained('road_segments')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->decimal('segment_match_distance_m', 8, 2)->nullable();
            $table->string('damage_type', 64);
            $table->text('description');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->string('status', 32)->default('submitted');
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('duplicate_of_id')
                ->nullable()
                ->constrained('damage_reports')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('data_origin', 32)->default('citizen_report');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index(['status', 'reported_at'], 'idx_damage_reports_status_reported');
            $table->index(['latitude', 'longitude'], 'idx_damage_reports_coordinates');
            $table->index('damage_type', 'idx_damage_reports_damage_type');
            $table->index('is_demo', 'idx_damage_reports_is_demo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};

