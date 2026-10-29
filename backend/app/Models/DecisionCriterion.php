<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecisionCriterion extends Model
{
    use HasFactory;

    public const TYPE_BENEFIT = 'benefit';
    public const TYPE_COST = 'cost';

    protected $table = 'decision_criteria';

    protected $fillable = [
        'code',
        'name',
        'description',
        'data_type',
        'unit',
        'criterion_type',
        'minimum_value',
        'maximum_value',
        'aggregation_rule',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'minimum_value' => 'float',
        'maximum_value' => 'float',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function weightItems(): HasMany
    {
        return $this->hasMany(CriterionWeightItem::class, 'decision_criterion_id');
    }

    public function ahpComparisonsAsA(): HasMany
    {
        return $this->hasMany(AhpPairwiseComparison::class, 'criterion_a_id');
    }

    public function ahpComparisonsAsB(): HasMany
    {
        return $this->hasMany(AhpPairwiseComparison::class, 'criterion_b_id');
    }
}
