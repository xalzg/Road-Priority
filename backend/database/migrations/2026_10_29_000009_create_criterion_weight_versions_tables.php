<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterion_weight_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_name', 191);
            $table->string('status', 24)->default('draft'); // draft | computed | approved | archived
            $table->unsignedSmallInteger('criteria_count')->nullable();
            $table->decimal('lambda_max', 18, 12)->nullable();
            $table->decimal('consistency_index', 18, 12)->nullable();
            $table->decimal('consistency_ratio', 18, 12)->nullable();
            $table->boolean('is_consistent')->default(false);
            $table->string('aggregation_method', 32)->default('geometric_mean');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_consistent'], 'idx_weight_versions_status_consistent');
        });

        Schema::create('criterion_weight_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_weight_version_id')
                ->constrained('criterion_weight_versions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('decision_criterion_id')
                ->constrained('decision_criteria')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->decimal('weight_value', 18, 12);
            $table->timestamps();

            $table->unique(
                ['criterion_weight_version_id', 'decision_criterion_id'],
                'uq_weight_version_criterion'
            );
        });

        Schema::create('ahp_pairwise_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_weight_version_id')
                ->constrained('criterion_weight_versions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('expert_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('criterion_a_id')
                ->constrained('decision_criteria')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('criterion_b_id')
                ->constrained('decision_criteria')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->decimal('comparison_value', 18, 12);
            $table->timestamps();

            $table->unique(
                ['criterion_weight_version_id', 'expert_id', 'criterion_a_id', 'criterion_b_id'],
                'uq_ahp_version_expert_pair'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahp_pairwise_comparisons');
        Schema::dropIfExists('criterion_weight_items');
        Schema::dropIfExists('criterion_weight_versions');
    }
};
