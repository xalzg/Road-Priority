<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('road_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administrative_area_id')
                ->nullable()
                ->constrained('administrative_areas')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('name', 191)->nullable();
            $table->string('road_reference_code', 64)->unique('uq_road_segments_reference_code');
            $table->json('geometry_geojson')->nullable();
            $table->decimal('centroid_latitude', 10, 7)->nullable();
            $table->decimal('centroid_longitude', 10, 7)->nullable();
            $table->decimal('length_meters', 10, 2)->nullable();
            $table->string('road_function', 32)->nullable();
            $table->string('road_authority', 32)->nullable();
            $table->decimal('nearest_facility_meters', 10, 2)->nullable();
            $table->string('source_name', 191)->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->string('data_origin', 32)->default('public_dataset');
            $table->boolean('is_demo')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['centroid_latitude', 'centroid_longitude'], 'idx_road_segments_centroid');
            $table->index('road_function', 'idx_road_segments_function');
            $table->index('road_authority', 'idx_road_segments_authority');
            $table->index(['is_active', 'is_demo'], 'idx_road_segments_active_demo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('road_segments');
    }
};
