<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_report_id')
                ->constrained('damage_reports')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('storage_disk', 32)->default('local');
            $table->string('file_path', 255);
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['damage_report_id', 'is_public'], 'idx_report_photos_report_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_photos');
    }
};

