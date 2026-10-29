<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_report_id')
                ->constrained('damage_reports')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['damage_report_id', 'changed_at'], 'idx_status_histories_report_changed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_status_histories');
    }
};
