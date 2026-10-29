<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class DamageAssessment extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINAL = 'final';

    protected $fillable = [
        'road_segment_id',
        'damage_report_id',
        'assessed_by',
        'assessment_date',
        'protocol_version',
        'severity_level',
        'safety_risk_score',
        'road_function_score',
        'impact_score',
        'measurements',
        'status',
        'notes',
        'assessment_source',
        'is_demo',
    ];

    protected $casts = [
        'assessment_date' => 'datetime',
        'severity_level' => 'float',
        'safety_risk_score' => 'float',
        'road_function_score' => 'float',
        'impact_score' => 'float',
        'measurements' => 'array',
        'is_demo' => 'boolean',
    ];

    protected static function booted(): void
    {
        // PRD 02 §4.8: "Jika laporan dan segmen jalan sama-sama tidak tersedia, penilaian tidak boleh dibuat."
        static::saving(function (self $assessment) {
            if ($assessment->road_segment_id === null && $assessment->damage_report_id === null) {
                throw new InvalidArgumentException(
                    'DamageAssessment wajib memiliki minimal salah satu dari road_segment_id atau damage_report_id.'
                );
            }
        });
    }

    public function roadSegment(): BelongsTo
    {
        return $this->belongsTo(RoadSegment::class, 'road_segment_id');
    }

    public function damageReport(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'damage_report_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}

