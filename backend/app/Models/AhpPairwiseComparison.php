<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AhpPairwiseComparison extends Model
{
    use HasFactory;

    protected $fillable = [
        'criterion_weight_version_id',
        'expert_id',
        'criterion_a_id',
        'criterion_b_id',
        'comparison_value',
    ];

    protected $casts = [
        'comparison_value' => 'float',
    ];

    public function weightVersion(): BelongsTo
    {
        return $this->belongsTo(CriterionWeightVersion::class, 'criterion_weight_version_id');
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expert_id');
    }

    public function criterionA(): BelongsTo
    {
        return $this->belongsTo(DecisionCriterion::class, 'criterion_a_id');
    }

    public function criterionB(): BelongsTo
    {
        return $this->belongsTo(DecisionCriterion::class, 'criterion_b_id');
    }
}

