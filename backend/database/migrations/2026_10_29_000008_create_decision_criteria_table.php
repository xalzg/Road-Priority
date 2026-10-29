<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique('uq_decision_criteria_code');
            $table->string('name', 191);
            $table->text('description');
            $table->string('data_type', 32)->default('numeric');
            $table->string('unit', 64)->nullable();
            $table->string('criterion_type', 16); // benefit | cost
            $table->decimal('minimum_value', 14, 4)->nullable();
            $table->decimal('maximum_value', 14, 4)->nullable();
            $table->string('aggregation_rule', 191)->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'idx_decision_criteria_active');
            $table->index('criterion_type', 'idx_decision_criteria_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_criteria');
    }
};

