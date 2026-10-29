<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('administrative_level', 32);
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('administrative_areas')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('external_code', 64)->nullable()->unique('uq_administrative_areas_external_code');
            $table->json('boundary_geojson')->nullable();
            $table->boolean('is_study_area')->default(false);
            $table->timestamps();

            $table->index('administrative_level', 'idx_admin_areas_level');
            $table->index('is_study_area', 'idx_admin_areas_study_area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_areas');
    }
};
