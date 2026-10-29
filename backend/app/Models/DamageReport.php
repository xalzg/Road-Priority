<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageReport extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_NEEDS_INFO = 'needs_info';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_NEEDS_INFO,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
        self::STATUS_RESOLVED,
    ];

    protected $fillable = [
        'report_code',
        'reporter_id',
        'road_segment_id',
        'segment_match_distance_m',
        'damage_type',
        'description',
        'latitude',
        'longitude',
        'gps_accuracy_m',
        'reported_at',
        'status',
        'verification_notes',
        'verified_by',
        'verified_at',
        'duplicate_of_id',
        'data_origin',
        'is_demo',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'gps_accuracy_m' => 'float',
        'segment_match_distance_m' => 'float',
        'reported_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_demo' => 'boolean',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function roadSegment(): BelongsTo
    {
        return $this->belongsTo(RoadSegment::class, 'road_segment_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReportPhoto::class, 'damage_report_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReportStatusHistory::class, 'damage_report_id');
    }

    public function damageAssessments(): HasMany
    {
        return $this->hasMany(DamageAssessment::class, 'damage_report_id');
    }
}

