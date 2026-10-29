<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterionWeightVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPUTED = 'computed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'version_name',
        'status',
        'criteria_count',
        'lambda_max',
        'consistency_index',
        'consistency_ratio',
        'is_consistent',
        'aggregation_method',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'criteria_count' => 'integer',
        'lambda_max' => 'float',
        'consistency_index' => 'float',
        'consistency_ratio' => 'float',
        'is_consistent' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function weightItems(): HasMany
    {
        return $this->hasMany(CriterionWeightItem::class, 'criterion_weight_version_id');
    }

    public function ahpComparisons(): HasMany
    {
        return $this->hasMany(AhpPairwiseComparison::class, 'criterion_weight_version_id');
    }

    public function decisionRuns(): HasMany
    {
        return $this->hasMany(DecisionRun::class, 'criterion_weight_version_id');
    }
}

