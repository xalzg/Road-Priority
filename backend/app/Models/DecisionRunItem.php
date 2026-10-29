<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionRunItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'decision_run_id',
        'road_segment_id',
        'is_included',
        'exclusion_reason',
        'rank_position',
        'preference_score',
        'd_plus',
        'd_minus',
        'largest_driving_criterion',
        'input_values_snapshot',
        'intermediate_values_snapshot',
    ];

    protected $casts = [
        'is_included' => 'boolean',
        'rank_position' => 'integer',
        'preference_score' => 'float',
        'd_plus' => 'float',
        'd_minus' => 'float',
        'input_values_snapshot' => 'array',
        'intermediate_values_snapshot' => 'array',
    ];

    public function decisionRun(): BelongsTo
    {
        return $this->belongsTo(DecisionRun::class, 'decision_run_id');
    }

    public function roadSegment(): BelongsTo
    {
        return $this->belongsTo(RoadSegment::class, 'road_segment_id');
    }
}

