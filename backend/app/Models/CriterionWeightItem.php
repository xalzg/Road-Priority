<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterionWeightItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'criterion_weight_version_id',
        'decision_criterion_id',
        'weight_value',
    ];

    protected $casts = [
        'weight_value' => 'float',
    ];

    public function weightVersion(): BelongsTo
    {
        return $this->belongsTo(CriterionWeightVersion::class, 'criterion_weight_version_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(DecisionCriterion::class, 'decision_criterion_id');
    }
}
