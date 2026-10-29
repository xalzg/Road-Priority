<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecisionRun extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'run_code',
        'name',
        'criterion_weight_version_id',
        'algorithm_name',
        'algorithm_version',
        'status',
        'is_simulation',
        'input_hash',
        'input_snapshot_reference',
        'executed_by',
        'executed_at',
    ];

    protected $casts = [
        'is_simulation' => 'boolean',
        'input_snapshot_reference' => 'array',
        'executed_at' => 'datetime',
    ];

    public function weightVersion(): BelongsTo
    {
        return $this->belongsTo(CriterionWeightVersion::class, 'criterion_weight_version_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DecisionRunItem::class, 'decision_run_id');
    }
}
